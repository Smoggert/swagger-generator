<?php

namespace Smoggert\SwaggerGenerator\Traits;

use Illuminate\Contracts\Support\Arrayable;

trait HasToArray
{
    public function toArray(): array
    {
        $array = (array) $this;

        foreach ($array as &$field) {
            if($field instanceof Arrayable) {
                $field = $field->toArray();
            }
        }

        return $array;
    }
}