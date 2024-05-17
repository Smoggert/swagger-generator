<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

class QueryParameter extends Parameter
{
    protected string $in = 'query';
    protected ?Parameter $sub_parameter = null;

    public function getSubParameter(): ?Parameter
    {
        return $this->sub_parameter;
    }

    public function setSubParameter(?Parameter $query_parameter): void
    {
        $this->sub_parameter = $query_parameter;
    }
}
