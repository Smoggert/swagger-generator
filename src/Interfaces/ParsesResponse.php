<?php

namespace Smoggert\SwaggerGenerator\Interfaces;

use ReflectionClass;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;

interface ParsesResponse
{
    public function __invoke(?Schema $schema, ReflectionClass $response): ?Schema;
}
