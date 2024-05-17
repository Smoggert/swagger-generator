<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

use Illuminate\Contracts\Support\Arrayable;
use Smoggert\SwaggerGenerator\Exceptions\SwaggerGeneratorException;
use Smoggert\SwaggerGenerator\Traits\HasToArray;

class Schema implements Arrayable
{
    public const ARRAY_TYPE = 'array';
    public const STRING_TYPE = 'string';

    use HasToArray;

    protected ?Schema $items = null;
    protected ?array $enum = null;

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
    public function setEnum(array $enum): void
    {
        if ($this->type !== static::ARRAY_TYPE) {
            throw new SwaggerGeneratorException("Try to set enum-values for wrong type [$this->type]");
        }

        $this->enum = $enum;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
