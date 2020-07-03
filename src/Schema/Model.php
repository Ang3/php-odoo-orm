<?php

namespace Ang3\Component\Odoo\ORM\Schema;

class Model
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $displayName;

    /**
     * @var bool
     */
    private $transient;

    /**
     * @var Field[]
     */
    private $fields = [];

    public function __construct(int $id, string $name, string $displayName = null, bool $transient = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->displayName = $displayName;
        $this->transient = $transient;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDisplayName(): string
    {
        return $this->displayName ?: $this->name;
    }

    public function isTransient(): bool
    {
        return $this->transient;
    }

    /**
     * @throws SchemaException when the field was not found
     */
    public function getField(string $fieldName): Field
    {
        if (!$this->hasField($fieldName)) {
            throw SchemaException::fieldNotFound($fieldName, $this);
        }

        return $this->fields[$fieldName];
    }

    public function hasField(string $fieldName): bool
    {
        return array_key_exists($fieldName, $this->fields);
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string>
     */
    public function getFieldNames(): array
    {
        return array_keys($this->fields);
    }
}
