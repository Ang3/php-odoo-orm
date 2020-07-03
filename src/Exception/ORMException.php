<?php

namespace Ang3\Component\Odoo\ORM\Exception;

use Ang3\Component\Odoo\ORM\Internal\ReflectorAwareTrait;

class ORMException extends RuntimeException
{
    use ReflectorAwareTrait;
}
