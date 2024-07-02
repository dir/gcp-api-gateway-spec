<?php

namespace LukeDavis\GcpApiGatewaySpec;

/**
 * @implements \ArrayAccess<string, mixed>
 */
class Config implements \ArrayAccess
{
    /**
     * The parsed config file.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * The parsed input spec file.
     *
     * @var array<string, mixed>
     */
    protected array $inputSpec;

    /**
     * Create a new Config instance.
     *
     * @param array<string, mixed> $config    The parsed config file
     * @param array<string, mixed> $inputSpec The parsed input spec file
     */
    public function __construct(array $config, array $inputSpec)
    {
        $this->config = $config;
        $this->inputSpec = $inputSpec;
    }

    /**
     * Get a value/nested value from the config file, or from the input spec file if not found in the config.
     *
     * @param string $key The key to retrieve, using dot notation for nested values
     *
     * @return mixed The value from the config or input spec, or null if not found
     */
    public function get(string $key): mixed
    {
        return $this->getValue($this->config, $key) ?? $this->getValue($this->inputSpec, $key) ?? null;
    }

    /**
     * Get a nested value from an array using dot notation.
     *
     * @param array<string, mixed> $array          The array to search
     * @param string               $dotNotationKey The key to retrieve, using dot notation for nested values
     *
     * @return mixed The value, or null if not found
     */
    protected function getValue(array $array, string $dotNotationKey): mixed
    {
        $keys = explode('.', $dotNotationKey);

        foreach ($keys as $innerKey) {
            if (array_key_exists($innerKey, $array)) {
                $array = $array[$innerKey];
            } else {
                return null;
            }
        }

        return $array;
    }

    /**
     * ArrayAccess method to check if offset exists.
     */
    public function offsetExists($offset): bool
    {
        return $this->get($offset) !== null;
    }

    /**
     * ArrayAccess method to get offset.
     */
    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * ArrayAccess method to set offset.
     */
    public function offsetSet($offset, $value): void
    {
        // Implementing write functionality if necessary, otherwise:
        throw new \Exception('Cannot write to config.');
    }

    /**
     * ArrayAccess method to unset offset.
     */
    public function offsetUnset($offset): void
    {
        // Implementing unset functionality if necessary, otherwise:
        throw new \Exception('Cannot unset config value.');
    }
}
