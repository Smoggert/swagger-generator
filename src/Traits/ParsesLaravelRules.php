<?php

namespace Smoggert\SwaggerGenerator\Traits;

use Illuminate\Validation\Rules\In;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Parameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;

trait ParsesLaravelRules
{
    protected function getEnumeratedValues(Parameter $parameter): ?array
    {
        $rules = $parameter->getArrayType()?->getRules();

        return $rules ? $this->getEnumFromRule($rules) : null;
    }

    protected function getPropertyType(Parameter $parameter): string
    {
        $rules = $parameter->getRules();

        if($parameter->hasSubParameters()) {
            return Schema::OBJECT_TYPE;
        }
        if (in_array('numeric', $rules)) {
            return Schema::NUMBER_TYPE;
        }

        if (in_array('boolean', $rules)) {
            return Schema::BOOLEAN_TYPE;
        }

        if (in_array('array', $rules)) {
            return Schema::ARRAY_TYPE;
        }

        if (in_array('integer', $rules) || in_array('int', $rules)) {
            return Schema::INTEGER_TYPE;
        }

        return Schema::STRING_TYPE;
    }

    protected function isNullable(array $rules): bool
    {
        return in_array('nullable', $rules);
    }

    protected function isRequestParameterRequired(array $rules): bool
    {
        return in_array('required', $rules);
    }

    protected function getEnumFromRule(array $rules): ?array
    {
        foreach ($rules as $rule) {
            if ($rule instanceof In) {
                $rule = (string) $rule;
            }

            $enum_rule = is_string($rule) && str_starts_with($rule, 'in:');

            if (! $enum_rule) {
                continue;
            }

            return explode(',', str_replace(['in:', '"'], '', $rule));
        }

        return null;
    }

    protected function findMinimum(array $rules): ?int
    {
        $regex = '/min:([0-9]*)/';

        if ($matches = preg_grep($regex, $rules)) {
            return preg_replace($regex, '$1', array_values($matches)[0]);
        }

        return null;
    }

    protected function findMaximum(array $rules): ?int
    {
        $regex = '/max:([0-9]*)/';

        if ($matches = preg_grep($regex, $rules)) {
            return preg_replace($regex, '$1', array_values($matches)[0]);
        }

        return null;
    }
}
