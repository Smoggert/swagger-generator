<?php

namespace Smoggert\SwaggerGenerator\Interfaces;

use Smoggert\SwaggerGenerator\SwaggerDefinitions\Parameter;

interface ParsesParameter
{
    public function __invoke(Parameter $query_parameter, string $context): Parameter;
}
