<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

use Illuminate\Contracts\Support\Arrayable;
use Smoggert\SwaggerGenerator\Traits\HasToArray;

class QueryParameter implements Arrayable
{
    use HasToArray {
        HasToArray::toArray as defaultToArray;
    }

    public function __construct(protected string $parameter_name, protected array $rules)
    {
        $this->name = $parameter_name;
    }

    protected string $name;
    protected string $in = 'query';
    protected ?string $description = null;
    protected ?string $style = null;
    protected ?bool $explode = null;
    protected ?bool $required = null;
    protected ?bool $nullable = null;
    protected ?Schema $schema = null;
    protected ?QueryParameter $sub_parameter = null;

    public function getExplode(): ?bool
    {
        return $this->explode;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getRequired(): ?bool
    {
        return $this->required;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    public function setStyle(?string $style): void
    {
        $this->style = $style;
    }

    public function getSchema(): ?Schema
    {
        return $this->schema;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setExplode(?bool $explode): void
    {
        $this->explode = $explode;
    }

    public function setSchema(?Schema $schema): void
    {
        $this->schema = $schema;

        if ($schema->getType() === Schema::ARRAY_TYPE) {
            $this->setArrayName();
        }
    }

    protected function setArrayName(): void
    {
        $this->name = $this->parameter_name.'[]';
    }

    public function getSubParameter(): ?QueryParameter
    {
        return $this->sub_parameter;
    }

    public function setSubParameter(?QueryParameter $query_parameter): void
    {
        $this->sub_parameter = $query_parameter;
    }

    public function setRequired(?bool $required): void
    {
        $this->required = $required;
    }

    public function getNullable(): ?bool
    {
        return $this->nullable;
    }

    public function setNullable(?bool $nullable): void
    {
        $this->nullable = $nullable;
    }

    public function toArray(): array
    {
        $array = $this->defaultToArray();

        unset(
            $array['sub_parameter'],
            $array['$parameter_name']
        );

        return $array;
    }
}