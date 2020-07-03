<?php

namespace Ang3\Component\Odoo\ORM\Model;

use Ang3\Component\Odoo\ORM\Internal\ReflectorAwareTrait;
use ArrayIterator;

/**
 * @phpstan-template TKey
 * @psalm-template TKey of array-key
 * @psalm-template T
 * @template-implements \IteratorAggregate<TKey, T>
 */
class Collection implements \IteratorAggregate
{
    use ReflectorAwareTrait;

    /**
     * @var int[]
     */
    private $storedIds = [];

    /**
     * @var array
     */
    private $elements = [];

    public function __construct(array $storedIds = [])
    {
        $this->storedIds = array_filter($storedIds);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->elements);
    }

    /**
     * @param mixed $element
     *
     * @psalm-param T $element
     */
    public function add($element): self
    {
        if (!$this->contains($element)) {
            $this->elements[] = $element;
        }

        return $this;
    }

    /**
     * @param mixed $element
     *
     * @psalm-param T $element
     */
    public function remove($element): self
    {
        if ($key = $this->search($element)) {
            unset($this->elements[$key]);
        }

        return $this;
    }

    /**
     * @param mixed $element
     *
     * @psalm-param T $element
     */
    public function contains($element): bool
    {
        return null !== $this->search($element);
    }

    /**
     * @psalm-return T|null
     */
    public function first()
    {
        return reset($this->elements);
    }

    /**
     * @psalm-return T|null
     */
    public function last()
    {
        return end($this->elements);
    }

    /**
     * @internal
     *
     * @param mixed $element
     *
     * @psalm-param T $element
     */
    private function search($element): ?int
    {
        foreach ($this->elements as $key => $existentRecord) {
            if ($element === $existentRecord) {
                return $key;
            }
        }

        return null;
    }

    public function clear(): self
    {
        $this->elements = [];

        return $this;
    }

    public function count(): int
    {
        return count($this->elements);
    }

    public function getStoredIds(): array
    {
        return $this->storedIds;
    }

    public function getElements(): array
    {
        return $this->elements;
    }
}
