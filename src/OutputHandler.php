<?php

namespace LukeDavis\GcpApiGatewaySpec;

use Symfony\Component\Yaml\Yaml;

class OutputHandler
{
    /**
     * Output spec destination path.
     */
    protected string $outputPath;

    /**
     * Default filename if the output path is a directory.
     */
    protected string $defaultFilename = 'generator-output.yaml';

    /**
     * Create a new Output instance.
     */
    public function __construct(?string $outputPath)
    {
        $this->outputPath = match (is_null($outputPath)) {
            true => $this->defaultFilename,
            default => $outputPath
        };
    }

    /**
     * Saves the generated spec file.
     *
     * @param array<string, mixed> $outputSpec The generated spec file
     *
     * @return string The path to the saved file
     */
    public function save(array $outputSpec): string
    {
        // Resolve the output path relative to the current working directory
        $outputPath = $this->resolveOutputPath($this->outputPath);

        // Create the output directory if it doesn't exist
        $outputDir = dirname($outputPath);

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Save the output spec file
        file_put_contents(
            $outputPath,
            Yaml::dump(
                $outputSpec,
                10,
                2,
                Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE | Yaml::DUMP_NUMERIC_KEY_AS_STRING
            )
        );

        return $outputPath;
    }

    /**
     * Resolve the output path relative to the current working directory.
     */
    protected function resolveOutputPath(string $output): string
    {
        // Check if the path is an absolute path
        if (realpath($output) !== false) {
            // If it's a directory, append the default filename
            if (is_dir($output)) {
                return rtrim(realpath($output), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->defaultFilename;
            }

            return realpath($output);
        }

        // If the path is relative, prepend the current working directory
        $resolvedPath = getcwd().DIRECTORY_SEPARATOR.$output;

        // If the resolved path is a directory, append the default filename
        if (is_dir($resolvedPath)) {
            return rtrim($resolvedPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->defaultFilename;
        }

        return $resolvedPath;
    }
}
