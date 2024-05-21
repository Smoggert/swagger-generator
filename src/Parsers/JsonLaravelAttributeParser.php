<?php

namespace Smoggert\SwaggerGenerator\Parsers;

use Smoggert\SwaggerGenerator\Exceptions\SwaggerGeneratorException;
use Smoggert\SwaggerGenerator\Interfaces\ParsesParameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\JsonParameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Parameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;
use Smoggert\SwaggerGenerator\Traits\ParsesLaravelRules;

class JsonLaravelAttributeParser implements ParsesParameter
{
    use ParsesLaravelRules;

    /**
     * @throws SwaggerGeneratorException
     */
    public function __invoke(Parameter $parameter, string $context): Parameter
    {
        if (! $parameter instanceof JsonParameter) {
            return $parameter;
        }

        $rules = $parameter->getRules();

        $parameter->setNullable($this->isNullable($rules));

        $type = $this->getPropertyType($parameter->getRules());

        $parameter->setRequired($this->isRequestParameterRequired($parameter->getRules()));
        $parameter->setNullable($this->isNullable($parameter->getRules()));

        match ($type) {
            Schema::ARRAY_TYPE => $this->handleArray($parameter),
            Schema::STRING_TYPE => $this->handleString($parameter),
            Schema::OBJECT_TYPE => $this->handObject($parameter)
        };

        return $parameter;

        if (! $this->hasObjects($rules)) {
            if ($this->hasSubParameters($rules)) {
                $property = [
                    'type' => 'object',
                    'required' => $this->getRequiredParameters($property_rule),
                    'properties' => $this->getProperties($property_rule),
                ];
            } else {
                $type = $this->getPropertyType($property_rule);
                $property = [
                    'type' => $type,
                ];

                if ($type === 'string' && ($enum = $this->getEnumFromRules($property_rule))) {
                    $property['enum'] = $enum;
                }
            }
        } else {
            $property = [
                'type' => 'array',
            ];

            $this->addProperty('items', $property_rule['*'], $property);
        }

        $component[$property_name] = $property;
    }

    /**
     * @throws SwaggerGeneratorException
     */
    protected function handleArray(Parameter $parameter): void
    {
        if ($parameter->hasSubParameters()) {
            $schema = new Schema(Schema::OBJECT_TYPE);
            $array_values = new Schema(Schema::STRING_TYPE);

            $array_values->setEnum($this->getEnumeratedValues($parameter));
            foreach ($parameter->getSubParameters() as &$sub_parameter) {
                $schema->setItems(
                    $array_values
                );
            }

            $parameter->setSchema(
                $schema
            );
        }

        $this->setDefaultPhPArray($parameter);

        $schema = new Schema(Schema::ARRAY_TYPE);
        $array_values = new Schema(Schema::STRING_TYPE);

        $array_values->setEnum($this->getEnumeratedValues($parameter));

        $schema->setItems(
            $array_values
        );

        $parameter->setSchema(
            $schema
        );
    }

    protected function hasSubParameters(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function hasObjects(array $array): bool
    {
        return isset($array['*']);
    }

    protected function handleString(Parameter $parameter): void
    {
        $schema = new Schema(Schema::STRING_TYPE);

        $parameter->setSchema($schema);
    }

    protected function setDefaultPhPArray(Parameter $parameter): void
    {
        $parameter->setStyle('form');
        $parameter->setExplode(true);
    }

    protected function getEnumeratedValues(Parameter $parameter): ?array
    {
        $rules = $parameter->getArrayType()?->getRules();

        return $rules ? $this->getEnumFromRules($rules) : null;
    }
}
