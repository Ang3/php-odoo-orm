<?php

namespace Ang3\Component\Odoo\ORM\Internal;

use ProxyManager\Proxy\GhostObjectInterface;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

class Reflector
{
    /**
     * @throws ReflectionException on reflection failure
     */
    public function copyObjectProperties(object $sourceObject, object $targetObject): void
    {
        $reflectionClassSource = $this->getClass($sourceObject);
        $reflectionClassTarget = $this->getClass($targetObject);

        foreach ($reflectionClassSource->getProperties() as $property) {
            $property->setAccessible(true);
            $propertyValue = $property->getValue($sourceObject);

            if ($reflectionClassTarget->hasProperty($property->getName())) {
                $targetProperty = $this->getProperty($reflectionClassTarget, $property->getName());
                $targetProperty->setAccessible(true);
                $targetProperty->setValue($targetObject, $propertyValue);
            }
        }
    }

    /**
     * @param mixed $subject
     *
     * @throws ReflectionException on reflection failure
     */
    public function getProperty($subject, string $propertyName): ReflectionProperty
    {
        $class = $this->getClass($subject);

        try {
            $property = $class->getProperty($propertyName);
            $property->setAccessible(true);
        } catch (Throwable $e) {
            throw new ReflectionException(sprintf('Failed to get the reflection of property %s::$%s', $class->getName(), $propertyName), 0, $e);
        }

        return $property;
    }

    /**
     * @param mixed $subject
     *
     * @throws ReflectionException on reflection failure
     */
    public function getClass($subject): ReflectionClass
    {
        if ($subject instanceof ReflectionClass) {
            return $subject;
        }

        try {
            $reflectionClass = new ReflectionClass($subject);

            if ($subject instanceof GhostObjectInterface) {
                return $reflectionClass->getParentClass() ?: $reflectionClass;
            }
        } catch (Throwable $e) {
            throw new ReflectionException(sprintf('Failed to get class reflection from value of type %s', gettype($subject)), 0, $e);
        }

        return $reflectionClass;
    }
}
