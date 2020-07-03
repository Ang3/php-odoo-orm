<?php

namespace Ang3\Component\Odoo\ORM\Mapping;

use Ang3\Component\Odoo\ORM\Annotation;
use Ang3\Component\Odoo\ORM\Exception\LogicException;
use Ang3\Component\Odoo\ORM\Schema\Field;
use ReflectionProperty;

class MappingException extends LogicException
{
    public static function classNotFound(string $class): self
    {
        return new self(sprintf('The class %s was not found', $class));
    }

    public static function classNotSupported(string $class): self
    {
        return new self(sprintf('The class %s is not supported - Did you forget to set annotation @%s on class %s?', $class, Annotation\Model::class, $class));
    }

    public static function identifierNotFound(ClassMetadata $classMetadata): self
    {
        return new self(sprintf('No identifier field found for class %s', $classMetadata->getClassName()));
    }

    public static function modelNotSupported(string $modelName): self
    {
        return new self(sprintf('No class found for the model "%s"', $modelName));
    }

    public static function fieldNotSupported(ClassMetadata $classMetadata, string $fieldName): self
    {
        return new self(sprintf('The field "%s" is not valid for the model of the class %s', $fieldName, $classMetadata->getClassName()));
    }

    public static function duplicateModel(string $modelName, string $className, ClassMetadata $classMetadata): self
    {
        return new self(sprintf(
            'Cannot redeclare model "%s" in %s because it was already declared in %s',
            $modelName,
            $className,
            $classMetadata->getClassName()
        ));
    }

    public static function duplicateField(string $fieldName, string $propertyName, string $className, PropertyMetadata $property): self
    {
        return new self(sprintf(
            'Cannot redeclare the field "%s" in property %s::$%s because it was already declared in property %s',
            $fieldName,
            $className,
            $propertyName,
            $property->getFullName()
        ));
    }

    public static function duplicateProperty(string $className, PropertyMetadata $property): self
    {
        return new self(sprintf(
            'Cannot redeclare the property "%s" in %s because it was already declared in %s',
            $property->getPropertyName(),
            $className,
            $property->getDeclaringClass()->getClassName()
        ));
    }

    public static function invalidAssociationField(ClassMetadata $classMetadata, ReflectionProperty $property, Field $field): self
    {
        return new self(sprintf(
            'Cannot set annotation @Association to the property %s::$%s because the type of field "%s" is "%s" - Use annotation @Field instead',
            $classMetadata->getClassName(),
            $property->getName(),
            $field->getName(),
            $field->getType()
        ));
    }
}
