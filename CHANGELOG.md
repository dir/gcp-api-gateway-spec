# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.4] - 2026-07-07

### Fixed

- Empty schema objects (e.g. `nickname: {}`, produced in the wild by
  api-spec-converter when it drops OpenAPI 3 `anyOf` constructs) are no
  longer dumped as empty sequences (`[]`), which `gcloud api-gateway
  api-configs create` rejects with `instance type (array) does not match any
  allowed primitive type (allowed: ["object"])`. Empty arrays in schema
  positions (definitions, properties, `schema`, `items`,
  `additionalProperties`, `allOf`) are now emitted as empty objects
  (`{  }`). Genuine empty sequences such as `security: []`, and any content
  inside `x-` vendor extensions, are unchanged.
- Properties literally named `type` (e.g. `properties.type: {type: string}`)
  are no longer collapsed to their first value, which produced specs the API
  Gateway validator rejects. This upstreams the composer patch previously
  carried by downstream consumers, with identical semantics: any map-valued
  `type` key is preserved — including inside free-form data (response
  `examples`, schema `default`/`enum` values, `x-` vendor extensions), where
  v2.0.3 silently corrupted such values to their first element. Genuine
  `type` declarations (strings and lists) are normalized exactly as before.
- A property named `type` with an *empty* schema (`type: {}`) is now emitted
  as an empty object instead of the invalid `type: false`.
- An empty `responses` map is now replaced with the generic 200 response
  instead of being emitted as an invalid empty sequence.
- Absolute `--output` paths pointing to files that don't exist yet are now
  used as-is instead of being resolved relative to the current working
  directory (`--output=/tmp/x/spec.yaml` used to write to
  `<cwd>//tmp/x/spec.yaml`). Relative path resolution is unchanged.
- The tool no longer fatals on PHP 8.0 with symfony/yaml 6.0–6.2, where
  `Yaml::DUMP_NUMERIC_KEY_AS_STRING` does not exist. On those versions,
  numeric keys (e.g. response codes) are dumped unquoted; on symfony/yaml
  ≥ 6.3 output is unchanged.

### Added

- Test suite: golden-file characterization tests, validation of generated
  output against the official Swagger 2.0 JSON schema (the same validation
  gcloud performs), CLI contract tests, and GitHub Actions CI covering
  PHP 8.0–8.4 with lowest/highest/locked dependencies.
- `CHANGELOG.md`, expanded README (output path semantics, configuration
  reference, normalization behavior).

### Changed

- Allow symfony/console and symfony/yaml `^8.0` (additive; existing
  installations are unaffected). Note that symfony/yaml 8 dumps nested
  sequences in a more compact style — output remains semantically identical
  and valid.
- Refreshed dev dependencies (PHPStan 2.x with strict rules, current
  php-cs-fixer) and the composer lock file (clears symfony/yaml security
  advisories CVE-2026-45304, CVE-2026-45305, CVE-2026-45133 in the dev
  lock).

### Upstream note

- symfony/yaml releases containing the CVE fixes above enforce parser
  hardening limits (YAML alias count, nesting depth ≤ 128). Extremely deep
  or alias-heavy input specs that parsed on older symfony/yaml versions may
  now be rejected at parse time. This applies to v2.0.3 equally once its
  (identical) `^6.0|^7.0` constraint resolves to a patched symfony/yaml.

## [2.0.3] - 2025-01-18

Last release before this changelog was introduced. See the git history for
details.

[2.0.4]: https://github.com/dir/gcp-api-gateway-spec/compare/v2.0.3...v2.0.4
[2.0.3]: https://github.com/dir/gcp-api-gateway-spec/releases/tag/v2.0.3
