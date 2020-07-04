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
        $classMetadata = $this->models[$modelName] ?? null;

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
        $cacheKey = s($reflectionClass->getName())
            ->replaceMatches('#[^a-zA-Z0-9]+#', '_')
            ->toString();

        try {
            return $cache->get($cacheKey, function () use ($factory, $subject) {
                return $factory->loadClassMetadata($subject);
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

            $this->models[$model->getName()] = $classMetadata;
        }

        return $classMetadata;
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
