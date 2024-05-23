<?php

namespace Smoggert\SwaggerGenerator\SwaggerDefinitions;

use Illuminate\Contracts\Support\Arrayable;

class Content implements Arrayable
{
    protected array $schemas = [];

    public function setJsonSchema(string $json_schema): void
    {
        $this->schemas[ 'application/json'] = $json_schema;
    }

    public function seFormUrlEncodedSchema(string $form_url_encoded_schema): void
    {
        $this->schemas['application/x-www-form-urlencoded'] = $form_url_encoded_schema;
    }

    public function toArray(): array
    {
        return $this->schemas;
    }
}
