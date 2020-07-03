<?php

namespace Ang3\Component\Odoo\ORM\Internal;

use Throwable;

class ReflectionException extends \RuntimeException
{
    /**
     * @param mixed $subject
     */
    public static function classReflectionFailed($subject, Throwable $previous = null): self
    {
        if (is_scalar($subject)) {
            $message = sprintf('Failed to get the reflection of class "%s"', (string) $subject);
        } else {
            $message = sprintf('Failed to get class reflection from value of type %s', gettype($subject));
        }

        return new self($message, 0, $previous);
    }

    public static function propertyReflectionFailed(string $className, string $propertyName, Throwable $previous = null): self
    {
        return new self(sprintf('Failed to get the reflection of property %s::%s', $className, $propertyName), 0, $previous);
    }
}
