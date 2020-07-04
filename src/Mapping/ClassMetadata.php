<?php

namespace Ang3\Component\Odoo\ORM\Mapping;

use Ang3\Component\Odoo\ORM\Internal\ReflectorAwareTrait;
use ReflectionClass;

class ClassMetadata
{
    use ReflectorAwareTrait;

    /**
     * @var class-string
     */
    private $className;

    /**
     * @var string|null
     */
    private $modelName;

    /**
     * @var string|null
     */
    private $repositoryClass;

    /**
     * @var PropertyMetadata[]
     */
    private $properties = [];

    /**
     * @var array<string, string>
     */
    private $fieldNames = [];

    /**
     * @var bool
     */
    private $transient;

    public function __construct(ReflectionClass $class, string $modelName = null, string $repositoryClass = null, bool $transient = true)
    {
        $this->className = $class->getName();
        $this->modelName = $modelName;
        $this->repositoryClass = $repositoryClass;
        $this->transient = $transient;
    }

    /**
     * @return class-string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    public function getModelName(): ?string
    {
        return $this->modelName;
    }

    public function getRepositoryClass(): ?string
    {
        return $this->repositoryClass;
    }

    /**
     * @throws MappingException when the property was already declared
     */
    public function addProperty(PropertyMetadata $propertyMetadata): void
    {
        $propertyName = $propertyMetadata->getPropertyName();

        if ($existentProperty = $this->getProperty($propertyName)) {
            throw MappingException::duplicateProperty($this->getClassName(), $existentProperty);
        }

        if ($existentProperty = $this->resolveProperty($propertyMetadata->getFieldName())) {
            throw MappingException::duplicateField($existentProperty->getFieldName(), $propertyName, $this->className, $existentProperty);
        }

        $propertyMetadata->setDeclaringClass($this);
        $this->properties[$propertyName] = $propertyMetadata;
        $this->fieldNames[$propertyName] = $propertyMetadata->getFieldName();
    }

    public function getIdProperty(): ?PropertyMetadata
    {
        return $this->resolveProperty('id');
    }

    /**
     * @throws MappingException when the field in not supported by the class
     */
    public function resolveProperty(string $fieldName): ?PropertyMetadata
    {
        $propertyName = array_search($fieldName, $this->fieldNames, true);

        return $propertyName ? $this->properties[$propertyName] : null;
    }

    public function getProperty(string $propertyName): ?PropertyMetadata
    {
        return $this->properties[$propertyName] ?? null;
    }

    public function hasProperty(string $propertyName): bool
    {
        return isset($this->properties[$propertyName]);
    }

    public function hasField(string $fieldName): bool
    {
        return in_array($fieldName, $this->fieldNames, true);
    }

    /**
     * @return iterable|PropertyMetadata[]
     */
    public function getPropertiesIterator(): iterable
    {
        foreach ($this->properties as $name => $property) {
            yield $name => $property;
        }
    }

    /**
     * @return string[]
     */
    public function getPropertyNames(): array
    {
        return array_keys($this->fieldNames);
    }

    /**
     * @return string[]
     */
    public function getFieldNames(): array
    {
        return array_values($this->fieldNames);
    }

    /**
     * @return array<string, string>
     */
    public function getPropertyMapping(): array
    {
        return $this->fieldNames;
    }

    public function getReflection(): ReflectionClass
    {
        return self::getReflector()->getClass($this->className);
    }

    public function isTransient(): bool
    {
        return true === $this->transient;
    }
}
