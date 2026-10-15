# CDM — Diagnostic Service

ChAoS Foundation PHP-native administrator-only diagnostic module.

## Scope

- Administrative access via `/admin/cdm`, using the framework's `require_admin(7)` authorization and CSRF validation.
- Read-only inspection of installed modules.
- JSON metadata syntax diagnostics, conventional controller presence checks, and a preliminary index-parameter mismatch heuristic.
- Findings show module, route, code location, summary, evidence, recommendation, and confidence classification.
- No automatic repairs, database changes, or Core modifications.

## Limitations

This module may miss code base requirements.

## Purpose
To move along with each version of the ChAoS MVC without needing to be updated as it simply:
- Scans the `ChAoS MVC Core` for its **Code Base**
  - Then scans `/user/modules/` for installed modules
  - Allows selection of each module in `/user/modules` to be tested


