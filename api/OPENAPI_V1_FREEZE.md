# API Contract Freeze (Phase 0)

This repository currently uses API Platform resources plus custom controllers.

## Contract policy

- v1 field names are frozen at the API boundary.
- New fields can be added as backward-compatible optional fields.
- Renames or removals require a new versioned endpoint namespace.

## Read/write serialization groups

All core entities now expose explicit serializer groups:

- read: response payload fields
- write: accepted request payload fields

Entities covered:

- Project
- ContextOfUseCard
- NAMStudy
- EvidenceItem
- ClaimNode
- ClaimEdge
- ECTDMapping
- ExportPackage (read only)

## Verification steps

When Symfony console tooling is available in the runtime image, export and diff the OpenAPI document:

1. Generate schema (example command):

   php bin/console api:openapi:export --spec-version=3 --output=openapi-v1.json

2. Compare with previous frozen artifact before merge.

3. If breaking changes are detected, either:

   - revert to maintain v1 compatibility, or
   - introduce a new versioned namespace and document migration path.

## Current custom endpoints to keep stable

- POST /api/projects/{id}/export
- POST /api/projects/{id}/export/download
- GET /api/projects/{id}/export/history
- GET /api/v1/projects
- POST /api/v1/projects
- GET /api/v1/projects/{id}/workspace
- PUT /api/v1/projects/{id}/cou/{couId}
- PUT /api/v1/projects/{id}/evidence/{evidenceId}
- PUT /api/v1/projects/{id}/claims/{claimId}/status
