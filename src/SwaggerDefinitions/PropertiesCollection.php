<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

use Illuminate\Contracts\Support\Arrayable;

class PropertiesCollection implements Arrayable
{
    protected array $properties = [];

    public function add(string $name, Schema $schema): void
    {
        $this->properties[$name] = $schema;
    }

    public function toArray(): array
    {
        $properties = [];

        foreach ($this->properties as $name => $property) {
            $properties[$name] = $property instanceof Arrayable ? $property->toArray() : $property;
        }

        return $properties;
    }
}
