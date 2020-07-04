<?php

namespace Ang3\Component\Odoo\ORM\Mapping;

use Ang3\Component\Odoo\ORM\Exception\RuntimeException;
use Ang3\Component\Odoo\ORM\Internal\ReflectorAwareTrait;
use Generator;

class MetadataLoader
{
    use ReflectorAwareTrait;

    private $classMetadataFactory;

    public function __construct(ClassMetadataFactory $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;
    }

    /**
     * Load metadata file classes and returns a new class metadata registry.
     *
     * @param string[]|string $paths
     *
     * @return Generator|ClassMetadata[]
     */
    public function load($paths): Generator
    {
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

                $classMetadata = $this->classMetadataFactory->getClassMetadata($reflectionClass);

                $loadedFiles[] = $classFilename;
                $loadedClasses[] = $classMetadata->getClassName();

                yield $classMetadata;
            }
        }
    }
}
