<?php

namespace Ang3\Component\Odoo\ORM\Schema;

class Field
{
    /**
     * List of Odoo field types constants.
     */
    public const T_BINARY = 'binary';
    public const T_BOOLEAN = 'boolean';
    public const T_CHAR = 'char';
    public const T_DATE = 'date';
    public const T_DATETIME = 'datetime';
    public const T_FLOAT = 'float';
    public const T_HTML = 'html';
    public const T_INTEGER = 'integer';
    public const T_MONETARY = 'monetary';
    public const T_SELECTION = 'selection';
    public const T_TEXT = 'text';
    public const T_MANY_TO_ONE = 'many2one';
    public const T_MANY_TO_MANY = 'many2many';
    public const T_ONE_TO_MANY = 'one2many';

    /**
     * Date formats.
     */
    public const DATE_FORMAT = 'Y-m-d';
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';

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
    private $type;

    /**
     * @var bool
     */
    private $required = false;

    /**
     * @var bool
     */
    private $readOnly = false;

    /**
     * @var string|null
     */
    private $displayName;

    /**
     * @var int|null
     */
    private $size;

    /**
     * @var bool
     */
    private $selectable = false;

    /**
     * @var Selection|null
     */
    private $selection;

    /**
     * @var string|null
     */
    private $targetModel;

    /**
     * @var string|null
     */
    private $mappedBy;

    private function __construct(int $id, string $name, string $type, bool $required = false, bool $readOnly = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->required = $required;
        $this->readOnly = $readOnly;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function getDisplayName(): string
    {
        return $this->displayName ?: $this->name;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function isSelectable(): bool
    {
        return $this->selectable;
    }

    public function getSelection(): ?Selection
    {
        return $this->selection;
    }

    public function getTargetModel(): ?string
    {
        return $this->targetModel;
    }

    public function getMappedBy(): ?string
    {
        return $this->mappedBy;
    }

    public function isIdentifier(): bool
    {
        return 'id' === $this->name;
    }

    public function isBoolean(): bool
    {
        return Field::T_BOOLEAN === $this->type;
    }

    public function isDate(): bool
    {
        return in_array($this->type, [self::T_DATE, self::T_DATETIME], true);
    }

    public function getDateFormat(): string
    {
        return Field::T_DATETIME === $this->type ? self::DATETIME_FORMAT : self::DATE_FORMAT;
    }

    public function isAssociation(): bool
    {
        return in_array($this->type, [
            self::T_MANY_TO_ONE,
            self::T_MANY_TO_MANY,
            self::T_ONE_TO_MANY,
        ], true);
    }

    public function isSingleAssociation(): bool
    {
        return self::T_MANY_TO_ONE === $this->type;
    }

    public function isMultipleAssociation(): bool
    {
        return in_array($this->type, [
            self::T_MANY_TO_MANY,
            self::T_ONE_TO_MANY,
        ], true);
    }
}
