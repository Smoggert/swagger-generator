<?php

namespace Smoggert\SwaggerGenerator\Interfaces;

use Smoggert\SwaggerGenerator\SwaggerDefinitions\QueryParameter;

interface ParsesParameter
{
    public function __invoke(QueryParameter $query_parameter): QueryParameter;
}