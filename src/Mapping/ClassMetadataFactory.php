<?php

namespace Ang3\Component\Odoo\ORM\Mapping;

use Ang3\Component\Odoo\ORM\Annotation;
use Ang3\Component\Odoo\ORM\Exception\RuntimeException;
use Ang3\Component\Odoo\ORM\Internal\ReflectorAwareTrait;
use Ang3\Component\Odoo\ORM\ObjectManager;
use Doctrine\Common\Annotations\Reader;
use Psr\Cache\InvalidArgumentException;
use ReflectionClass;
use function Symfony\Component\String\s;

class ClassMetadataFactory
{
    use ReflectorAwareTrait;

    private $objectManager;
    private $metadataLoader;
    private $reader;

    /**
     * @var array<string, ClassMetadata>
     */
    private $models = [];

    /**
     * @throws RuntimeException on cache failure
     */
    public function __construct(ObjectManager $objectManager, Reader $reader)
    {
        $this->objectManager = $objectManager;
        $this->metadataLoader = new MetadataLoader($this);
        $this->reader = $reader;
    }

    /**
     * @throws MappingException when no class metadata found for the model
     */
    public function resolveClassMetadata(string $modelName): ClassMetadata
    {
        $cacheKey = $this->getModelClassMetadataCacheKey($modelName);

        try {
            $classMetadata = $this->objectManager
                ->getConfiguration()
                ->getMetadataCache()
                ->get($cacheKey, function () {
                    return null;
                });
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException(sprintf('Failed to retrieve class metadata cache entry at key "%s"', $cacheKey), 0, $e);
        }

        if (!$classMetadata) {
            throw MappingException::modelNotSupported($modelName);
        }

        return $classMetadata;
    }

    /**
     * Return cached or new class metadata for the subject.
     *
     * @param mixed $subject
     *
     * @throws RuntimeException on cache errors
     */
    public function getClassMetadata($subject): ClassMetadata
    {
        if ($subject instanceof ClassMetadata) {
            return $subject;
        }

        $reflectionClass = self::getReflector()->getClass($subject);

        $factory = $this;
        $cache = $this->objectManager
            ->getConfiguration()
            ->getMetadataCache();
        $cacheKey = s(sprintf('%s.%s', $this->objectManager->getClient()->getIdentifier(), $reflectionClass->getName()))
            ->replaceMatches('#[^a-zA-Z0-9_]+#', '_')
            ->toString();

        try {
            return $cache->get($cacheKey, function () use ($factory, $reflectionClass) {
                return $factory->loadClassMetadata($reflectionClass);
            });
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException(sprintf('Failed to retrieve class metadata cache entry at key "%s"', $cacheKey), 0, $e);
        }
    }

    /**
     * Load class metadata.
     *
     * @throws RuntimeException on cache errors
     */
    public function loadClassMetadata(ReflectionClass $reflectionClass): ClassMetadata
    {
        /** @var Annotation\Model|null $modelAnnotation */
        $modelAnnotation = $this->reader->getClassAnnotation($reflectionClass, Annotation\Model::class);

        $model = $modelAnnotation ? $this->objectManager
            ->getSchema()
            ->getModel($modelAnnotation->name) : null;

        [$modelName, $repositoryClass, $isTransient] = [
            $model ? $model->getName() : null,
            $modelAnnotation ? $modelAnnotation->repositoryClass : null,
            $model ? $model->isTransient() : true,
        ];

        $classMetadata = new ClassMetadata($reflectionClass, $modelName, $repositoryClass, $isTransient);
        $metadataCache = $this->objectManager
            ->getConfiguration()
            ->getMetadataCache();

        if ($model) {
            $properties = $reflectionClass->getProperties();

            foreach ($properties as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                /** @var Annotation\Field|null $fieldAnnotation */
                $fieldAnnotation = $this->reader->getPropertyAnnotation($property, Annotation\Field::class);

                if (!$fieldAnnotation) {
                    continue;
                }

                $field = $model->getField($fieldAnnotation->name);
                $propertyMetadata = new PropertyMetadata($classMetadata, $property, $field);
                $classMetadata->addProperty($propertyMetadata);
            }

            $cacheKey = $this->getModelClassMetadataCacheKey($modelName);

            try {
                $metadataCache->delete($cacheKey);
            } catch (InvalidArgumentException $e) {
            }

            try {
                $classMetadata = $metadataCache->get($cacheKey, function () use ($classMetadata) {
                    return $classMetadata;
                });
            } catch (InvalidArgumentException $e) {
            }
        }

        return $classMetadata;
    }

    private function getModelClassMetadataCacheKey(string $modelName): string
    {
        return sprintf('odoo_model.class_metadata.%s', $modelName);
    }

    public function getObjectManager(): ObjectManager
    {
        return $this->objectManager;
    }

    public function getMetadataLoader(): MetadataLoader
    {
        return $this->metadataLoader;
    }

    public function getReader(): Reader
    {
        return $this->reader;
    }
}
