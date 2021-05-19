<?php

namespace Smoggert\SwaggerGenerator\Services;


use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Smoggert\SwaggerGenerator\Models\FakeModelForSwagger as Model;

use Symfony\Component\Console\Output\OutputInterface;

class SwaggerGeneratorService
{
    /**
     * @var \Symfony\Component\Console\Output\OutputInterface  $output
     */

    protected $output;

    /**
     * @var Illuminate\Routing\Router $router;
     */

    protected $router;

    /**
     * @var Illuminate\Routing\RouteCollection $routes;
     */

    protected $routes;

    /**
     * @var Illuminate\Support\Collection $filtered_routes;
     */

    protected $filtered_routes;

    public const YAMLSPACE = "  ";
    public const YAMLPARAMETER = "- ";
    public const YAMLARRAYKEYINDICATOR = ": ";

    protected $schemas = [];
    protected $security_schemes = [];
    protected $paths = [];
    protected $default_responses;

    protected $supported_formats = [
        'json',
        'yaml'
    ];

    protected $output_file_path;

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->routes = $router->getRoutes();
        $this->default_responses = Config::get('swagger_gen.default_responses');
        $this->output_file_path = Config::get('swagger_gen.output');
    }

    public function generate(OutputInterface $output, string $format = 'yaml') : int
    {
        if(! in_array($format, $this->supported_formats))
        {
            $formats = implode(', ',$this->supported_formats);
            throw new \Exception("Unsupported format {$format} try: {$formats}");
        }

        $this->output = $output;

        $this->filterRoutes();

        $swagger_file = [];

        $this->addVersion($swagger_file);
        $this->addInfo($swagger_file);
        $this->addServers($swagger_file);
        $this->addAuthentication($swagger_file);

        $this->addPaths($swagger_file);
        $this->addComponents($swagger_file);
        $this->printSwaggerDocsUsingFormat($swagger_file, $format);
        return 0;
    }
    public function filterRoutes() : void
    {
        $allowed_routes = Config::get('swagger_gen.allowed');

        $this->filtered_routes = new Collection();

        foreach($this->routes as $route)
        {
            foreach($allowed_routes as $allowed_route)
            {
                if(str_contains($route->uri,$allowed_route))
                    $this->filtered_routes->push($route);
            }
        }
    }

    public function addAuthentication(&$swagger_docs)
    {
        $schemes = Config::get('swagger_gen.middleware');
        foreach($schemes as $key => $scheme)
        {
            $this->addScheme($key, $scheme);
        }
    }
    protected function addScheme(string $key, string $scheme_string) : void
    {
         // Strip name if it exists and get the scheme type
        $type = preg_replace('/[:](.*)/s',"",preg_replace('/[;](.*)/s',"",$scheme_string,1),1);
        // Get the scheme name if supplied, otherwise default to the middleware name.
        $scheme_name = preg_replace('/(.*)[;]/s',"",$scheme_string,1);
        // Get the parameters
        $parameters_string = preg_replace('/[;](.*)/s',"",preg_replace('/(.*)[:]/s',"",$scheme_string,1),1);
        $scheme_parameters = $parameters_string === "" ? null : explode("|",$parameters_string);
        if($security_scheme =  $this->buildScheme($type, $scheme_parameters))
        {
            $this->security_schemes[$key] = [
                'scheme' =>$security_scheme,
                'name' => $scheme_name === $scheme_string ? $key : $scheme_name
            ];
        }
    }

    protected function buildScheme(string $type, ?array $scheme_parameters) : ?array
    {
        $type_method = "get{$type}AuthScheme";
        if(\method_exists($this, $type_method))
        {
            return $scheme_parameters ? $this->$type_method($scheme_parameters) : $this->type_method();
        } else {
            return null;
            Log::error("Supplied auth type: {$type} is not supported.");
        }
    }


    protected function addPaths(&$swagger_docs)
    {
        $paths = [];
        foreach($this->filtered_routes as $route)
        {
            $this->addPath($paths,$route);
        }
        $swagger_docs['paths'] = $paths;

    }

    public function getRouteName(Route $route) :string
    {
        if(substr($route->uri,0,1) !== '/')
        {
            return '/'.$route->uri;
        }
        return $route->uri;
    }
    
    protected function printSwaggerDocsUsingFormat(array $swagger_docs,string $format)
    {
        if($format === 'yaml')
        {
            $this->printYaml($swagger_docs);
        }elseif($format === 'json'){
            $this->printJson($swagger_docs);
        }
    }
    protected function printJson(array $swagger_docs)
    {
        $this->output->write(json_encode($swagger_docs,JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES),true);    
    }

    // PECL: Yaml isn't a default php-package. So screw this I guess.
    // protected function printEasierYaml(array $swagger_docs)
    // {
    //     $this->output->write(\yaml_emit($swagger_docs),true);
    // }

    protected function printYaml(array $mixed = [], $starting_indentation = "", $is_list_start = false) : void
    {
        $indentation = $starting_indentation;
        foreach($mixed as $key => $mix)
        {
            $is_array = \is_array($mix);
            if(is_numeric($key))
            {
                if($is_array && ! empty($mix))
                {
                    $this->printYaml($mix, $indentation . self::YAMLSPACE, true);
                } else {
                    $mix = $is_array ? "[]" : $mix;
                    $this->output->writeln($indentation . self::YAMLPARAMETER . $mix);
                }
            } 
            else {
                if($is_array && ! empty($mix))
                {
                    $this->output->writeln($indentation. $key. self::YAMLARRAYKEYINDICATOR);
                    $this->printYaml($mix, $indentation . self::YAMLSPACE);
                } else {
                    $mix = $is_array ? "[]" : $mix;
                    if(! $is_list_start)
                    {
                        $this->output->writeln($indentation . $key . self::YAMLARRAYKEYINDICATOR . $mix);
                    } else {
                        $this->output->writeln($indentation . self::YAMLPARAMETER . $key . self::YAMLARRAYKEYINDICATOR . $mix);
                        $is_list_start = false;
                        $indentation .= self::YAMLSPACE;
                    }
                }
            }
        }
    }

    public function getController(Route $route) :string
    {
        if(isset($route->action['controller']))
        {
            $class = explode("@",$route->action['controller']);
            return $class[0];
        }
        return "Undefined controller";
    }

    protected function getRouteParameters(Route $route) : array
    {
        $method = $this->getRouteMethod($route);
        if(isset($method))
        {
            return $method->getParameters() ?? [];
        }
        return [];

    }

    protected function getRouteMethod(Route $route) : ?\ReflectionMethod
    {
        if(isset($route->action['controller']))
        {
            $class = explode("@",$route->action['controller']);
            $classMethod = new \ReflectionMethod($class[0],$class[1]);
            return $classMethod;
        }
        return null;
    }
    protected function getRouteVerb(Route $route) : string
    {
        $result = Arr::where($route->methods, function($value, $key) {
            return $value !== "HEAD" && $value !== "OPTIONS";
        });
        return strtolower($result[0]);
    }
    protected function setRouteParameters(Route $route, array &$object): void
    {
        $parameters = $this->getRouteParameters($route);
        if( ! empty($parameters))
        {
            $url_parameters = [];
            $query_parameters = [];
            foreach($parameters as $parameter)
            {
                if($this->parameterHasType($parameter))
                {
                    $class = $parameter->getType() && !$parameter->getType()->isBuiltin() ? new \ReflectionClass($parameter->getType()->getName()) : null;
                    if($this->parameterClassIsFormRequest($class))
                    {
                        if($this->isQueryRoute($route))
                        {
                            $this->parseJsonBodyParametersAsQueryParameters($class,$query_parameters);
                        }
                        $this->parseJsonBodyParameters($class,$object);
                    } else
                    {
                        $this->parseUrlParameter($parameter,$url_parameters);
                    }

                } else
                {
                    Log::warning("Couldn't parse ".$parameter.", parameter is not typed on ".$route->uri);
                }
            }
            $all_parameters = array_merge($url_parameters,$query_parameters);
            $object['parameters'] = $all_parameters;
        }
    }
    protected function isQueryRoute(Route $route) : bool
    {
        return $this->getRouteVerb($route) === 'get';
    }
    protected function parameterHasType(\ReflectionParameter $parameter) : bool
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
            'in' => "path",
            'type' => "string",
            'required' => ($parameter->isOptional() ? "false": "true"),
        ];
        $url_parameters[] = $param;
    }

    protected function parseJsonBodyParameters(\ReflectionClass $class,array &$object):void
    {
        $class_name = $class->getName();
        $requestParameters = $this->getRequestParameters($class);
        if(! empty($requestParameters)) {
            $body = [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '\$ref' => $this->createRequestBodyComponent($requestParameters, $class_name),
                            ],
                        ],
                    ],
                ];
            $object['requestBody'] = $body;
        }
    }
    protected function parseJsonBodyParametersAsQueryParameters(\ReflectionClass $class, array &$query_parameters):void
    {
        $requestParameters = $class->newInstance()->rules();
        $this->addQueryParameters($requestParameters,$query_parameters);
    }
    protected function getRequestParameters(\ReflectionClass $request) : array
    {
        return $request->newInstance()->rules();
    }

    protected function addComponents(array &$swagger_docs) : void
    {
        $components = [];
        $components['schemas'] = $this->schemas;
        $components['securitySchemes'] = $this->mappedschemes();
        $swagger_docs['components'] = $components;
     }

    protected function mappedSchemes(): array
    {
        $schemes = [];
        foreach($this->security_schemes as $security_scheme)
        {
            $schemes[$security_scheme['name']] = $security_scheme['scheme'];
        }
        
        return $schemes;
    }
    protected function createRequestBodyComponent(array $parameters, string $requestName) : string
    {
        $requestName = $this->trimRequestPath($requestName);
        if(! isset($this->schemas[$requestName]))
        {
            $component = [
                'type' => 'object',
                'required' => $this->getRequiredParameters($parameters),
                'properties' => $this->getProperties($parameters)
            ];
            $this->schemas[$requestName] = $component;
        }
        return $this->wrapString('#/components/schemas/'.$requestName);
    }
    
    protected function createResponseBodyFromJsonResource(?\ReflectionType $type) : string
    {

        $reflection = (isset($type) && !$type->isBuiltin()) ? new \ReflectionClass($type->getName()) : null;

        $resource_name = $reflection ? $this->trimResourcePath($type->getName()) : '204';

        if($this->responseClassIsJsonResource($reflection))
        {
            if($this->responseClassIsResourceCollection($reflection))
            {
                $parameters = $reflection->newInstance(new Collection())->toArray(request());
            }
            else{
                $parameters = $reflection->newInstance(new Model())->toArray(request());
            }

            
            if(! isset($this->schemas[$resource_name]))
            {
                $component = [
                    'type' => 'object',
                    'properties' => $this->getProperties($parameters)
                ];
                $this->schemas[$resource_name] = $component;
            }
        };

        return $this->wrapString('#/components/schemas/'.$resource_name);
    }             

    protected function trimRequestPath(string $requestName)
    {
        //TODO :regex replace
        return str_replace('App\\Http\\Requests\\',"",$requestName);
    }
    protected function trimResourcePath(string $requestName)
    {
        //TODO :regex replace
        return str_replace('App\\Http\\Resources\\',"",$requestName);
    }

    protected function getProperties(array $parameters): array
    {
        $properties = [];
        foreach($parameters as $parameter_name => $parameter_info)
        {
            $this->addProperty($parameter_name, $parameter_info, $properties);
        }
        return $properties;
    }

    protected function addQueryParameters(array $properties, array &$component): void
    {
        foreach($properties as $property_name => $property_info)
        {
            $this->addQueryParameter($property_name,$property_info,$component);
        }
    }
    protected function addProperty(string $property_name, $property_info, &$component): void
    {
        $property = [
            'type' => $this->getPropertyType($property_info)
        ];
        
        $component[$property_name] = $property;
    }

    protected function addQueryParameter($property_name,$property_info,&$parameters)
    {
        $param = [
            'name' => $property_name,
            'in' => "query",
            'type' =>  $this->getPropertyType($property_info),
            'required' => $this->isRequestParameterRequired($property_info) ? "false": "true",
        ];

        $parameters[] = $param;
    }
    
    protected function getPropertyType($info) : string
    {
        if(is_string($info)) {
            if (str_contains($info, 'string') || str_contains($info, 'date') || str_contains($info,'email') || str_contains($info,'ip')) {
                return 'string';
            } elseif (str_contains($info, 'integer')) {
                return 'integer';
            } elseif (str_contains($info, 'numeric'))
            {
                return 'number';
            } elseif (str_contains($info, 'bool'))
            {
                return 'boolean';
            } elseif ( str_contains($info,'int'))
            {
                Log::alert('Possible use of `int´ statement. Due to possible mismatches this type should be declared as integer.');
                return 'integer';
            }
        }
        //default to string 
        return 'string';
    }

    protected function getRequiredParameters(array $parameters) : array
    {
        $required = [];
        foreach($parameters as $parameterName => $parameter_rule)
        {
            if($this->isRequestParameterRequired($parameter_rule))
                $required[] = $parameterName;
        }
        return $required;
    }

    protected function isRequestParameterRequired($parameterRule) : bool
    {
        return is_string($parameterRule) && str_contains($parameterRule,'required');
    }
    
    protected function getResponses(Route $route, string $verb): array
    {
        $responses = [];
        
        foreach($this->getDefaultResponsesForVerb($verb) as $key => $default_response)
        {
            $responses[$this->wrapString($key)] = $default_response;
        }

        if($method = $this->getRouteMethod($route))
        {
            
            $class_type = $this->getMethodReturnClass($method);
            $response = [
               'description' => "The object returned by this method.",
               'content' => [
                   'application/json' => [
                        'schema' => [
                            '\$ref' => $this->createResponseBodyFromJsonResource($class_type),
                        ],
                   ]
               ]
            ];
            $responses[$this->wrapString('200')] = $response;
        }
        return $responses;
    }

    protected function getDefaultResponsesForVerb(string $verb) :array
    {
        return $this->default_responses['*'] ?? [] + $this->default_responses[$verb] ?? [];
    }

    protected function getMethodReturnClass(\ReflectionMethod $method) :?\ReflectionType
    {
        if(! $method->hasReturnType())
        {
            return null;
            Log::error("Return object from ".$method->name." not typed. Unable to obtain response object.");
        } else
        {
            return $method->getReturnType();
        }
    }
    protected function getControllerResponse(Route $route) : ?string
    {
        if(isset($route->action['controller']))
        {
            $class = explode("@",$route->action['controller']);
            return $class[0];
        }
        return null;
    }
    protected function addPath(array &$paths, Route $route):void
    {
            try {
                $verb = $this->getRouteVerb($route);
                $path = [
                    'responses' => $this->getResponses($route, $verb),
                    'security' => $this->getSecurity($route)
                ];
                
                $this->generateSummary($path);
                $this->setRouteParameters($route, $path);
                $paths[$route->uri][$verb] = $path;
            }catch (\Exception $exception)
            {
                Log::info($exception->getMessage()." :".$this->getRouteName($route), $exception->getTrace());
            }
    }

    protected function getSecurity(Route $route) : array 
    {
        $security = [];
        $middlewares = $this->router->gatherRouteMiddleware($route);
        foreach($middlewares as $middleware)
        {
            if(isset($this->security_schemes[$middleware]))
            {
                $security[] = [
                    $this->security_schemes[$middleware]['name'] => []
                ];
            }
        }
        return $security;
    }
    protected function getPrefix(Route $route) : ?string
    {
       return $route->getPrefix() ?? null;
    }

    protected function generateSummary(array &$object): void
    {
        $object['summary'] = "SOME TEXT GETS INSERTED HERE?";
    }

    protected function wrapString(string $string) : string
    {
        return "'".$string."'";
    }
    protected function addServers(&$swagger_docs) : void
    {
        $swagger_docs['servers'] = Config::get('swagger_gen.servers');
    }

    protected function addVersion(&$object) : void
    {
        $object['openapi'] = Config::get('swagger_gen.openapi');
    }

    protected function addInfo(&$object): void
    {
        $object['info'] = Config::get('swagger_gen.info');
    }

    protected function getBasicAuthScheme() : array
    {
        return [
            'type' => 'http',
            'scheme' => 'basic'
        ];
    }

    protected function getBearerAuthScheme() : array
    {
        return [
            'type' => 'http',
            'scheme' => 'bearer'
        ];
    }

    protected function getApiKeyAuthScheme(array $params) : array
    {
        $api_key_auth =  [
            'type' => 'apiKey',
            'in' => $params[0],
        ];

        if(isset($params[1])) {
            $api_key_auth['name'] = $params[1];
        }

        return $api_key_auth;
    }

    protected function getOpenIDAuthScheme(array $params)
    {
        return [
            'type' => 'openIdConnect',
            'openIdConnectUrl' => $params[0]
        ];
    }
}