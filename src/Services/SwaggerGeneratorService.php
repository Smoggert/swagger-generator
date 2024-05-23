<?php

namespace Smoggert\SwaggerGenerator\Services;

use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Smoggert\SwaggerGenerator\Exceptions\SwaggerGeneratorException;
use Smoggert\SwaggerGenerator\Interfaces\ParsesParameter;
use Smoggert\SwaggerGenerator\Interfaces\ParsesResponse;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Content;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Parameter;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Request;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Response;
use Smoggert\SwaggerGenerator\SwaggerDefinitions\Schema;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class SwaggerGeneratorService
{
    protected const CONFIG_FILE_NAME = 'smoggert_swagger';
    protected const OLD_CONFIG_FILE_NAME = 'swagger_gen';
    protected const PATH_CONTEXT = 'PATH_URL';

    protected OutputInterface $output;

    protected Router $router;

    protected RouteCollection $routes;

    protected array $filtered_routes = [];

    protected array $tags = [];
    protected array $schemas = [];
    protected array $security_schemes = [];
    protected array $paths = [];
    protected array $default_responses = [];
    protected string $format = 'json';
    protected array $auth_middleware = [];
    protected array $allowed_routes = [];
    protected array $excluded_routes = [];
    protected array $servers = [];
    protected string $version = '3.0.0';
    protected array $info = [];
    protected array $apis = [];

    /**
     * @var string[]
     */
    protected array $parsers = [];

    /**
     * @var string[]
     */
    protected array $response_parsers = [];

    protected array $supported_formats = [
        'json',
        'yaml',
    ];

    protected ?string $output_file_path = null;

    /**
     * @param  Router  $router
     *
     * @throws SwaggerGeneratorException
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->routes = $router->getRoutes();
        $this->apis = Config::get(self::OLD_CONFIG_FILE_NAME.'.apis') ?? Config::get(self::CONFIG_FILE_NAME.'.apis');

        $this->validateConfiguration();
    }

    /**
     * @param  OutputInterface  $output
     * @param  bool  $print_to_output
     * @param  string  $format
     * @return int
     *
     * @throws Exception
     */
    public function generate(OutputInterface $output, bool $print_to_output, string $format = 'json'): int
    {
        $this->output = $output;

        foreach ($this->apis as $api) {
            $swagger_file = [];

            $this->setConfig($api);
            $this->setFormat($format);
            $this->addVersion($swagger_file);
            $this->addInfo($swagger_file);
            $this->addServers($swagger_file);
            $this->addAuthentication($swagger_file);

            $this->filterRoutes();

            $this->addTags($swagger_file);
            $this->addPaths($swagger_file);
            $this->addComponents($swagger_file);
            $this->printSwaggerDocsUsingFormat($swagger_file, $print_to_output);
        }

        return 0;
    }

    protected function setConfig($configuration): void
    {
        $this->default_responses = $configuration['default_responses'] ?? [];
        $this->output_file_path = $configuration['output'] ?? null;
        $this->auth_middleware = $configuration['middleware'] ?? [];
        $this->servers = $configuration['servers'] ?? [];
        $this->allowed_routes = $configuration['allowed'] ?? [];
        $this->excluded_routes = $configuration['excluded'] ?? [];
        $this->info = $configuration['info'] ?? [];
        $this->parsers = $configuration['parsers'] ?? [];
        $this->response_parsers = $configuration['response_parsers'] ?? [];
    }

    /**
     * @throws SwaggerGeneratorException
     */
    protected function validateConfiguration(): void
    {
        if (empty($this->apis)) {
            throw new SwaggerGeneratorException('No apis configured.');
        }
        foreach ($this->apis as &$api) {
            if (! is_array($api)) {
                throw new SwaggerGeneratorException('Objects within the apis config should be arrays.');
            }

            if (! array_key_exists('default', $this->apis)) {
                throw new SwaggerGeneratorException('Please provide an api.');
            }

            $api = array_merge($this->apis['default'], $api);
        }
    }

    /**
     * @throws Exception
     */
    protected function setFormat(string $format): void
    {
        if (! in_array($format, $this->supported_formats)) {
            $formats = implode(', ', $this->supported_formats);
            throw new Exception("Unsupported format {$format} try: {$formats}");
        }

        if (! extension_loaded($format)) {
            throw new Exception("In order to parse the docs as {$format} you must install/enable the {$format} extension: ( https://github.com/php/pecl-file_formats-yaml or https://www.php.net/manual/en/function.json-encode)");
        }
        $this->format = $format;
    }

    protected function addTags(&$swagger_file): void
    {
        $swagger_file['tags'] = [];

        sort($this->tags);

        foreach ($this->tags as $tag) {
            $swagger_file['tags'][] = [
                'name' => $tag,
            ];
        }
    }

    protected function filterRoutes(): void
    {
        $filtered_routes = [];
        $all_tags = new Collection();

        $non_excluded_routes = $this->routes;

        foreach ($this->excluded_routes as $excluded_route) {
            $temp = new RouteCollection();
            $escaped_excluded_route = str_replace('/', '\/', $excluded_route);
            $excluded = str_replace('{id}', "[a-zA-Z0-9-:\}\{]+", $escaped_excluded_route);
            foreach ($non_excluded_routes as $route) {
                if (! preg_match('/'.$excluded.'/s', $route->uri)) {
                    $temp->add($route);
                }
            }
            $non_excluded_routes = $temp;
        }

        foreach ($this->allowed_routes as $allowed_route) {
            $stripped_allowed_route = str_replace('{$tag}', '([a-zA-Z0-9-]+)', $allowed_route);
            $escaped_allowed_route = str_replace('/', '\/', $stripped_allowed_route);
            $twice_stripped_allowed_route = str_replace('{id}', "[a-zA-Z0-9-:\}\{_]+", $escaped_allowed_route);

            foreach ($non_excluded_routes as $route) {
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

    public function addAuthentication(&$swagger_docs): void
    {
        foreach ($this->auth_middleware as $key => $middleware) {
            $this->addMiddleware($key, $middleware);
        }
    }

    protected function addMiddleware(string $key, array $middleware): void
    {
        $this->security_schemes[$key] = $middleware['schema'];
    }

    protected function addPaths(&$swagger_docs): void
    {
        $paths = [];

        foreach ($this->filtered_routes as $route) {
            $this->addPath($paths, $route['route'], $route['tags']);
        }

        ksort($paths);

        $swagger_docs['paths'] = $paths;
    }

    public function getRouteName(Route $route): string
    {
        if (! str_starts_with($route->uri, '/')) {
            return '/'.$route->uri;
        }

        return $route->uri;
    }

    protected function printSwaggerDocsUsingFormat(array $swagger_docs, bool $print_to_output): void
    {
        $output = ($this->format === 'json') ? $this->printJson($swagger_docs) : $this->printYaml($swagger_docs);

        if ($print_to_output) {
            $this->output->write($output, true);
        }

        $sub_directory = dirname($this->output_file_path);

        if (! File::exists($sub_directory)) {
            File::makeDirectory($sub_directory, 0777, true, true);
        }

        if ($this->output_file_path) {
            File::put($this->output_file_path, $output);
        }
    }

    /**
     * @requires json > 1.2.0
     *
     * @param  array  $swagger_docs
     * @return string
     */
    protected function printJson(array $swagger_docs): string
    {
        return json_encode($swagger_docs, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
    }

    /**
     * @requires yaml >= 0.5.0
     *
     * @param  array  $swagger_docs
     * @return string
     */
    protected function printYaml(array $swagger_docs = []): string
    {
        return yaml_emit($swagger_docs);
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

    /**
     * @throws ReflectionException
     */
    protected function getRouteMethod(Route $route): ?ReflectionMethod
    {
        if (isset($route->action['controller'])) {
            $class = explode('@', $route->action['controller']);

            return new ReflectionMethod($class[0], $class[1]);
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

    /**
     * @throws ReflectionException
     * @throws SwaggerGeneratorException
     */
    protected function setRouteParameters(Route $route, array &$object): void
    {
        $parameters = $this->getRouteParameters($route);
        if (! empty($parameters)) {
            $route_parameters = [];
            foreach ($parameters as $parameter) {
                $this->handleRouteParameter($route, $parameter, $route_parameters, $object);
            }

            $object['parameters'] = $route_parameters;
        }
    }

    /**
     * @throws ReflectionException
     * @throws SwaggerGeneratorException
     */
    protected function handleRouteParameter(Route $route, ReflectionParameter $parameter, array &$route_parameters, array &$object): void
    {
        if (! $this->parameterHasType($parameter)) {
            Log::warning($route->uri."| Couldn't parse ".$parameter.', parameter is not typed on ');

            return;
        }

        $class = $this->getReflectionClass($parameter->getType());

        if (! $this->parameterClassIsFormRequest($class)) {
            $this->parseUrlParameter($parameter, $route_parameters);

            return;
        }

        if ($this->isQueryRoute($route)) {
            $this->parseFormRequest($class, $route_parameters, Parameter::IN_QUERY);

            return;
        }

        $this->parseJsonBodyParameters($class, $object);
    }

    protected function isQueryRoute(Route $route): bool
    {
        return $this->getRouteVerb($route) === 'get';
    }

    protected function parameterHasType(ReflectionParameter $parameter): bool
    {
        return $parameter->hasType();
    }

    protected function parameterClassIsFormRequest(?ReflectionClass $class): bool
    {
        return isset($class) && $class->isSubclassOf(FormRequest::class);
    }

    protected function responseClassIsBaseResponse(?ReflectionClass $class): bool
    {
        return isset($class) && $class->isSubclassOf(\Symfony\Component\HttpFoundation\Response::class);
    }

    protected function parseUrlParameter(ReflectionParameter $reflection_parameter, array &$url_parameters): void
    {
        $parameter = new Parameter(
            $reflection_parameter->getName(),
            [],
            Parameter::IN_URL
        );

        $parameter = $this->parseParameter($parameter, self::PATH_CONTEXT);

        $parameter->setRequired(! $reflection_parameter->isOptional());

        $url_parameters[] = $parameter->toArray();
    }

    /**
     * @throws ReflectionException
     * @throws SwaggerGeneratorException
     */
    protected function parseJsonBodyParameters(ReflectionClass $class, array &$object): void
    {
        if (empty($class->newInstance()->rules())) {
            return;
        }

        $schema_reference = $this->createRequestBodyComponent($class);

        $request_content = new Content();
        $request_content->setJsonSchema($schema_reference);

        $request = new Request(null, $request_content);

        $object['requestBody'] = $request->toArray();
    }

    /**
     * @throws ReflectionException
     * @throws SwaggerGeneratorException
     */
    protected function parseFormRequest(ReflectionClass $class, array &$query_parameters, string $parameter_location): void
    {
        $context = $class->newInstance();

        $requestParameters = $context->rules();

        $this->addParameters($requestParameters, get_class($context), $query_parameters, $parameter_location);
    }

    /**
     * @throws ReflectionException
     */
    protected function getRequestParameters(ReflectionClass $request): array
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

    /**
     * @throws ReflectionException
     * @throws SwaggerGeneratorException
     */
    protected function createRequestBodyComponent(ReflectionClass $class): string
    {
        $requestName = $this->trimRequestPath($class->getName());

        if (! isset($this->schemas[$requestName])) {
            $properties = [];

            $this->parseFormRequest($class, $properties, Parameter::IN_BODY);

            $schema = [
                'type' => Schema::OBJECT_TYPE,
                'required' => $this->getRequiredParameters($this->getRequestParameters($class)),
                'properties' => $properties,
            ];

            $this->schemas[$requestName] = $schema;
        }

        return $this->wrapString('#/components/schemas/'.$requestName);
    }

    /**
     * @throws ReflectionException
     * @throws SwaggerGeneratorException
     */
    protected function createResponseBodyFromJsonResource(?ReflectionType $type): ?Schema
    {
        if (! $type) {
            return null;
        }

        $reflection = $this->getReflectionClass($type);

        $schema = null;

        foreach ($this->response_parsers as $parser_class) {
            if (! class_exists($parser_class)) {
                throw new SwaggerGeneratorException("Parser configuration [$parser_class] invalid.");
            }

            /**
             * @var $parser ParsesResponse
             */
            $parser = new $parser_class;

            if (! $parser instanceof ParsesResponse) {
                throw new SwaggerGeneratorException("Parser configuration [$parser_class] does not implement [ParsesResponse].");
            }

            $schema = $parser($schema, $reflection);
        }

        return $schema;
    }

    /**
     * @throws ReflectionException
     */
    protected function getReflectionClass(?ReflectionType $type): ?ReflectionClass
    {
        if (! $type) {
            return null;
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            Log::info('Tried to parse a UnionType or IntersectionType. This is currently not supported.');

            return null;
        }

        if (! $type->isBuiltin()) {
            return new ReflectionClass($type->getName());
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

    /**
     * @throws SwaggerGeneratorException
     */
    protected function addParameters(array $properties, string $context, array &$component, string $in = Parameter::IN_QUERY): void
    {
        $fixed = $this->fixProperties($properties);

        foreach ($fixed as $property_name => $rules) {
            if (str_contains($property_name, '.')) {
                continue;
            }

            $parameter = $this->createParameter($property_name, $this->transformRulesToArray($rules), $in, $fixed, $context);

            if ($in === Parameter::IN_BODY) {
                $component[$parameter->getName()] = $parameter->getSchema()->toArray();
                continue;
            }

            $component[] = $parameter->toArray();
        }
    }

    protected function fixProperties(array $properties): array
    {
        $fixed = [];
        foreach ($properties as $key => $property) {
            $fixed[$key] = $this->transformRulesToArray($property);
        }

        return $fixed;
    }

    /**
     * @throws SwaggerGeneratorException
     */
    protected function createParameter(string $name, array $rules, string $in, array $properties, string $context): Parameter
    {
        $parameter_name = array_slice(explode('.', $name), -1)[0];

        $parameter = new Parameter(
            parameter_name: $parameter_name,
            rules: $rules,
            in: $in
        );

        $un_dotted_properties = Arr::undot($properties);
        $sub_properties = Arr::get($un_dotted_properties, $name);

        if (is_array($sub_properties) && count($sub_properties)) {
            $array_rules = Arr::get($sub_properties, '*');
            // ENUM HANDLING / ARRAY HANDLING
            if (! empty($array_rules)) {
                $parameter->setArrayType(
                    $this->createParameter(
                        name: "$name.*",
                        rules: array_filter($array_rules, function ($key) {
                            return is_numeric($key);
                        }, ARRAY_FILTER_USE_KEY),
                        in: $in,
                        properties: $properties,
                        context: $context
                    ));
            }

            // OBJECT HANDLING
            unset($sub_properties['*']);

            foreach ($sub_properties as $sub_property_name => $sub_property_rules) {
                if (is_numeric($sub_property_name)) {
                    continue;
                }

                $sub_parameter = $this->createParameter(
                    name: "$name.$sub_property_name",
                    rules: array_filter($sub_property_rules, function ($key) {
                        return is_numeric($key);
                    }, ARRAY_FILTER_USE_KEY),
                    in: $in,
                    properties: $properties,
                    context: $context
                );

                $parameter->addSubParameter(
                    $sub_parameter
                );
            }
        }

        return $this->parseParameter($parameter, $context);
    }

    protected function transformRulesToArray(string|array $rules): array
    {
        return is_string($rules) ? explode('|', $rules) : $rules;
    }

    protected function isNullable(array $property_rule): bool
    {
        return in_array('nullable', $property_rule);
    }

    /**
     * @throws SwaggerGeneratorException
     */
    protected function parseParameter(Parameter $query_parameter, string $context): Parameter
    {
        foreach ($this->parsers as $parser_class) {
            if (! class_exists($parser_class)) {
                throw new SwaggerGeneratorException("Parser configuration [$parser_class] invalid.");
            }

            $parser = new $parser_class;

            if (! $parser instanceof ParsesParameter) {
                throw new SwaggerGeneratorException("Parser configuration [$parser_class] does not implement [ParsesParameter].");
            }

            $query_parameter = $parser($query_parameter, $context);
        }

        return $query_parameter;
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

    /**
     * @throws ReflectionException
     * @throws SwaggerGeneratorException
     */
    protected function getResponses(Route $route, string $verb): array
    {
        $responses = [];

        foreach ($this->getDefaultResponsesForVerb($verb) as $key => $default_response) {
            $responses[$this->wrapString($key)] = $default_response;
        }

        $response = $this->generateResponse($route);

        if (! empty($response)) {
            $responses = array_merge($responses, $response);
        }

        return $responses;
    }

    /**
     * @throws SwaggerGeneratorException
     * @throws ReflectionException
     */
    protected function generateResponse(Route $route): array
    {
        $method = $this->getRouteMethod($route);

        if (! $method) {
            return [];
        }

        $reflection_type = $this->getMethodReturnClass($method, $route);

        $reflection_class = $this->getReflectionClass($reflection_type);

        $response_class_name = $reflection_class?->getName();

        if (! $response_class_name) {
            return [];
        }

        $schema_name = $this->trimResourcePath($reflection_class->getName());

        if (! $this->hasSchema($schema_name)) {
            $response_schema = $this->createResponseBodyFromJsonResource($reflection_type);

            if ($response_schema) {
                $this->schemas[$schema_name] = $response_schema->toArray();
            }
        }

        $request_content = new Content();
        $request_content->setJsonSchema($this->wrapString("#/components/schemas/{$schema_name}"));

        $response = new Response(
            'The request has been properly executed.',
            $request_content
        );

        return [
            $this->wrapString($this->getStatusCode($reflection_type)) => $response->toArray(),
        ];
    }

    protected function hasSchema(string $schema_key): bool
    {
        return isset($this->schemas[$schema_key]);
    }

    /**
     * @throws ReflectionException
     */
    public function getStatusCode(?ReflectionType $type): string
    {
        $reflection = $this->getReflectionClass($type);
        $response_name = $reflection ? $this->trimResourcePath($type->getName()) : null;

        if (isset($response_name)) {
            if ($this->responseClassIsBaseResponse($reflection)) {
                $response = $reflection->newInstance();

                return (string) $response->getStatusCode();
            }
        }

        return '200';
    }

    protected function getDefaultResponsesForVerb(string $verb): array
    {
        return $this->default_responses['*'] ?? [] + $this->default_responses[$verb] ?? [];
    }

    protected function getMethodReturnClass(ReflectionMethod $method, ?Route $route = null): ?ReflectionType
    {
        if (! $method->hasReturnType()) {
            Log::warning(($route ? $route->uri : '').'| Return object from '.$method->name.' not typed. Unable to obtain response object.');

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

    protected function addPath(array &$paths, Route $route, array $tags): void
    {
        try {
            $verb = $this->getRouteVerb($route);
            $path = [
                'responses' => $this->getResponses($route, $verb),
                'security' => $this->getSecurity($route),
                'tags' => $tags,
            ];

            $this->generateSummary($route, $path);
            $this->setRouteParameters($route, $path);
            $path_name = $this->getRouteName($route);
            $paths[$path_name][$verb] = $path;
        } catch (Throwable $exception) {
            Log::error($exception->getMessage().' :'.$this->getRouteName($route));
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
        foreach ($this->servers as &$server) {
            $params = [];
            preg_match_all("/\{[a-zA-Z0-9-\.,:]+\}/", $server['url'] ?? '', $params);
            if (! empty($params[0])) {
                $this->addParametersToServer($server, $params[0]);
            }
        }

        $swagger_docs['servers'] = $this->servers;
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
        $object['openapi'] = '3.1.0';
    }

    protected function addInfo(&$object): void
    {
        $object['info'] = $this->info;
    }
}
