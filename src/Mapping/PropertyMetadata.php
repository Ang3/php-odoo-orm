<?php

namespace Ang3\Component\Odoo\ORM\Mapping;

use Ang3\Component\Odoo\ORM\Internal\ReflectorAwareTrait;
use Ang3\Component\Odoo\ORM\Schema\Field;
use ReflectionProperty;

class PropertyMetadata
{
    use ReflectorAwareTrait;

    /**
     * @var ClassMetadata
     */
    protected $declaringClass;

    /**
     * @var Field
     */
    protected $field;

    /**
     * @var string
     */
    protected $propertyName;

    public function __construct(ClassMetadata $declaringClass, ReflectionProperty $property, Field $field)
    {
        $this->declaringClass = $declaringClass;
        $this->propertyName = $property->getName();
        $this->field = $field;
    }

    /**
     * @param mixed $value
     */
    public function setValue(object $object, $value): self
    {
        $this
            ->getReflection()
            ->setValue($object, $value);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getValue(object $object)
    {
        return $this
            ->getReflection()
            ->getValue($object);
    }

    public function getFullName(): string
    {
        return sprintf('%s::%s', $this->getClassName(), $this->propertyName);
    }

    public function getDeclaringClass(): ClassMetadata
    {
        return $this->declaringClass;
    }

    public function setDeclaringClass(ClassMetadata $declaringClass): PropertyMetadata
    {
        $this->declaringClass = $declaringClass;

        return $this;
    }

    public function getField(): Field
    {
        return $this->field;
    }

    public function getClassName(): string
    {
        return $this->declaringClass->getClassName();
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function getFieldName(): ?string
    {
        return $this->field->getName();
    }

    public function getReflection(): ReflectionProperty
    {
        $property = self::getReflector()->getProperty($this->declaringClass->getClassName(), $this->propertyName);
        $property->setAccessible(true);

        return $property;
    }
}
