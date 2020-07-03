<?php

namespace Ang3\Component\Odoo\ORM\Mapping;

use Generator;
use LogicException;

class ClassMetadataRegistry implements \IteratorAggregate
{
    /**
     * @var ClassMetadata[]
     */
    private $registry = [];

    /**
     * @return Generator|ClassMetadata[]
     */
    public function getIterator(): Generator
    {
        foreach ($this->registry as $modelName => $classMetadata) {
            yield $modelName => $classMetadata;
        }
    }

    /**
     * @throws LogicException when the model or the class is already registered
     */
    public function register(ClassMetadata $classMetadata): self
    {
        if ($existentClassMetadata = $this->get($classMetadata->getClassName())) {
            throw new LogicException(sprintf('Cannot register class %s because the class was already declared', $classMetadata->getClassName()));
        }

        $modelName = $classMetadata->getModelName();

        if ($modelName && $existentClassMetadata = $this->resolve($modelName)) {
            throw new LogicException(sprintf('Cannot register model "%s" for class %s because the model was already declared for class %s', $modelName, $classMetadata->getClassName(), $existentClassMetadata->getClassName()));
        }

        $this->registry[] = $classMetadata;

        return $this;
    }

    public function get(string $className): ?ClassMetadata
    {
        foreach ($this->registry as $classMetadata) {
            if ($className === $classMetadata->getClassName()) {
                return $classMetadata;
            }
        }

        return null;
    }

    public function resolve(string $modelName): ?ClassMetadata
    {
        foreach ($this->registry as $classMetadata) {
            if ($modelName === $classMetadata->getModelName()) {
                return $classMetadata;
            }
        }

        return null;
    }

    public function clear(): self
    {
        $this->registry = [];

        return $this;
    }
}
