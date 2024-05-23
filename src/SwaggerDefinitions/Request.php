<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

use Illuminate\Contracts\Support\Arrayable;
use Smoggert\SwaggerGenerator\Traits\HasToArray;

class Request implements Arrayable
{
    use HasToArray;

    public function __construct(protected string|null $description, protected Content $content)
    {
    }
}
