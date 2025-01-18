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

        foreach ($this->inputSpec['paths'] as $path => $methods) {
            $this->addPath($path, $methods);
        }

        // Recursively look for "type" keys in the output spec, and 
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
        }
        if (isset($methodSpec['parameters'])) {
            $methodSpec['parameters'] = $this->getFixedParameters($methodSpec['parameters']);
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
        if (!$this->preserveResponses) {
            $responses = [
                '200' => [
                    'description' => 'Successful response',
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ];
        }

        return $responses;
    }

    /**
     * Fixes broken path parameters
     * 
     * Converts arrays of types to single types, and removes 'null' types
     * in favor of x-nullable: true
     *
     * @param array<string, mixed>   $parameters The spec for the method
     * @return array<string, mixed>  The merged method config
     */
    protected function getFixedParameters(array $parameters): array
    {
        foreach ($parameters as &$param) {
            if (isset($param['type']) && is_array($param['type'])) {
                if (in_array('null', $param['type'], true)) {
                    $param['x-nullable'] = true;
                    $param['type'] = array_filter($param['type'], fn($type) => $type !== 'null');
                    $param['type'] = reset($param['type']);
                }
            }
            if (isset($param['schema']) && isset($param['schema']['properties'])) {
                $schemaProperties = &$param['schema']['properties'];
                foreach ($schemaProperties as &$property) {
                    if (isset($property['type']) && is_array($property['type'])) {
                        if (in_array('null', $property['type'], true)) {
                            $property['x-nullable'] = true;
                            $property['type'] = array_filter($property['type'], fn($type) => $type !== 'null');
                            $property['type'] = reset($property['type']);
                        }
                    }
                }
                unset($property); // Unset the reference to avoid accidental modifications
            }
        }
        unset($param); // Unset the reference to avoid accidental modifications

        return $parameters;
    }

    protected function getFixedType(mixed $type): string
    {
        if (!is_array($type)) {
            return $type;
        }

        if (in_array('null', $type, true)) {
            $type = array_filter($type, fn($type) => $type !== 'null');
            $type = reset($type);
        }

        return $type;
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
}
