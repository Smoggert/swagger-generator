<?php

namespace Smoggert\SwaggerGenerator\Traits;

use Illuminate\Contracts\Support\Arrayable;
use ReflectionClass;

trait HasToArray
{
    public function toArray(): array
    {
        $array = [];

        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties();
        foreach ($properties as $property)
        {
            $value = $property->getValue($this);
            $array[$property->getName()] = $value instanceof Arrayable ? $value->toArray() : $value;
        }

        return $array;
    }
}
