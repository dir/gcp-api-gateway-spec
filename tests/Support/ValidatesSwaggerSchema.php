<?php

namespace LukeDavis\GcpApiGatewaySpec\Tests\Support;

use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates generated YAML against the official Swagger 2.0 JSON schema
 * (swagger.io/v2/schema.json) — the same schema `gcloud api-gateway
 * api-configs create` validates uploaded specs against.
 */
trait ValidatesSwaggerSchema
{
    /**
     * Returns the list of validation errors for a generated YAML document.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function swaggerSchemaErrors(string $yaml): array
    {
        $schema = json_decode((string) file_get_contents(dirname(__DIR__).'/fixtures/swagger-v2-schema.json'));
        $draft04 = json_decode((string) file_get_contents(
            dirname(__DIR__, 2).'/vendor/justinrainbow/json-schema/dist/schema/json-schema-draft-04.json'
        ));

        $storage = new SchemaStorage();
        $storage->addSchema('http://json-schema.org/draft-04/schema', $draft04);
        $storage->addSchema('http://swagger.io/v2/schema.json', $schema);

        // PARSE_OBJECT_FOR_MAP keeps the map/sequence distinction intact:
        // `{}` parses to stdClass and `[]` to an empty PHP array, exactly as
        // a JSON-level validator (like gcloud's) would see the document.
        $data = Yaml::parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP);

        $validator = new Validator(new Factory($storage));
        $validator->validate($data, (object) ['$ref' => 'http://swagger.io/v2/schema.json#']);

        return $validator->getErrors();
    }

    protected function assertValidSwaggerSpec(string $yaml, string $context = ''): void
    {
        $errors = $this->swaggerSchemaErrors($yaml);

        self::assertSame(
            [],
            $errors,
            trim($context."\nGenerated spec violates the Swagger 2.0 JSON schema:\n"
                .json_encode($errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
        );
    }
}
