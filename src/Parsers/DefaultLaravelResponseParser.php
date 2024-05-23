<?php

namespace Smoggert\SwaggerGenerator\Parsers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use ReflectionClass;
use Smoggert\SwaggerGenerator\Interfaces\ParsesResponse;
use Smoggert\SwaggerGenerator\Models\FakeModelForSwagger as Model;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\PropertiesCollection;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;

class DefaultLaravelResponseParser implements ParsesResponse
{
    public function __invoke(?Schema $schema, ReflectionClass $response): ?Schema
    {
        if (!$response->getName()) {
            return null;
        }

        if (!$this->isJsonResource($response)) {
            return null;
        }

        $collects = $this->isResourceCollection($response) ? new Collection([new Model()]) : new Model();

        $parameters = $response->newInstance($collects)->toArray(request());

        $schema = new Schema(Schema::OBJECT_TYPE);

        $schema->setProperties(
            $this->getProperties($parameters)
        );

        return $schema;
    }

    protected function getProperties(array $parameters): ?PropertiesCollection
    {
        if(empty($parameters)) {
            return null;
        }

        $properties = new PropertiesCollection();

        foreach ($parameters as $parameter_name => $parameter_info) {
            $properties->add($parameter_name, new Schema(Schema::STRING_TYPE));
        }

        return $properties;
    }

    protected function isJsonResource(ReflectionClass $response): bool
    {
        return $response->isSubclassOf(JsonResource::class);
    }

    protected function isResourceCollection(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(ResourceCollection::class);
    }

    protected function trimResourcePath(string $requestName): string
    {
        //TODO :regex replace
        return $this->replaceSlashes(str_replace('App\\Http\\Resources\\', '', $requestName));
    }

    protected function replaceSlashes(string $requestName): string
    {
        return str_replace('\\', '', $requestName);
    }
}