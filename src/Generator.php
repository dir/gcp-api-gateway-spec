<?php

namespace LukeDavis\GcpApiGatewaySpec;

use Symfony\Component\Yaml\Yaml;
use LukeDavis\GcpApiGatewaySpec\Helpers;

class Generator
{
    /**
     * Input spec file.
     */
    protected mixed $inputSpec;

    /**
     * Config file.
     */
    protected Config $config;

    /**
     * Output handler.
     */
    protected OutputHandler $output;

    /**
     * Generated GCP API Gateway spec.
     *
     * @var array<string, mixed>
     */
    protected array $outputSpec = [];

    /**
     * The host to use for the generated spec.
     */
    protected ?string $host;

    /**
     * The backend host to use for the generated spec.
     */
    protected ?string $backendHost;

    /**
     * Whether to preserve responses from the generated spec file and replace them with generic 200 responses.
     */
    protected bool $preserveResponses;

    /**
     * Default responses to use when stripping responses and in the case of empty responses.
     * @var array<mixed>
     */
    protected array $defaultResponses = [
        '200' => [
            'description' => 'Successful response',
            'schema' => [
                'type' => 'object',
            ],
        ],
    ];

    /**
     * Unsupported properties to remove recursively
     * @var array<string>
     */
    protected array $unsupportedProperties = ['additionalItems', 'patternProperties', 'dependencies', 'propertyNames', 'contains', 'const', 'if', 'then', 'else'];

    /**
     * Create a new Generator instance.
     *
     * @param string $inputSpec Path to the input Swagger 2.0 spec file
     * @param string $config    Path to the config file
     */
    public function __construct(
        string $inputSpec,
        ?string $outputPath,
        #[\SensitiveParameter]
        string $config,
        #[\SensitiveParameter]
        ?string $host = null,
        #[\SensitiveParameter]
        ?string $backendHost = null,
        bool $preserveResponses = false)
    {
        $this->inputSpec = Yaml::parseFile($inputSpec);

        $this->config = new Config(
            config: Yaml::parseFile($config),
            inputSpec: $this->inputSpec
        );

        $this->output = new OutputHandler(
            outputPath: $outputPath
        );

        $this->host = $host;
        $this->backendHost = $backendHost;

        $this->preserveResponses = $preserveResponses;
    }

    /**
     * Validates the input spec file.
     */
    public function validate(): void
    {
        if (!isset($this->inputSpec['swagger']) || $this->inputSpec['swagger'] !== '2.0') {
            throw new \InvalidArgumentException('The input spec file is not a valid Swagger 2.0 spec file. Missing or invalid "swagger" key.');
        }
    }

    /**
     * Generates a GCP API Gateway Swagger spec file.
     */
    public function generate(): void
    {
        $this->createBaseSpec();

        if (isset($this->inputSpec['definitions'])) {
            foreach ($this->inputSpec['definitions'] as $definitionName => $definition) {
                $this->addDefinition($definitionName, $definition);
            }
        }

        foreach ($this->inputSpec['paths'] as $path => $methods) {
            $this->addPath($path, $methods);
        }

        $this->recursivelyFixTypes($this->outputSpec);
        $this->recursivelyRemoveUnsupportedProperties($this->outputSpec);
    }

    /**
     * Saves the generated spec file.
     *
     * @return string The path to the saved file
     */
    public function save(): string
    {
        return $this->output->save($this->outputSpec);
    }

    /**
     * Creates the base spec for the output file.
     *
     * This includes the info, host, basePath, schemes, produces, consumes, securityDefinitions, x-google-backend, and paths.
     * Grabs these with priority from the config file, then the input spec file.
     */
    protected function createBaseSpec(): void
    {
        $this->outputSpec = [
            'swagger' => '2.0',
            'info' => [
                'title' => $this->config->get('info.title'),
                'description' => $this->config->get('info.description') ?? '',
                'version' => $this->config->get('info.version'),
            ],
            'basePath' => $this->config->get('basePath') ?? '/',
            'schemes' => ['https'],
            'produces' => $this->config->get('produces') ?? ['application/json'],
            'consumes' => $this->config->get('consumes') ?? ['application/json'],
        ];

        // If x-google-backend is set, add it to the output spec
        if (!is_null($this->config->get('x-google-backend'))) {
            $this->outputSpec['x-google-backend'] = $this->config->get('x-google-backend');
        }

        // If securityDefinitions is set, add it to the output spec
        if (!is_null($this->config->get('securityDefinitions'))) {
            $this->outputSpec['securityDefinitions'] = $this->config->get('securityDefinitions');
        }

        // If host is set, add it to the output spec
        if (!is_null($this->host)) {
            $this->outputSpec['host'] = $this->host;
        }

        // If backend host is set, add it to the output spec
        if (!is_null($this->backendHost)) {
            $this->outputSpec['x-google-backend']['address'] = $this->backendHost;
        }

        $this->outputSpec['paths'] = [];
    }

