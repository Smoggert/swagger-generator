<?php

namespace Smoggert\SwaggerGenerator\Parsers;

use Smoggert\SwaggerGenerator\Exceptions\SwaggerGeneratorException;
use Smoggert\SwaggerGenerator\Interfaces\ParsesParameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Parameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\QueryParameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;
use Smoggert\SwaggerGenerator\Traits\ParsesLaravelRules;

class QueryLaravelAttributeParser implements ParsesParameter
{
    use ParsesLaravelRules;

    /**
     * @throws SwaggerGeneratorException
     */
    public function __invoke(Parameter $parameter, string $context): Parameter
    {
        if (! $parameter instanceof QueryParameter) {
            return $parameter;
        }

        $type = $this->getPropertyType($parameter->getRules());

        $parameter->setRequired($this->isRequestParameterRequired($parameter->getRules()));
        $parameter->setNullable($this->isNullable($parameter->getRules()));

        match ($type) {
            Schema::ARRAY_TYPE => $this->handleArray($parameter),
            Schema::STRING_TYPE => $this->handleString($parameter)
        };

        return $parameter;
    }

    /**
     * @throws SwaggerGeneratorException
     */
    protected function handleArray(Parameter $parameter): void
    {
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
