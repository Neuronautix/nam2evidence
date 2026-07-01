# Changelog — NAM-CORE standardization layer

This release refactors the NAMO-to-IND Mapper from a regulatory evidence-packaging
prototype into a modular, FAIR-by-design, ontology-linked, validator-driven NAM
standardization toolkit. **It is additive**: the five existing workspaces, the demo, the
existing entities/routes/exports, and TypeScript strictness are all preserved.

> Positioning: a proof-of-concept for **standardizing** NAM-derived nonclinical data. It
> makes no claim of FDA acceptance, SEND/CDISC compliance, or automatic IND readiness.
> Standardization ≠ validation ≠ regulatory interpretation ≠ human approval.

## Architecture summary

```
frontend (Next.js 15)  ──HTTP──►  api (Symfony 7 / API Platform)  ──►  PostgreSQL 16
   11 legacy + 6 new                 legacy REST + /api/v1/* NAM-CORE
   workspaces                                  │
                                               └──(optional)──►  validator sidecar
                                                                  (Flask + pyshacl + pyarrow)
standards/   JSON-LD context · SHACL shapes · seed vocabulary
demo/        synthetic dataset with deliberate + corrected states
docs/        schema · ontology · validation · exports · positioning · demo script
```

The NAM-CORE layer sits **underneath** the existing workspaces: raw NAM data →
canonical NAM-CORE schema → ontology-linked metadata → provenance graph → validation/QC →
FAIR/AI-readiness → evidence package → eCTD mapping → reusable exports.

## New backend entities (`App\Entity\NamCore`, table prefix `namcore_`)
BiologicalSystem, Donor, CellSource, Platform, Device, Assay, Sample, Exposure,
**EndpointMeasurement** (canonical SEND-like tabular core), QCResult, OntologyTerm,
OntologyMapping, ProvenanceActivity, RawDataFile, AnalysisScript, AuditLog — all sharing
`NamCoreEntityTrait` (stable id, label, description, version, validationStatus, JSONB
`extensions`, timestamps). Explicit queryable columns; JSONB only for extension fields.
`ECTDMapping` gained structured placement fields (module, document_type,
evidence_package_component, placement_rationale, claim/evidence id lists, reviewer_status,
caveat). Migrations `Version20260701000001..0003`.

## New endpoints (`/api/v1`)
- `POST /projects/{id}/endpoint-measurements/import` — CSV preview → column mapping →
  validate → unit-normalize → store → summary
- `GET  /projects/{id}/endpoint-measurements`
- `GET/POST /ontology/terms`, `POST /ontology/map`,
  `PATCH /ontology/mappings/{id}/approve|reject`, `GET /projects/{id}/ontology-mappings`
- `GET  /projects/{id}/semantic-validation` (PHP-native + optional pyshacl sidecar)
- `GET  /projects/{id}/readiness-report` (10-dimension POC FAIR/AI-readiness)
- `GET  /projects/{id}/export-gate` — aggregated draft-vs-formal blockers
- `GET  /projects/{id}/audit-log`
- `GET  /projects/{id}/exports/{jsonld,turtle,isa-tab,parquet,ro-crate}`

## New frontend screens (`/projects/[id]/…`)
`endpoints` (Endpoint Data Standardization), `ontology`, `semantic-validation`,
`readiness`, `provenance`, `audit`; Export Center gained NAM-CORE download buttons; Sidebar
gained the six nav items.

## New services / standards / demo
- Services: `EndpointMeasurementImporter`, `UnitNormalizer`, `ProjectGraphBuilder`,
  `SemanticValidator`, `ReadinessScorer`, `ExportReadinessGate`, `TurtleSerializer`,
  `IsaTabExporter`, `RoCrateBuilder`, `ValidatorSidecarClient`, `AuditLogger`.
- `services/validator/` — Flask + pyshacl + pyarrow sidecar (SHACL `/validate`, Parquet
  `/parquet`), wired into `docker-compose.yml` (optional via `VALIDATOR_URL`).
- `standards/` — JSON-LD context, `nam-core-v0.1.ttl` SHACL shapes, seed vocabulary.
- `demo/` — raw + corrected endpoint CSVs and supporting files with deliberate blockers.
- Commands: `app:load-namcore-demo [--corrected]`, `app:load-ontology-seed`.

## How to run locally
```bash
docker compose up --build                    # db + api + validator + frontend
docker compose exec api php bin/console app:load-demo-data --force
docker compose exec api php bin/console app:load-ontology-seed
docker compose exec api php bin/console app:load-namcore-demo         # add --corrected for the resolved state
# frontend http://localhost:3000 · api http://localhost:8080 · validator http://localhost:8000
```

## Tests
`cd api && vendor/bin/phpunit` — 44 tests / 147 assertions green (unit: UnitNormalizer,
importer preview, Turtle, RO-Crate; integration `NamCoreApiTest`: import, ontology
approve/reject, validation, readiness, gate, all exports, audit). Frontend: `npm run build`
and `npx tsc --noEmit` clean.

## Known limitations
- Readiness scoring is a POC heuristic, not a certified metric.
- The PHP-native `SemanticValidator` mirrors the SHACL shapes; the pyshacl sidecar is the
  formal RDF second opinion and is optional.
- `TurtleSerializer` is a pragmatic emitter, not a full JSON-LD 1.1 processor.
- Ontology mapping uses a local seed vocabulary (no live term resolution).
- Business-key reference resolution on import is best-effort (unresolved keys → warnings).

## Recommended next steps
- Live ontology resolution (OLS/BioPortal) behind the seed cache.
- Persisted export snapshots per format with immutable versioned RO-Crates.
- Expand SHACL coverage (QC thresholds, cross-donor consistency) and add SEND-like domain
  mapping as an explicit, clearly-labelled non-official view.
- Per-entity ontology-mapping UI wired into the NAM Study and Endpoint workspaces.
