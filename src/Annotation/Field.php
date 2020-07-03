<?php

namespace Ang3\Component\Odoo\ORM\Annotation;

/**
 * @Annotation
 * @Target({"METHOD","PROPERTY"})
 */
class Field
{
    /**
     * @var string
     *
     * @Required
     */
    public $name;

    /**
     * @var array
     */
    public $options = [];

    public function getLength(): ?string
    {
        return $this->options['length'] ?? null;
    }

    public function isIdentifier(): bool
    {
        return 'id' === $this->name;
    }
}
