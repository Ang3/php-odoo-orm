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
    private $classMetadataRegistry;
    private $reader;

    /**
     * @throws RuntimeException on cache failure
     */
    public function __construct(ObjectManager $objectManager, Reader $reader)
    {
        $this->objectManager = $objectManager;
        $this->classMetadataRegistry = new ClassMetadataRegistry();
        $this->reader = $reader;
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

        if ($classMetadata = $this->classMetadataRegistry->get($reflectionClass->getName())) {
            return $classMetadata;
        }

        $classMetadata = $this->loadClassMetadata($reflectionClass);
        $this->classMetadataRegistry->register($classMetadata);

        return $classMetadata;
    }

    /**
     * Load metadata file classes and returns a new class metadata registry.
     *
     * @param string[]|string $paths
     */
    public function loadFiles($paths): ClassMetadataRegistry
    {
        $classMetadataRegistry = new ClassMetadataRegistry();

        $paths = array_filter((array) $paths);
        $classes = get_declared_classes();
        [$loadedFiles, $loadedClasses] = [[], []];
        $reflector = self::getReflector();

        foreach ($paths as $path) {
            $filename = realpath($path);

            if (!$filename) {
                throw new RuntimeException(sprintf('The path "%s" is not valid', $path));
            }

            foreach ($classes as $key => $className) {
                $reflectionClass = $reflector->getClass($className);

                if (in_array($reflectionClass->getName(), $loadedClasses, true)) {
                    continue;
                }

                if ($reflectionClass->isAbstract() || $reflectionClass->isInterface()) {
                    continue;
                }

                $classFilename = $reflectionClass->getFileName();

                if (!$classFilename || in_array($classFilename, $loadedFiles, true)) {
                    continue;
                }

                if (false === (0 === strpos($classFilename, $filename))) {
                    continue;
                }

                $classMetadata = $this->loadClassMetadata($reflectionClass);
                $classMetadataRegistry->register($classMetadata);

                $loadedFiles[] = $classFilename;
                $loadedClasses[] = $classMetadata->getClassName();
            }
        }

        return $classMetadataRegistry;
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
        }

        return $classMetadata;
    }

    public function getObjectManager(): ObjectManager
    {
        return $this->objectManager;
    }

    public function setClassMetadataRegistry(ClassMetadataRegistry $classMetadataRegistry): self
    {
        $this->classMetadataRegistry = $classMetadataRegistry;

        return $this;
    }

    public function getClassMetadataRegistry(): ClassMetadataRegistry
    {
        return $this->classMetadataRegistry;
    }

    public function getReader(): Reader
    {
        return $this->reader;
    }
}
