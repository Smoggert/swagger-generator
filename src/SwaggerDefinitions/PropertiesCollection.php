<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

use Illuminate\Contracts\Support\Arrayable;

class PropertiesCollection implements Arrayable
{
    protected array $properties = [];
    protected array $required = [];

    public function add(string $name, Schema $schema, bool $required = false): void
    {
        $this->properties[$name] = $schema;

        if($required) {
            $this->required[] = $name;
        }
    }

    public function toArray(): array
    {
        $properties = [];

        foreach ($this->properties as $name => $property) {
            $properties[$name] = $property instanceof Arrayable ? $property->toArray() : $property;
        }

        return $properties;
    }

    public function getRequired(): array
    {
        return $this->required;
    }
}
