# Validation Rules

> **Positioning.** Validation here means **checking that the standardized data is
> structurally complete, semantically consistent, and human-reviewed enough to
> package** — it is *not* scientific validation of the NAM method, nor a regulatory
> determination. A conforming project is a *review-supportive* structured evidence
> package, not an IND-ready or FDA-accepted submission. Rules are a
> proof-of-concept ruleset, **not an official regulatory or SEND schema.**

Validation runs in three layers, from cheap structural checks to human review
gates.

---

## Layer A — Structural / required-field checks (JSON-schema-style)

Applied at import and record creation. The **EndpointMeasurementImporter**
(`api/src/Service/NamCore/EndpointMeasurementImporter.php`) enforces the
import-time minimum:

- Required mapped fields: `endpoint_id`, `value`, `unit`. A missing mapping is a
  **blocking** import error.
- `value` must be numeric; non-numeric values (e.g. `QNS`) are preserved in
  `valueRaw`, flagged as errors, and set `validationStatus = errors`.
- Missing `unit` → warning, `validationStatus = warnings`; the row cannot be
  AI-ready until a unit is mapped or justified.
- Units are passed through `UnitNormalizer`; unknown units raise a warning and
  need ontology mapping/justification.
- Business-key references (study, sample, assay, exposure, donor, raw file) are
  resolved softly — a miss is a warning and the raw key is stashed in
  `extensions.unresolved_*`, so gaps stay visible rather than aborting the run.

Entity-level `#[Assert\NotBlank]` / `#[Assert\Choice]` constraints (e.g.
`endpointId`, `modelSystemType`, `testArticle`, claim `confidence`) are enforced by
Symfony validation on write.

---

## Layer B — SHACL semantic validation

Semantic rules live in `standards/shacl/nam-core-v0.1.ttl` and are evaluated
against the JSON-LD graph produced by
`GET /api/v1/projects/{id}/exports/jsonld`. Each SHACL violation carries an
`sh:message` the API surfaces as `{rule, recommended_fix}`. Severity `sh:Violation`
is **blocking**; `sh:Warning` is advisory.

### Shapes (plain English)

| Shape | Rules |
|---|---|
| **ProjectShape** | Every project must declare **exactly one** ContextOfUse (blocking). |
| **ContextOfUseShape** | ContextOfUse must include `decision_question`, `intended_use`, `biological_domain`, and `regulatory_support_level` (all blocking). |
| **BiologicalSystemShape** | Must declare `model_system_type` (blocking); must declare a species **or** a cell source (blocking); *should* map `cellType` to an ontology term (warning). |
| **OrganoidCellSourceShape** | Organoid / organ-on-chip / tissue-on-chip systems must declare a cell source (blocking, SPARQL). |
| **ExposureShape** | Must have a `test_article` (blocking); any concentration must have a unit (blocking); any timepoint must have a unit (blocking). |
| **EndpointMeasurementShape** | `value` present and numeric (blocking); `unit` present, mapped or justified (blocking); linked to an `endpoint` definition (blocking); *should* link to a sample and an assay (warnings); must link to a raw data file **or** an analysis activity — provenance (blocking, SPARQL). |
| **EvidenceClaimShape** | Every claim must have a `review_status` (blocking); decision-informing / potentially-pivotal claims must link to validation evidence (blocking, SPARQL); export is blocked while any claim is `human_review_required` (blocking, SPARQL). |
| **AnalysisActivityShape** (Provenance) | Every analysis activity must include a software name **or** a script reference (blocking, SPARQL). |

### PHP-native validator mirrors the SHACL rules

The **SemanticValidator** (`api/src/Service/NamCore/SemanticValidator.php`) is a
PHP re-implementation of the same rules. It runs **in-process** (no sidecar
required), makes each rule queryable, drives the review gate, and returns
navigable issues (each carries the `workspace` the offending entity lives in).
Endpoint: `GET|POST /api/v1/projects/{id}/semantic-validation`.

### Optional `pyshacl` sidecar — formal RDF second opinion