    /**
     * Adds a definition to the output spec file.
     *
     * @param string              $name      The name of the definition
     * @param array<string, mixed> $definition The definition to add
     */
    protected function addDefinition(string $name, array $definition): void
    {
        $this->outputSpec['definitions'][$name] = $definition;
    }

    /**
     * Adds a path to the output spec file.
     *
     * @param string               $path    The path to add
     * @param array<string, mixed> $methods The methods for the path
     */
    protected function addPath(string $path, array $methods): void
    {
        $this->outputSpec['paths'][$path] = $methods;

        foreach ($methods as $method => $details) {
            $this->addMethod($path, $method, $details);
        }
    }

    /**
     * Adds a method to the output spec file.
     *
     * @param string               $path   The path for the method
     * @param string               $method The method to add
     * @param array<string, mixed> $spec   The spec for the method
     */
    protected function addMethod(string $path, string $method, array $spec): void
    {
        $methodSpec = $this->getNormalizedMethod($path, $method, $spec);

        $methodSpec = $this->addMissingPathParams($path, $methodSpec);
        if (isset($methodSpec['responses'])) {
            $methodSpec['responses'] = $this->getMethodResponses($methodSpec['responses']);
        } else {
            $methodSpec['responses'] = $this->defaultResponses;
        }

        $this->outputSpec['paths'][$path][$method] = $methodSpec;
    }

    /**
     * Gets the responses for a method
     *
     * @param array<string, mixed>   $responses The original responses for the method
     * @return array<int|string, mixed>  The responses for the method
     */
    protected function getMethodResponses(array $responses): array
    {
        if ($this->preserveResponses) {
            return $responses;
        }

        return $this->defaultResponses;
    }

    /**
     * Adds missing path parameters to a method spec.
     *
     * @param string                 $path       The path for the method
     * @param array<string, mixed>   $methodSpec The spec for the method
     * @return array<string, mixed>  The merged method config
     */
    protected function addMissingPathParams(string $path, array $methodSpec): array
    {
        preg_match_all('/\{(\w+)\}/', $path, $matches);
        $hasPathParams = count($matches[1]) > 0;
        if (!$hasPathParams) {
            return $methodSpec;
        }

        if (!isset($methodSpec['parameters'])) {
            $methodSpec['parameters'] = [];
        }

        foreach ($matches[1] as $parameterName) {
            $paramExists = false;
            foreach ($methodSpec['parameters'] as $param) {
                if (isset($param['name']) && $param['name'] === $parameterName && isset($param['in']) && $param['in'] === 'path') {
                    $paramExists = true;
                    break;
                }
            }

            if (!$paramExists) {
                $methodSpec['parameters'][] = [
                    'name' => $parameterName,
                    'in' => 'path',
                    'required' => true,
                    'type' => 'string',
                ];
            }
        }

        return $methodSpec;
    }

    /**
     * Adds a method to the output spec file.
     *
     * @param string                 $path   The path for the method
     * @param string                 $method The method to add
     * @param array<string, mixed>   $spec   The spec for the method
     * @return array<string, mixed>  The merged method config
     */
    protected function getNormalizedMethod(string $path, string $method, array $spec): array
    {
        $defaultPathConfig = $this->config->get('path-defaults') ?? [];
        $methodConfig = $this->config->get("path-overrides.{$path}.{$method}") ?? [];
        $mergedConfig = $defaultPathConfig;
        foreach ($methodConfig as $key => $value) {
            $mergedConfig[$key] = $value;
        }
        $mergedConfig = Helpers::array_merge_overwrite($mergedConfig, $spec);
        if (!is_null($this->backendHost)) {
            $mergedConfig['x-google-backend']['address'] = $this->backendHost;
        }
        unset($mergedConfig['paths'][$path]['parameters']);
        return $mergedConfig;
    }

    /**
     * Recursively fixes ALL occurences of type in the spec.
     * 
     * Removes null values and replaces them with x-nullable: true,
     * and fixes array types to the first non-null type.
     *
     * @param array<string, mixed> &$data The data to process
     */
    protected function recursivelyFixTypes(array &$data): void
    {
        foreach ($data as $key => &$value) {
            if ($key === 'type') {
                $hasNull = false;
                if (is_array($value)) {
                    $hasNull = in_array('null', $value, true);
                    $value = array_filter($value, fn($type) => $type !== 'null');
                    $value = reset($value);
                } elseif ($value === 'null') {
                    $hasNull = true;
                    $value = 'string';
                }
                
                if ($hasNull) {
                    $data['x-nullable'] = true;
                }
            } elseif (is_array($value)) {
                $this->recursivelyFixTypes($value);
            }
        }
    }

    /**
     * Recursively removes unsupported properties from the spec.
     *
     * @param array<string, mixed> &$data The data to process
     */
    protected function recursivelyRemoveUnsupportedProperties(array &$data): void
    {
        foreach ($data as $key => &$value) {
            if (in_array($key, $this->unsupportedProperties, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $this->recursivelyRemoveUnsupportedProperties($value);
            }
        }
    }
}
