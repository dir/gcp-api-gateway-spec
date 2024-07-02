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
    protected Output $output;

    /**
     * Generated GCP API Gateway spec.
     *
     * @var array<string, mixed>
     */
    protected array $outputSpec;

    /**
     * Create a new Generator instance.
     *
     * @param string $inputSpec   Path to the input Swagger 2.0 spec file
     * @param string $config      Path to the config file
     * @param string $output_path Destination for the output spec file
     */
    public function __construct(string $inputSpec, string $config, string $output_path)
    {
        $this->inputSpec = Yaml::parseFile($inputSpec);
        $this->outputSpec = [];

        $this->config = new Config(
            config: Yaml::parseFile($config),
            inputSpec: $this->inputSpec
        );

        $this->output = new Output(
            output_path: $output_path
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
        $this->output->save($this->outputSpec);
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
            'host' => $this->config->get('host'),
            'basePath' => $this->config->get('basePath'),
            'schemes' => ['https'],
            'produces' => $this->config->get('produces'),
            'consumes' => $this->config->get('consumes'),
            'securityDefinitions' => $this->config->get('securityDefinitions'),
            'x-google-backend' => $this->config->get('x-google-backend'),
            'paths' => [],
        ];
    }
}
