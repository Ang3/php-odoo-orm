<?php

namespace Ang3\Component\Odoo\ORM\Internal;

trait ReflectorAwareTrait
{
    /**
     * @var Reflector|null
     */
    protected static $reflector;

    public static function getReflector(): Reflector
    {
        if (!self::$reflector) {
            self::$reflector = new Reflector();
        }

        return self::$reflector;
    }
}
