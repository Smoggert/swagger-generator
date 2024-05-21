<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

use Illuminate\Contracts\Support\Arrayable;
use Smoggert\SwaggerGenerator\Exceptions\SwaggerGeneratorException;
use Smoggert\SwaggerGenerator\Traits\HasToArray;

class Schema implements Arrayable
{
    public const ARRAY_TYPE = 'array';
    public const STRING_TYPE = 'string';
    public const OBJECT_TYPE = 'object';
    public const BOOLEAN_TYPE = 'boolean';
    public const INTEGER_TYPE = 'integer';

    use HasToArray;

    protected ?Schema $items = null;
    protected ?array $enum = null;
    protected ?int $minimum = null;
    protected ?int $maximum = null;
    protected ?int $minLength = null;
    protected ?int $maxLength = null;

    public function __construct(protected string $type)
    {
    }

    public function getEnum(): ?array
    {
        return $this->enum;
    }

    public function getItems(): ?Schema
    {
        return $this->items;
    }

    /**
     * @throws SwaggerGeneratorException
     */
    public function setItems(?Schema $items): void
    {
        if ($this->type !== static::ARRAY_TYPE) {
            throw new SwaggerGeneratorException("Try to set array item schema for wrong type [$this->type]");
        }

        $this->items = $items;
    }

    /**
     * @throws SwaggerGeneratorException
     */
    public function setEnum(?array $enum): void
    {
        if ($this->type !== static::STRING_TYPE) {
            throw new SwaggerGeneratorException("Try to set enum-values for wrong type [$this->type]");
        }

        $this->enum = $enum;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMinimum(): ?int
    {
        return $this->minimum;
    }

    public function setMinimum(?int $minimum): void
    {
        $this->minimum = $minimum;
    }

    public function getMaximum(): ?int
    {
        return $this->maximum;
    }

    public function setMaximum(?int $maximum): void
    {
        $this->maximum = $maximum;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function setMinLength(?int $minLength): void
    {
        $this->minLength = $minLength;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function setMaxLength(?int $maxLength): void
    {
        $this->maxLength = $maxLength;
    }
}
