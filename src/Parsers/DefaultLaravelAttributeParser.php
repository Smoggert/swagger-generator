<?php

namespace Smoggert\SwaggerGenerator\Parsers;

use Smoggert\SwaggerGenerator\Exceptions\SwaggerGeneratorException;
use Smoggert\SwaggerGenerator\Interfaces\ParsesParameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\QueryParameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;
use Smoggert\SwaggerGenerator\Traits\ParsesLaravelRules;

class DefaultLaravelAttributeParser implements ParsesParameter
{
    use ParsesLaravelRules;

    /**
     * @throws SwaggerGeneratorException
     */
    public function __invoke(QueryParameter $query_parameter, string $context): QueryParameter
    {
        $type = $this->getPropertyType($query_parameter->getRules());

        $query_parameter->setRequired($this->isRequestParameterRequired($query_parameter->getRules()));
        $query_parameter->setNullable($this->isNullable($query_parameter->getRules()));

        match ($type) {
            Schema::ARRAY_TYPE => $this->handleArray($query_parameter),
            Schema::STRING_TYPE => $this->handleString($query_parameter)
        };

        return $query_parameter;
    }

    /**
     * @throws SwaggerGeneratorException
     */
    protected function handleArray(QueryParameter $query_parameter): void
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
}
