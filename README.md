# GCP API Gateway Spec Generator

This is a simple tool that:

- Takes a Swagger 2.0 spec file
- Takes a configuration file
- Generates a new Swagger 2.0 spec file with API Gateway specific properties
- Optionally (recommended), strips responses from the original spec file, and replaces them with generic 200 responses.
    - >Complicated responses are a constant source of errors when deploying to the API Gateway, and in most use cases are not necessary.

The generator does not handle converting API specs (i.e., OpenAPI 3.0 to Swagger 2.0). It is assumed that you have a Swagger 2.0 spec file. If you have an API spec file in a different format, it is recommended to use api-spec-converter or another tool to convert it to Swagger 2.0.

The main use case of this tool is an intermediate step in the deployment of an API to Google Cloud Platform's API Gateway. As Google still uses the old Swagger 2.0 spec, and then also has additional fields that can be added/removed, this tool helps to automate the process of generating a spec file that is compatible with the API Gateway. A common use case for this tool would be:

CI Pipeline:
```
Autogenerate spec for your API -> convert spec to Swagger 2.0 -> Generate API Gateway spec file -> Deploy to API Gateway
```

## Disclaimer

This tool is not officially supported by Google Cloud Platform or the API Gateway team.

This tool is provided as-is and without warranties of any kind. Luke Davis is not responsible for any security issues, vulnerabilities, or other problems that may arise from the use of this tool.

Users are responsible for ensuring the security and suitability of this tool for their specific needs and use cases. Use at your own risk.

## Installation

Ensure you have [Composer](https://getcomposer.org/) installed and available in your PATH, as well as PHP 8.0 or later.

```bash
composer require -g lukedavis/gcp-api-gateway-spec
```

## Configuration

Below are the configuration options, take a look at the `config.example.yaml` file for a more practical example.

```yaml
# Define your security definitions here
securityDefinitions: []

# Default configuration applied to all paths if not overridden
# Useful for setting global security definitions
default-path:
  security: []

# Path/method specific overrides
# Useful for setting security definitions on specific paths
override-paths: []
```

## Usage

### Requirements

- Swagger 2.0 YAML spec file
    - If you are working with an OpenAPI 3.0 spec file, I recommend using [api-spec-converter](https://github.com/LucyBot-Inc/api-spec-converter) to create a Swagger 2.0 spec file.
- Configuration file (see config.example.yaml)
- Output
  - Can be absolute path, relative path, or either + a filename. If no filename is provided, the generated file will be named `generator-output.yaml`.

### Command

```bash
gcp-api-gateway-spec generate \
  --input=swagger.yaml \
  --output=api-gateway.yaml \
  --config=config.yaml \
  [--preserve-responses]
```

### Examples

Absolute Path w/ Filename:
```bash
gcp-api-gateway-spec generate \
    --input=swagger.yaml \
    --output=/tmp/api-gateway.yaml \
    --config=config.yaml
```

With the `--preserve-responses` flag and a relative output to cwd:

```bash
gcp-api-gateway-spec generate \
    --input=swagger.yaml \
    --output=api-gateway.yaml
    --config=config.yaml \
    --preserve-responses
```
