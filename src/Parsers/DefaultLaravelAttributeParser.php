<?php

namespace Smoggert\SwaggerGenerator\Parsers;

use Illuminate\Validation\Rules\In;
use Smoggert\SwaggerGenerator\Exceptions\SwaggerGeneratorException;
use Smoggert\SwaggerGenerator\Interfaces\ParsesParameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\QueryParameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;

/**
 *
 */
class DefaultLaravelAttributeParser implements ParsesParameter
{
    /**
     * @throws SwaggerGeneratorException
     */
    public function __invoke(QueryParameter $query_parameter, ParsesParameter $parses_parameter = null): QueryParameter
    {
        $type = $this->getPropertyType($query_parameter->getRules());

        $query_parameter->setRequired($this->isRequestParameterRequired($query_parameter->getRules()));
        $query_parameter->setNullable($this->isNullable($query_parameter->getRules()));

        match ($type) {
            Schema::ARRAY_TYPE => $this->handleArray($query_parameter),
            Schema::STRING_TYPE => $this->handleString($query_parameter)
        };

        return $parses_parameter ? $parses_parameter($query_parameter) : $query_parameter;
    }

    /**
     * @throws SwaggerGeneratorException
     */
    protected function handleArray(QueryParameter $query_parameter): void
    {
        $this->setDefaultPhPArray($query_parameter);

        $schema = new Schema(Schema::ARRAY_TYPE);
        $schema->setItems(
            new Schema(Schema::STRING_TYPE)
        );

        $schema->setEnum($this->getEnumeratedValues($query_parameter));

        $query_parameter->setSchema(
            $schema
        );
    }

    protected function handleString(QueryParameter $query_parameter): void
    {
        $schema = new Schema(Schema::STRING_TYPE);

        $query_parameter->setSchema($schema);
    }

    protected function setDefaultPhPArray(QueryParameter $parameter): void
    {
        $parameter->setStyle('form');
        $parameter->setExplode(true);
    }

    protected function getEnumeratedValues(QueryParameter $parameter): ?array
    {
        foreach ($parameter->getSubParameters() as $sub_parameter){
            $enum = $this->getEnumFromRule($sub_parameter->getRules());

            if(count($enum)) {
                return $enum;
            }
        }

        return null;
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

    protected function isRequestParameterRequired($parameterRule): bool
    {
        return is_string($parameterRule) && str_contains($parameterRule, 'required');
    }

    protected function findSubProperties(string $property_name, array $other_properties): array
    {
        $subs = [];
        if (key_exists("{$property_name}.*", $other_properties)) {
            $subs = $this->getEnumFromRule($other_properties["{$property_name}.*"]);
        }

        return $subs;
    }

    protected function getEnumFromRule(array $rules): array
    {
        foreach ($rules as $rule) {
            if (is_object($rule) && get_class($rule) === In::class) {
                $rule = (string) $rule;
            }

            $enum_rule = is_string($rule) && str_starts_with($rule,'in:');

            if(! $enum_rule) {
                continue;
            }

            return explode(',', str_replace(['in:', '"'], '', $rule));
        }

        return [];
    }
}