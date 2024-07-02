<?php

namespace LukeDavis\GcpApiGatewaySpec;

use Symfony\Component\Yaml\Yaml;

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
    protected array $outputSpec;

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
    public function __construct(string $inputSpec, ?string $outputPath, string $config, bool $preserveResponses = false)
    {
        $this->inputSpec = Yaml::parseFile($inputSpec);
        $this->outputSpec = [];

        $this->preserveResponses = $preserveResponses;

        $this->config = new Config(
            config: Yaml::parseFile($config),
            inputSpec: $this->inputSpec
        );

        $this->output = new OutputHandler(
            outputPath: $outputPath
        );
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
                'description' => $this->config->get('info.description'),
                'version' => $this->config->get('info.version'),
            ],
            'basePath' => $this->config->get('basePath') ?? '/',
            'schemes' => ['https'],
            'produces' => $this->config->get('produces') ?? ['application/json'],
            'consumes' => $this->config->get('consumes') ?? ['application/json'],
            'paths' => [],
        ];

        // If x-google-backend is set, add it to the output spec
        if (!is_null($this->config->get('x-google-backend'))) {
            $this->outputSpec['x-google-backend'] = $this->config->get('x-google-backend');
        }

        // If securityDefinitions is set, add it to the output spec
        if (!is_null($this->config->get('securityDefinitions'))) {
            $this->outputSpec['securityDefinitions'] = $this->config->get('securityDefinitions');
        }
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
        // Initial merge where methodConfig completely overrides defaultPathConfig
        $defaultPathConfig = $this->config->get('path-defaults') ?? [];
        $methodConfig = $this->config->get("path-overrides.{$path}.{$method}") ?? [];

        // Directly replace arrays in defaultPathConfig with those in methodConfig
        $mergedConfig = $defaultPathConfig;

        foreach ($methodConfig as $key => $value) {
            $mergedConfig[$key] = $value;
        }

        $mergedConfig = $this->array_merge_overwrite($mergedConfig, $spec);

        $this->outputSpec['paths'][$path][$method] = $mergedConfig;

        if ($this->preserveResponses) {
            $responses = $this->inputSpec['paths'][$path][$method]['responses'] ?? [];
        } else {
            $responses = [
                '200' => [
                    'description' => 'Successful response',
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ];
        }

        unset($this->outputSpec['paths'][$path]['parameters']);

        preg_match_all('/\{(\w+)\}/', $path, $matches);

        if (isset($matches[1])) {
            foreach ($matches[1] as $parameterName) {
                $this->outputSpec['paths'][$path][$method]['parameters'][] = [
                    'name' => $parameterName,
                    'in' => 'path',
                    'required' => true,
                    'type' => 'string',
                ];
            }
        }

        unset($this->outputSpec['paths'][$path][$method]['responses']);

        $this->outputSpec['paths'][$path][$method]['responses'] = $responses;
    }

    /**
     * Merges two arrays recursively, with the second array taking precedence.
     *
     * @param array<string, mixed> $array1 The first array
     * @param array<string, mixed> $array2 The second array
     *
     * @return array<string, mixed> The merged array
     */
    private function array_merge_overwrite(array &$array1, array &$array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                // If both are arrays, recursively merge
                $merged[$key] = $this->array_merge_overwrite($merged[$key], $value);
            } else {
                // Otherwise, replace the value
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
