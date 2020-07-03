<?php

namespace Ang3\Component\Odoo\ORM\Exception;

class RecordNotFoundException extends ORMException
{
    public static function create(string $className, int $id): self
    {
        return new self(sprintf('The record of type %s with ID #%d was not found', $className, $id));
    }
}
