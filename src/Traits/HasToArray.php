<?php

namespace Smoggert\SwaggerGenerator\Traits;

use ReflectionClass;

trait HasToArray
{
    public function toArray(): array
    {
        $array = [];

        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            $array[$property->getName()] = $property->getValue($this);
        }

        return $array;
    }
}
