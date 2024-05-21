<?php

namespace Smoggert\SwaggerGenerator\Parsers;

use Smoggert\SwaggerGenerator\Exceptions\SwaggerGeneratorException;
use Smoggert\SwaggerGenerator\Interfaces\ParsesParameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Parameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;
use Smoggert\SwaggerGenerator\Traits\ParsesLaravelRules;

class DefaultLaravelAttributeParser implements ParsesParameter
{
    use ParsesLaravelRules;

    /**
     * @throws SwaggerGeneratorException
     */
    public function __invoke(Parameter $query_parameter, string $context): Parameter
    {
        $type = $this->getPropertyType($query_parameter->getRules());

        $query_parameter->setRequired($this->isRequestParameterRequired($query_parameter->getRules()));
        $query_parameter->setNullable($this->isNullable($query_parameter->getRules()));

        match ($type) {
            Schema::ARRAY_TYPE => $this->handleArray($query_parameter),
            Schema::BOOLEAN_TYPE => $this->handleBoolean($query_parameter),
            Schema::INTEGER_TYPE => $this->handleInteger($query_parameter),
            default => $this->handleString($query_parameter)
        };

        return $query_parameter;
    }

    /**
     * @throws SwaggerGeneratorException
     */
    protected function handleArray(Parameter $query_parameter): void
    {
        $this->setDefaultPhPArray($query_parameter);

        $schema = new Schema(Schema::ARRAY_TYPE);
        $array_values = new Schema(Schema::STRING_TYPE);

        $array_values->setEnum($this->getEnumeratedValues($query_parameter));

        $schema->setItems(
            $array_values
        );

        $query_parameter->setSchema(
            $schema
        );
    }

    protected function handleString(Parameter $query_parameter): void
    {
        $schema = new Schema(Schema::STRING_TYPE);

        $schema->setMinLength($this->findMinimum($query_parameter->getRules()));
        $schema->setMaxLength($this->findMaximum($query_parameter->getRules()));

        $query_parameter->setSchema($schema);
    }

    /**
     * Due to OpenAPI standard of needing true/false as values for a boolean, we have to change the type into a tiny-int.
     */
    protected function handleBoolean(Parameter $query_parameter): void
    {
        $schema = new Schema(Schema::INTEGER_TYPE);

        $schema->setMinimum(0);
        $schema->setMaximum(1);

        $query_parameter->setSchema($schema);
    }

    /**
     * Due to OpenAPI standard of needing true/false as values for a boolean, we have to change the type into a tiny-int.
     */
    protected function handleInteger(Parameter $query_parameter): void
    {
        $schema = new Schema(Schema::INTEGER_TYPE);

        $schema->setMinimum($this->findMinimum($query_parameter->getRules()));
        $schema->setMaximum($this->findMaximum($query_parameter->getRules()));

        $query_parameter->setSchema($schema);
    }

    protected function setDefaultPhPArray(Parameter $parameter): void
    {
        $parameter->setStyle('form');
        $parameter->setExplode(true);
    }
}