The Docker service `services/validator` (pyshacl + rdflib) evaluates the *same*
`nam-core-v0.1.ttl` shapes against the exported JSON-LD graph for a formal RDF
result. It is called via `ValidatorSidecarClient` and is **entirely optional**:
if `VALIDATOR_URL` is unset the API responds gracefully.

The `semantic-validation` response embeds:

```json
"shacl_sidecar": { "available": true, "conforms": true, "violation_count": 0 }
// or, when the sidecar is not configured:
"shacl_sidecar": { "available": false, "note": "pyshacl sidecar not configured; PHP-native validation used." }
```

---

## Layer C — Scientific QC + review gates

Beyond structure and semantics, the package must be **human-reviewed** enough to
package. QC lives on the data (`EndpointMeasurement.qcStatus`
`pending|pass|fail|warn`, `exclusionStatus`, and `QCResult` records), and the
**ExportReadinessGate** (`api/src/Service/Export/ExportReadinessGate.php`)
aggregates blockers.

### Review-gate blockers

A project's formal package is **blocked** while any of the following hold:

1. **Human review** — any claim is `human_review_required` or `pending`.
2. **Context of Use** — mandatory COU fields are missing.
3. **Unsupported claims** — a decision-informing / potentially-pivotal claim lacks
   linked validation evidence.
4. **Ontology** — a mandatory ontology mapping is unresolved (not `approved`).
5. **Semantic errors** — any blocking semantic-validation error.
6. **Endpoint structure** — unresolved structural errors on endpoint data
   (missing value/unit).
7. **Provenance** — processed endpoint data with no raw file or analysis activity.

The gate never asserts *regulatory adequacy* — only that the standardization
package is **internally complete and human-reviewed enough to package**.

- **Draft formats always allowed:** `json`, `markdown`/`md`, `validation-report`.
- **Formal formats blocked while the gate is closed:** `ro-crate` (complete),
  `ectd-package`, `dossier`.
- `export_status` is reported as `draft` (blocked) or `internally_reviewed`.

Endpoints:
`GET /api/v1/projects/{id}/export-gate`,
`GET /api/v1/projects/{id}/readiness-report` (POC FAIR/AI-readiness),
`GET /api/v1/projects/{id}/audit-log`.

---

## Report shape

`GET|POST /api/v1/projects/{id}/semantic-validation` returns:

```json
{
  "schema": "NAM-CORE v0.1",
  "conforms": false,
  "error_count": 4,
  "warning_count": 2,
  "blocking_count": 4,
  "completion_percentage": 86,
  "errors": [ /* issues with severity=error */ ],
  "warnings": [ /* issues with severity=warning */ ],
  "issues": [
    {
      "severity": "error",
      "blocking": true,
      "rule": "EndpointMeasurementShape",
      "workspace": "endpoints",
      "entity": "EndpointMeasurement:ldh_release#1a2b3c4d",
      "field": "unit",
      "message": "Endpoint measurement has no unit.",
      "recommended_fix": "Map or justify a unit for this endpoint."
    }
  ],
  "shacl_sidecar": { "available": false, "note": "..." }
}
```

Every issue is `{severity, blocking, rule, workspace, entity, field, message,
recommended_fix}` so the frontend can navigate directly from a violation to the
offending record.

---

## Audit trail

Every validate/import/export/approve/reject/review-gate action is recorded in the
append-only `AuditLog` (`AuditLogger` service). Entries carry `entityType`,
`entityId`, `action`, `oldValue`, `newValue`, `userOrRole`, `reason`, and
`timestamp`, and are retrievable via `GET /api/v1/projects/{id}/audit-log`.

## Related documents

- [NAM_CORE_SCHEMA.md](./NAM_CORE_SCHEMA.md)
- [ONTOLOGY_MAPPING.md](./ONTOLOGY_MAPPING.md)
- [EXPORTS.md](./EXPORTS.md)
- [REGULATORY_POSITIONING.md](./REGULATORY_POSITIONING.md)
