<?php

namespace Smoggert\SwaggerGenerator\Traits;

use Illuminate\Validation\Rules\In;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Parameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;

trait ParsesLaravelRules
{
    protected function getEnumeratedValues(Parameter $parameter): ?array
    {
        $rules = $parameter->getSubParameter()?->getRules();

        return $rules ? $this->getEnumFromRule($rules) : null;
    }

    protected function getPropertyType(array $rules): string
    {
        if (in_array('numeric', $rules)) {
            return 'number';
        }

        if (in_array('boolean', $rules)) {
            return 'boolean';
        }

        if (in_array(Schema::ARRAY_TYPE, $rules)) {
            return 'array';
        }

        if (in_array('integer', $rules) || in_array('int', $rules)) {
            return 'integer';
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
