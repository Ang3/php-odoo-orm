<?php

namespace Ang3\Component\Odoo\ORM\Mapping;

use Ang3\Component\Odoo\ORM\Annotation;
use Ang3\Component\Odoo\ORM\Exception\RuntimeException;
use Ang3\Component\Odoo\ORM\Internal\ReflectorAwareTrait;
use Ang3\Component\Odoo\ORM\ObjectManager;
use Doctrine\Common\Annotations\Reader;
use ReflectionClass;

class ClassMetadataFactory
{
    use ReflectorAwareTrait;

    private $objectManager;
    private $metadataLoader;
    private $reader;
    private $cache;

    /**
     * @throws RuntimeException on cache failure
     */
    public function __construct(ObjectManager $objectManager, Reader $reader)
    {
        $this->objectManager = $objectManager;
        $this->metadataLoader = new MetadataLoader($this);
        $this->reader = $reader;
        $this->cache = new MetadataCache($this);
    }

    /**
     * @throws MappingException when no class metadata found for the model
     */
    public function resolveClassMetadata(string $modelName): ClassMetadata
    {
        $className = $this->cache->resolve($modelName);

        if (!$className) {
            throw MappingException::modelNotSupported($modelName);
        }

        return $this->getClassMetadata($className);
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

        $factory = $this;
        $reflectionClass = self::getReflector()->getClass($subject);

        return $this->cache->get($reflectionClass, function () use ($factory, $reflectionClass) {
            return $factory->loadClassMetadata($reflectionClass);
        });
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

            $this->cache->add($model->getName(), $reflectionClass);
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

    public function getCache(): MetadataCache
    {
        return $this->cache;
    }
}
