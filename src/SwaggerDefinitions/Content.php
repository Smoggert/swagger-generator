<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

use Illuminate\Contracts\Support\Arrayable;

class Content implements Arrayable
{
    public function __construct(protected string $schema_reference, protected string $content_type = 'application/json')
    {
    }

    public function toArray(): array
    {
        return [
            $this->content_type => [
                'schema' => [
                    '$ref' => $this->schema_reference,
                ],
            ],
        ];
    }
}
