<?php

namespace Smoggert\SwaggerGenerator\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\In;
use Smoggert\SwaggerGenerator\Models\FakeModelForSwagger as Model;
use Symfony\Component\Console\Output\OutputInterface;

class SwaggerGeneratorService
{
    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var Illuminate\Routing\Router;
     */
    protected $router;

    /**
     * @var Illuminate\Routing\RouteCollection;
     */
    protected $routes;

    /**
     * @var Illuminate\Support\Collection;
     */
    protected $filtered_routes;

    public const YAMLSPACE = '  ';
    public const YAMLPARAMETER = '- ';
    public const YAMLARRAYKEYINDICATOR = ': ';

    protected $tags = [];
    protected $schemas = [];
    protected $security_schemes = [];
    protected $paths = [];
    protected $default_responses;
    protected $format = 'yaml';
    protected $auth_middleware;

    protected $supported_formats = [
        'json',
        'yaml',
    ];

    protected $output_file_path;

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->routes = $router->getRoutes();
        $this->default_responses = Config::get('swagger_gen.default_responses');
        $this->output_file_path = Config::get('swagger_gen.output');
        $this->auth_middleware = Config::get('swagger_gen.middleware');
    }

    public function generate(OutputInterface $output, string $format = 'json'): int
    {
        if (! in_array($format, $this->supported_formats)) {
            $formats = implode(', ', $this->supported_formats);
            throw new \Exception("Unsupported format {$format} try: {$formats}");
        }
        $this->format = $format;
        $this->output = $output;
        $swagger_file = [];

        $this->addVersion($swagger_file);
        $this->addInfo($swagger_file);
        $this->addServers($swagger_file);
        $this->addAuthentication($swagger_file);

        $this->filterRoutes();

        $this->addTags($swagger_file);
        $this->addPaths($swagger_file);
        $this->addComponents($swagger_file);
        $this->printSwaggerDocsUsingFormat($swagger_file);

        return 0;
    }

    protected function addTags(&$swagger_file): void
    {
        $swagger_file['tags'] = [];

        foreach ($this->tags as $tag) {
            $swagger_file['tags'][] = [
                'name' => $tag,
            ];
        }
    }

    protected function filterRoutes(): void
    {

        $allowed_routes = Config::get('swagger_gen.allowed');
        $excluded_routes = Config::get('swagger_gen.excluded');
        $filtered_routes = [];
        $all_tags = new Collection();

        foreach ($excluded_routes as $excluded_route) {
            $non_excluded_routes = new RouteCollection();

            $escaped_excluded_route = str_replace('/', '\/', $excluded_route);
            $excluded = str_replace('{id}', "[a-zA-Z0-9-:\}\{]+", $escaped_excluded_route);
            foreach ($this->routes as $route) {
                if (! preg_match('/'.$excluded.'/s', $route->uri)) {
                    $non_excluded_routes->add($route);
                }
            }
            $this->routes = $non_excluded_routes;
        }

        foreach ($allowed_routes as $allowed_route) {
            $stripped_allowed_route = str_replace('{$tag}', '([a-zA-Z0-9-]+)', $allowed_route);
            $escaped_allowed_route = str_replace('/', '\/', $stripped_allowed_route);
            $twice_stripped_allowed_route = str_replace('{id}', "[a-zA-Z0-9-:\}\{]+", $escaped_allowed_route);

            foreach ($this->routes as $route) {
                $tags = [];
                if (preg_match('/'.$twice_stripped_allowed_route.'/s', $route->uri, $tags)) {
                    array_shift($tags);
                    $object_id = spl_object_id($route);
                    if (isset($filtered_routes[$object_id])) {
                        $filtered_routes[$object_id]['tags'] = collect(array_merge($filtered_routes[$object_id]['tags'], $tags))->unique()->values()->toArray();
                    } else {
                        $filtered_routes[$object_id] = [
                            'route' => $route,
                            'tags' => $tags,
                        ];
                    }
                    $all_tags->push(...$tags);
                }
            }
        }

        $this->filtered_routes = $filtered_routes;
        $this->tags = $all_tags->unique()->values()->toArray();
    }

    public function addAuthentication(&$swagger_docs)
    {
        foreach ($this->auth_middleware as $key => $middleware) {
            $this->addMiddleware($key, $middleware);
        }
    }

    protected function addMiddleware(string $key, array $middleware): void
    {
        $this->security_schemes[$key] = $middleware['schema'];
    }

    protected function addPaths(&$swagger_docs)
    {
        $paths = [];

        foreach ($this->filtered_routes as $route) {
            $this->addPath($paths, $route);
        }
        $swagger_docs['paths'] = $paths;
    }

    public function getRouteName(Route $route): string
    {
        if (substr($route->uri, 0, 1) !== '/') {
            return '/'.$route->uri;
        }

        return $route->uri;
    }

    protected function printSwaggerDocsUsingFormat(array $swagger_docs)
    {
        if ($this->format === 'yaml') {
            $this->printYaml($swagger_docs);
        } elseif ($this->format === 'json') {
            $this->printJson($swagger_docs);
        }
    }

    protected function printJson(array $swagger_docs)
    {
        $stringify = json_encode($swagger_docs, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
        $this->output->write($stringify, true);

        if($this->output_file_path) {
            File::put($this->output_file_path,$stringify);
        }
    }

    // PECL: Yaml isn't a default php-package. So screw this I guess.
    // protected function printEasierYaml(array $swagger_docs)
    // {
    //     $this->output->write(\yaml_emit($swagger_docs),true);
    // }

    protected function printYaml(array $mixed = [], $starting_indentation = '', $is_list_start = false): void
    {
        $indentation = $starting_indentation;
        foreach ($mixed as $key => $mix) {
            $is_array = \is_array($mix);
            if (is_numeric($key)) {
                if ($is_array && ! empty($mix)) {
                    $this->printYaml($mix, $indentation.self::YAMLSPACE, true);
                } else {
                    $mix = $is_array ? '[]' : $mix;
                    $this->output->writeln($indentation.self::YAMLPARAMETER.$mix);
                }
            } else {
                if ($is_array && ! empty($mix)) {
                    $this->output->writeln($indentation.$key.self::YAMLARRAYKEYINDICATOR);
                    $this->printYaml($mix, $indentation.self::YAMLSPACE);
                } else {
                    $mix = $is_array ? '[]' : $mix;
                    $mix = \is_bool($mix) ? ($mix ? 'true' : 'false') : $mix;
                    if (! $is_list_start) {
                        $this->output->writeln($indentation.$key.self::YAMLARRAYKEYINDICATOR.$mix);
                    } else {
                        $this->output->writeln($indentation.self::YAMLPARAMETER.$key.self::YAMLARRAYKEYINDICATOR.$mix);
                        $is_list_start = false;
                        $indentation .= self::YAMLSPACE;
                    }
                }
            }
        }
    }

    public function getController(Route $route): string
    {
        if (isset($route->action['controller'])) {
            $class = explode('@', $route->action['controller']);

            return $class[0];
        }

        return 'Undefined controller';
    }

    protected function getRouteParameters(Route $route): array
    {
        $method = $this->getRouteMethod($route);
        if (isset($method)) {
            return $method->getParameters() ?? [];
        }

        return [];
    }

    protected function getRouteMethod(Route $route): ?\ReflectionMethod
    {
        if (isset($route->action['controller'])) {
            $class = explode('@', $route->action['controller']);
            $classMethod = new \ReflectionMethod($class[0], $class[1]);

            return $classMethod;
        }

        return null;
    }

    protected function getRouteVerb(Route $route): string
    {
        $result = Arr::where($route->methods, function ($value, $key) {
            return $value !== 'HEAD' && $value !== 'OPTIONS';
        });

        return strtolower($result[0]);
    }

    protected function setRouteParameters(Route $route, array &$object): void
    {
        $parameters = $this->getRouteParameters($route);
        if (! empty($parameters)) {
            $url_parameters = [];
            $query_parameters = [];
            foreach ($parameters as $parameter) {
                if ($this->parameterHasType($parameter)) {
                    $class = $parameter->getType() && ! $parameter->getType()->isBuiltin() ? new \ReflectionClass($parameter->getType()->getName()) : null;
                    if ($this->parameterClassIsFormRequest($class)) {
                        if ($this->isQueryRoute($route)) {
                            $this->parseJsonBodyParametersAsQueryParameters($class, $query_parameters);
                        } else {
                            $this->parseJsonBodyParameters($class, $object);
                        }
                    } else {
                        $this->parseUrlParameter($parameter, $url_parameters);
                    }
                } else {
                    Log::warning("Couldn't parse ".$parameter.', parameter is not typed on '.$route->uri);
                }
            }
            $all_parameters = array_merge($url_parameters, $query_parameters);
            $object['parameters'] = $all_parameters;
        }
    }

    protected function isQueryRoute(Route $route): bool
    {
        return $this->getRouteVerb($route) === 'get';
    }

    protected function parameterHasType(\ReflectionParameter $parameter): bool
    {
        return $parameter->hasType();
    }

    protected function parameterClassIsFormRequest(?\ReflectionClass $class): bool
    {
        return isset($class) && $class->isSubclassOf(FormRequest::class);
    }

    protected function responseClassIsJsonResource(?\ReflectionClass $class): bool
    {
        return isset($class) && $class->isSubclassOf(JsonResource::class);
    }

    protected function responseClassIsResourceCollection(?\ReflectionClass $class): bool
    {
        return isset($class) && $class->isSubclassOf(ResourceCollection::class);
    }

    protected function parseUrlParameter(\ReflectionParameter $parameter, array &$url_parameters): void
    {
        $param = [
            'name' => $parameter->getName(),
            'in' => 'path',
            'required' => ($parameter->isOptional() ? false : true),
        ];
        $url_parameters[] = $param;
    }

    protected function parseJsonBodyParameters(\ReflectionClass $class, array &$object): void
    {
        $class_name = $class->getName();
        $requestParameters = $this->getRequestParameters($class);
        if (! empty($requestParameters)) {
            $body = [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => $this->createRequestBodyComponent($requestParameters, $class_name),
                        ],
                    ],
                ],
            ];
            $object['requestBody'] = $body;
        }
    }

    protected function parseJsonBodyParametersAsQueryParameters(\ReflectionClass $class, array &$query_parameters): void
    {
        $requestParameters = $class->newInstance()->rules();
        $this->addQueryParameters($requestParameters, $query_parameters);
    }

    protected function getRequestParameters(\ReflectionClass $request): array
    {
        return Arr::undot($request->newInstance()->rules());
    }

    protected function addComponents(array &$swagger_docs): void
    {
        $components = [];
        $components['schemas'] = $this->schemas;
        $components['securitySchemes'] = $this->mappedschemes();
        $swagger_docs['components'] = $components;
    }

    protected function mappedSchemes(): array
    {
        $schemes = [];
        foreach ($this->security_schemes as $security_scheme) {
            $schemes[$security_scheme['name']] = $security_scheme;
            unset($schemes[$security_scheme['name']]['name']);
        }

        return $schemes;
    }

    protected function createRequestBodyComponent(array $parameters, string $requestName): string
    {
        $requestName = $this->trimRequestPath($requestName);
        if (! isset($this->schemas[$requestName])) {
            $component = [
                'type' => 'object',
                'required' => $this->getRequiredParameters($parameters),
                'properties' => $this->getProperties($parameters),
            ];

            $this->schemas[$requestName] = $component;
        }

        return $this->wrapString('#/components/schemas/'.$requestName);
    }

    protected function createResponseBodyFromJsonResource(?\ReflectionType $type): ?string
    {
        $reflection = (isset($type) && ! $type->isBuiltin()) ? new \ReflectionClass($type->getName()) : null;

        $resource_name = $reflection ? $this->trimResourcePath($type->getName()) : null;

        if (isset($resource_name)) {
            if ($this->responseClassIsJsonResource($reflection)) {
                if ($this->responseClassIsResourceCollection($reflection)) {
                    $parameters = $reflection->newInstance(new Collection())->toArray(request());
                } else {
                    $parameters = $reflection->newInstance(new Model())->toArray(request());
                }

                if (! isset($this->schemas[$resource_name])) {
                    $component = [
                        'type' => 'object',
                        'properties' => $this->getPropertiesFromResource($parameters),
                    ];
                    $this->schemas[$resource_name] = $component;
                }
            }

            return $this->wrapString('#/components/schemas/'.$resource_name);
        }

        return null;
    }

    protected function trimRequestPath(string $requestName): string
    {
        //TODO :regex replace
        return $this->replaceSlashes(str_replace('App\\Http\\Requests\\', '', $requestName));
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

    protected function getPropertiesFromResource(array $parameters) : array
    {
        return $this->getProperties($parameters, true);
    }

    protected function getProperties(array $parameters, bool $is_resource = false): array
    {
        $properties = [];
        foreach ($parameters as $parameter_name => $parameter_info) {
            if(! is_numeric($parameter_name))
            {
                $this->addProperty($parameter_name, $is_resource ? "string" : $parameter_info , $properties);
            }
        }

        return $properties;
    }

    protected function addQueryParameters(array $properties, array &$component): void
    {
        foreach ($properties as $property_name => $property_info) {
            $this->addQueryParameter($property_name, $property_info, $component, $properties);
        }
    }

    protected function hasObjects(array $array) : bool
    {
        return isset($array['*']);
    }

    protected function hasSubParameters(array $array) : bool
    {
        if (empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    protected function addProperty(string $property_name, $property_rule, &$component): void
    {
        $property_rule = is_string($property_rule) ? explode('|',$property_rule) : $property_rule;

        if(! $this->hasObjects($property_rule)) {
            if($this->hasSubParameters($property_rule)) {
                $property = [
                    'type' => 'object',
                    'required' => $this->getRequiredParameters($property_rule),
                    'properties' => $this->getProperties($property_rule),
                ];
            } else {
                $property = [
                    'type' => $this->getPropertyType($property_rule),
                ];
            }
        } else {
            $property = [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => $this->getRequiredParameters($property_rule),
                    'properties' => $this->getProperties($property_rule),
                ]
            ];
        }

        $component[$property_name] = $property;
    }

    protected function addQueryParameter($property_name, $property_info,array &$parameters,array &$other_properties)
    {
        if(str_ends_with($property_name,".*")) {
            return;
        }

        $type = $this->getPropertyType($property_info);
        $name = $type === 'array' ? $property_name.'[]' : $property_name;

        $param = [
            'name' => $name,
            'in' => 'query',
            'required' => $this->isRequestParameterRequired($property_info),
        ];

        if ($type === 'array') {
            $param['style'] = 'form';
            $param['explode'] = true;
            $param['schema'] = [
                'type' => $type,
                'items' => [
                    'type' => 'string',
                ]
            ];

            $enum = $this->findSubProperties($property_name, $other_properties);
            if(! empty($enum)) {
                $param['schema']['items']['enum'] = $enum;
            }
        }

        $parameters[] = $param;
    }

    protected function findSubProperties(string $property_name, array &$other_properties) : array
    {
        $subs = [];
        if(key_exists("{$property_name}.*",$other_properties)) {
            $subs =  $this->getEnumFromRule($other_properties["{$property_name}.*"]);
        }
        return $subs;
    }

    protected function getEnumFromRule($rules) : array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        foreach($rules as $rule)
        {
            if(is_object($rule) && get_class($rule) === In::class)
            {
                return explode(",",str_replace(["in:",'"'],"",(string) $rule));
            }
        }

        return [];
    }

    protected function getPropertyType($rule): string
    {
        if (is_string($rule)) {
            $rule = explode($rule, '|');
        }

        if(in_array('numeric',$rule)) {
            return 'number';
        };

        if(in_array('boolean',$rule)) {
            return 'boolean';
        };

        if(in_array('array',$rule)) {
            return 'array';
        };

        if(in_array('integer',$rule) || in_array('int',$rule)) {
            return 'integer';
        };

        // default to string !

        return 'string';
    }

    protected function getRequiredParameters(array $parameters): array
    {
        $required = [];
        foreach ($parameters as $parameterName => $parameter_rule) {
            if ($this->isRequestParameterRequired($parameter_rule)) {
                $required[] = $parameterName;
            }
        }

        return $required;
    }

    protected function isRequestParameterRequired($parameterRule): bool
    {
        return is_string($parameterRule) && str_contains($parameterRule, 'required');
    }

    protected function getResponses(Route $route, string $verb): array
    {
        $responses = [];

        foreach ($this->getDefaultResponsesForVerb($verb) as $key => $default_response) {
            $responses[$this->wrapString($key)] = $default_response;
        }

        if ($method = $this->getRouteMethod($route)) {
            $class_type = $this->getMethodReturnClass($method);

            $response = [
                'description' => 'The object returned by this method.',
            ];

            $response_reference = $this->createResponseBodyFromJsonResource($class_type);

            if (isset($response_reference)) {
                $response['content'] = [
                    'application/json' => [
                        'schema' => [
                            '$ref' => $response_reference,
                        ],
                    ],
                ];
            }

            $responses[$this->wrapString('200')] = $response;
        }

        return $responses;
    }

    protected function getDefaultResponsesForVerb(string $verb): array
    {
        return $this->default_responses['*'] ?? [] + $this->default_responses[$verb] ?? [];
    }

    protected function getMethodReturnClass(\ReflectionMethod $method): ?\ReflectionType
    {
        if (! $method->hasReturnType()) {
            Log::error('Return object from '.$method->name.' not typed. Unable to obtain response object.');
            return null;
        } else {
            return $method->getReturnType();
        }
    }

    protected function getControllerResponse(Route $route): ?string
    {
        if (isset($route->action['controller'])) {
            $class = explode('@', $route->action['controller']);

            return $class[0];
        }

        return null;
    }

    protected function addPath(array &$paths, array $route): void
    {
        try {
            $verb = $this->getRouteVerb($route['route']);
            $path = [
                'responses' => $this->getResponses($route['route'], $verb),
                'security' => $this->getSecurity($route['route']),
                'tags' => $route['tags'] ?? [],
            ];

            $this->generateSummary($route['route'], $path);
            $this->setRouteParameters($route['route'], $path);
            $path_name = (strpos($route['route']->uri, '/') === 0) ? $route['route']->uri : '/'.$route['route']->uri;
            $paths[$path_name][$verb] = $path;
        } catch (\Exception $exception) {
            Log::info($exception->getMessage().' :'.$this->getRouteName($route['route']), $exception->getTrace());
        }
    }

    /**
     * Not supporting scopes atm.
     */
    protected function getSecurity(Route $route): array
    {
        $security = [];
        $middlewares = $this->router->gatherRouteMiddleware($route);
        foreach ($middlewares as $middleware) {
            foreach ($this->auth_middleware as $key => $auth_middleware) {
                if (isset($auth_middleware['class']) && $auth_middleware['class'] === $middleware) {
                    $security[] = [
                        $auth_middleware['schema']['name'] ?? $key => [],
                    ];
                }
            }
        }

        return $security;
    }

    protected function getPrefix(Route $route): ?string
    {
        return $route->getPrefix() ?? null;
    }

    protected function generateSummary(Route $route, array &$object): void
    {
        $object['summary'] = ucfirst(Str::snake($route->getActionMethod(), ' '));
    }

    protected function wrapString(string $string): string
    {
        return $this->format === 'yaml' ? "'".$string."'" : $string;
    }

    protected function addServers(&$swagger_docs): void
    {
        $servers = Config::get('swagger_gen.servers');
        foreach ($servers as &$server) {
            $params = [];
            preg_match_all("/\{[a-zA-Z0-9-\.,:]+\}/", $server['url'] ?? '', $params);
            if (! empty($params[0])) {
                $this->addParametersToServer($server, $params[0]);
            }
        }

        $swagger_docs['servers'] = $servers;
    }

    protected function addParametersToServer(array &$server, array $params): void
    {
        foreach ($params as $param) {
            $param_cut = substr($param, 1, -1);
            $default = explode(',', preg_replace("/[a-zA-Z0-9-\.]+:/", '', $param_cut));
            $vars = [
                'default' => $default[0],
            ];
            if (count($default) > 1) {
                $vars['enum'] = $default;
            }
            $server['variables'][preg_replace("/:[a-zA-Z0-9-\.,]+/", '', $param_cut)] = $vars;
        }

        $server['url'] = preg_replace("/:[a-zA-Z0-9-\.,]+\}/", '}', $server['url']);
    }

    protected function addVersion(&$object): void
    {
        $object['openapi'] = Config::get('swagger_gen.openapi');
    }

    protected function addInfo(&$object): void
    {
        $object['info'] = Config::get('swagger_gen.info');
    }
}
