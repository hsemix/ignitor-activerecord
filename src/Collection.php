<?php

declare(strict_types=1);

/**
 * This file is part of the Igniter framework.
 *
 * @package    Igniter
 * @category   ActiveRecord
 * @author     Your Name
 * @license    https://opensource.org/licenses/MIT  MIT License
 * @link       https://igniter.com
 */
namespace Igniter\ActiveRecord;

class Collection implements \ArrayAccess, \IteratorAggregate, \Countable
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function all(): array
    {
        return $this->items;
    }
    public function first()
    {
        return reset($this->items);
    }
    public function last()
    {
        return end($this->items);
    }
    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->items[$offset] ?? null;
    }
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }
    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }
    public function toArray(): array
    {
        return $this->items;
    }

    public function __toString(): string
    {
        return json_encode($this->items);
    }
}