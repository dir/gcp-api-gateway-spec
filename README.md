# GCP API Gateway Spec Generator

This is a simple tool that:

- Takes a Swagger 2.0 spec file
- Takes a configuration file
- Generates a new Swagger 2.0 spec file with API Gateway specific properties
- Optionally (recommended), strips responses from the original spec file, and replaces them with generic 200 responses.
  - Complicated responses are a constant source of errors when deploying to the API Gateway, and in most use cases are not necessary.

> The generator does not handle converting API specs (i.e., OpenAPI 3.0 to Swagger 2.0). It is assumed that you have a Swagger 2.0 spec file. If you have an API spec file in a different format, it is recommended to use [api-spec-converter](https://github.com/LucyBot-Inc/api-spec-converter) or another tool to convert it to Swagger 2.0.

The main use case of this tool is an intermediate step in the deployment of an API to Google Cloud Platform's API Gateway. As Google still uses the old Swagger 2.0 spec, and has additional fields that can be added/removed, this tool helps to automate the process of generating a spec file that is compatible with the API Gateway. A common use case for this tool would be on a CI pipeline:

1. Autogenerate spec for your API
2. Convert spec to Swagger 2.0
3. Generate API Gateway spec file
4. Deploy to API Gateway

## Installation

Ensure you have [Composer](https://getcomposer.org/) installed and available in your PATH, as well as PHP 8.0 or later.

### Local

Run the following command in your project root:

```bash
composer require lukedavis/gcp-api-gateway-spec --dev
```

After installing, you can now run the tool using: `./vendor/bin/gcp-api-gateway-spec generate` from your project root.

### Global

Run the following command anywhere in your terminal:

```bash
composer global require lukedavis/gcp-api-gateway-spec
```

After installing, the tool will now be in your composer installation's bin directory at `<composer-home>/vendor/bin/gcp-api-gateway-spec`.

You can view the path to your composer's home directory by running `composer -n config --global home`.

> You can alias the path to the tool or add the composer vendor/bin directory to your PATH in your `.zshrc` or `.bashrc` for easier access.

## Usage

### Requirements

- Swagger 2.0 YAML spec file, passed as the first argument to `generate`
  - If you are working with an OpenAPI 3.0 spec file, I recommend using [api-spec-converter](https://github.com/LucyBot-Inc/api-spec-converter) to create a Swagger 2.0 spec file.
- Configuration file (see [Configuration](#configuration))

### Command

```bash
gcp-api-gateway-spec generate swagger2.yaml \
  --output=api-gateway.yaml \
  --config=config.yaml \
  [--host=api.example.com] \
  [--backend=https://backend.example.com] \
  [--preserve-responses]
```

| Argument / option      | Description                                                                                              |
| ---------------------- | -------------------------------------------------------------------------------------------------------- |
| `input` (argument)     | Path to the input Swagger 2.0 YAML spec file. Required.                                                   |
| `--output`, `-o`       | Where to write the generated spec. See [Output path resolution](#output-path-resolution).                 |
| `--config`, `-c`       | Path to the config file. Required.                                                                        |
| `--host`               | Sets the top-level `host` of the generated spec. Optional.                                                |
| `--backend`, `-b`      | Sets `x-google-backend.address` at the top level and on every operation. Optional.                        |
| `--preserve-responses`, `-p` | Keep the response schemas from the input spec. By default responses are replaced with a generic 200. |

### Output path resolution

- **Absolute path** (e.g. `--output=/tmp/specs/api-gateway.yaml`): used as-is. Missing directories are created.
- **Relative path** (e.g. `--output=build/api-gateway.yaml`): resolved against the current working directory.
- **Directory** (existing): the file is written inside it as `generator-output.yaml`.
- **Omitted**: writes `generator-output.yaml` in the current working directory.

### Examples

Absolute path with filename:

```bash
gcp-api-gateway-spec generate swagger.yaml \
    --output=/tmp/api-gateway.yaml \
    --config=config.yaml
```

With `--preserve-responses` and a relative output to cwd:

```bash
gcp-api-gateway-spec generate swagger.yaml \
    --output=api-gateway.yaml \
    --config=config.yaml \
    --preserve-responses
```

## Configuration

Values in the config file take precedence over the corresponding values in the input spec (`info`, `basePath`, `produces`, `consumes`, `securityDefinitions`, `x-google-backend`). Take a look at the [example config](config.example.yaml) for a practical example.

```yaml
# Optional; taken from the input spec if not set here
info:
  title: My API
  version: "1.0.0"

# Optional, defaults to /
basePath: /v1

# Optional, defaults to application/json
produces:
  - application/json
consumes:
  - application/json

# Define your security definitions here
securityDefinitions:
  auth0:
    authorizationUrl: 'https://example.auth0.com/authorize'
    flow: implicit
    type: oauth2
    x-google-issuer: 'https://example.auth0.com/'
    x-google-jwks_uri: 'https://example.auth0.com/.well-known/jwks.json'
    x-google-audiences: 'https://example.com'

# Top-level x-google-backend (the --backend option overrides its address)
x-google-backend:
  path_translation: 'APPEND_PATH_TO_ADDRESS'

# Default configuration applied to every operation if not overridden
# Useful for setting global security definitions
path-defaults:
  security:
    - auth0: []
  x-google-backend:
    path_translation: 'APPEND_PATH_TO_ADDRESS'

# Path/method specific overrides
# Useful for removing security from specific paths
path-overrides:
  /unsecured-route:
    post:
      consumes:
        - multipart/form-data
      security: []
    get:
      security: []
```

For each operation, the effective spec is built by layering (later wins): `path-defaults` → the matching `path-overrides.<path>.<method>` entry → the operation from the input spec.

## Normalization

Besides merging the config, the generator applies a few normalizations so that the output passes the API Gateway's Swagger 2.0 validation:

- **Responses** are replaced with a generic `200` response unless `--preserve-responses` is passed. Empty `responses` maps are replaced with the generic response as well.
- **Path parameters** that appear in a path template (e.g. `/pets/{petId}`) but are not declared on the operation are added as required string parameters.
- **Nullable types** are rewritten: `type: [string, "null"]` becomes `type: string` with `x-nullable: true`, and `type: "null"` becomes `type: string` with `x-nullable: true`.
- **Unsupported JSON Schema keywords** are removed: `additionalItems`, `patternProperties`, `dependencies`, `propertyNames`, `contains`, `const`, `if`, `then`, `else`.
- **Empty schemas** (e.g. `nickname: {}`, common in specs converted with api-spec-converter) are kept as empty objects instead of degrading to `[]`, which the API Gateway validator rejects. Empty sequences such as `security: []` are unaffected.

## Development

```bash
composer install
vendor/bin/phpunit             # tests (golden files + schema validation)
vendor/bin/phpstan analyse     # static analysis
vendor/bin/php-cs-fixer fix    # code style
```

Generated output is byte-compared against golden files in `tests/fixtures/cases/*/expected.yaml` and validated against the official Swagger 2.0 JSON schema — the same validation `gcloud api-gateway api-configs create` performs. After an intentional output change, regenerate goldens with `UPDATE_GOLDENS=1 vendor/bin/phpunit`.

## Disclaimer

This tool is not officially supported by Google Cloud Platform or the API Gateway team.

This tool is provided as-is and without warranties of any kind. Luke Davis is not responsible for any security issues, vulnerabilities, or other problems that may arise from the use of this tool.

Users are responsible for ensuring the security and suitability of this tool for their specific needs and use cases. Use at your own risk.
