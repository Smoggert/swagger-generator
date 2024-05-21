<?php

namespace Smoggert\SwaggerGenerator\Parsers;

use Smoggert\SwaggerGenerator\Exceptions\SwaggerGeneratorException;
use Smoggert\SwaggerGenerator\Interfaces\ParsesParameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Parameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\PropertiesCollection;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;
use Smoggert\SwaggerGenerator\Traits\ParsesLaravelRules;

class DefaultLaravelAttributeParser implements ParsesParameter
{
    use ParsesLaravelRules;

    /**
     * @throws SwaggerGeneratorException
     */
    public function __invoke(Parameter $parameter, string $context): Parameter
    {
        $type = $this->getPropertyType($parameter);

        $parameter->setRequired($this->isRequestParameterRequired($parameter->getRules()));
        $parameter->setNullable($this->isNullable($parameter->getRules()));

        match ($type) {
            Schema::ARRAY_TYPE => $this->handleArray($parameter),
            Schema::BOOLEAN_TYPE => $this->handleBoolean($parameter),
            Schema::INTEGER_TYPE => $this->handleInteger($parameter),
            Schema::OBJECT_TYPE => $this->handleObject($parameter),
            default => $this->handleString($parameter)
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

        $schema->setMinLength($this->findMinimum($parameter->getRules()));
        $schema->setMaxLength($this->findMaximum($parameter->getRules()));

        $parameter->setSchema($schema);
    }

    /**
     * Due to OpenAPI standard of needing true/false as values for a boolean, we have to change the type into a tiny-int.
     */
    protected function handleBoolean(Parameter $parameter): void
    {
        $schema = new Schema(Schema::INTEGER_TYPE);

        $schema->setMinimum(0);
        $schema->setMaximum(1);

        $parameter->setSchema($schema);
    }

    /**
     * Due to OpenAPI standard of needing true/false as values for a boolean, we have to change the type into a tiny-int.
     */
    protected function handleInteger(Parameter $parameter): void
    {
        $schema = new Schema(Schema::INTEGER_TYPE);

        $schema->setMinimum($this->findMinimum($parameter->getRules()));
        $schema->setMaximum($this->findMaximum($parameter->getRules()));

        $parameter->setSchema($schema);
    }

    protected function handleObject(Parameter $parameter): void
    {
        $schema = new Schema(Schema::OBJECT_TYPE);

        $properties = new PropertiesCollection();

        foreach ($parameter->getSubParameters() as $sub_parameter) {
            $properties->add($sub_parameter->getName(), $sub_parameter->getSchema());
        }

        $schema->setProperties($properties);

        $parameter->setSchema($schema);
    }

    protected function setDefaultPhPArray(Parameter $parameter): void
    {
        $parameter->setStyle('form');
        $parameter->setExplode(true);
    }
}
