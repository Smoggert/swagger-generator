<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

use Illuminate\Contracts\Support\Arrayable;
use Smoggert\SwaggerGenerator\Traits\HasToArray;

class Parameter implements Arrayable
{
    public const IN_QUERY = 'query';
    public const IN_BODY = 'body';
    public const IN_URL = 'query';

    use HasToArray {
        HasToArray::toArray as defaultToArray;
    }

    public function __construct(protected string $parameter_name, protected array $rules, protected string $in)
    {
        $this->name = $parameter_name;
    }

    protected string $name;
    protected ?string $description = null;
    protected ?string $style = null;
    protected ?bool $explode = null;
    protected null|bool|array $required = null;
    protected ?bool $nullable = null;
    protected ?Schema $schema = null;
    protected ?Parameter $array_type = null;

    /**
     * @var Parameter[] $sub_parameters
     */
    protected array $sub_parameters = [];

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
        if($this->in === self::IN_QUERY){
            $this->name = $this->parameter_name.'[]';
        }
    }

    public function getArrayType(): ?Parameter
    {
        return $this->array_type;
    }

    public function setArrayType(?Parameter $query_parameter): void
    {
        $this->array_type = $query_parameter;
    }

    public function setRequired(bool|array|null $required): void
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
            $array['array_type'],
            $array['parameter_name'],
            $array['rules'],
            $array['sub_parameters']
        );

        return $array;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getSubParameters(): array
    {
        return $this->sub_parameters;
    }

    public function setSubParameters(array $sub_parameters): void
    {
        $this->sub_parameters = $sub_parameters;
    }

    public function addSubParameter(Parameter $parameter): void
    {
        $this->sub_parameters[] = $parameter;
    }

    public function hasSubParameters(): bool
    {
        return ! count($this->sub_parameters);
    }
}
