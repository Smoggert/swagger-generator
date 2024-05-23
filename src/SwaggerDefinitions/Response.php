<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

use Illuminate\Contracts\Support\Arrayable;
use Smoggert\SwaggerGenerator\Traits\HasToArray;

class Response implements Arrayable
{
    use HasToArray;
    public function __construct(protected string $description, protected Content $content)
    {
    }
}
