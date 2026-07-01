# NAM-CORE Standardization Toolkit — Implementation Plan

Transform nam2evidence from a regulatory evidence-packaging prototype into a
modular, FAIR-by-design, ontology-linked, validator-driven NAM standardization toolkit —
**without breaking the existing five workspaces or demo mode**.

> Positioning: this is a technical proof-of-concept for **standardizing** NAM-derived
> nonclinical data. It does **not** claim FDA acceptance, SEND/CDISC compliance, or
> automatic IND readiness. Standardization ≠ validation ≠ regulatory interpretation.

## Repository audit (Phase 1 — done)

Current backend (`api/`, Symfony 7 + API Platform + Doctrine, PHP 8.4, ULID ids):
- Entities: `Project`, `ContextOfUseCard`, `NAMStudy` (JSONB-heavy), `EvidenceItem`,
  `ClaimNode`, `ClaimEdge`, `ECTDMapping`, `ExportPackage`.
- Controllers: `WorkspaceController`, `NAMOImportController`, `ValidationController`
  (3-tier structural/ontology/business), `ClaimReviewController`, `ExportController`
  (json/csv/md/txt + history), plus `Service/Export/ExportGate` and
  `Service/Validation/CurieValidator`.
- Demo: `app:load-demo-data` (COU-HEP-001 / CX-4471 liver organoid).
- Tests: 26 unit + 1 integration (PHPUnit 10), all green.

Current frontend (`frontend/`, Next.js 15, App Router, TS strict, Tailwind):
- Workspaces: Overview, Context of Use, NAM Study, Import NAMO, Validation Matrix,
  Claim Graph, eCTD Mapping, Export Center (`components/Sidebar.tsx` nav).
- Data layer: `lib/store.tsx` (demo-mode + api-mode), `lib/api.ts`, `lib/types.ts`,
  `lib/demoData.ts`.

**Strategy:** additive, low-invasive. Keep every existing entity/route/workspace.
Add a NAM-CORE data-standardization layer *underneath* the five workspaces, new
`/api/v1/...` routes, new frontend workspaces, and a Python sidecar for SHACL + Parquet.

## Phase 2 — NAM-CORE v0.1 backend schema
New Doctrine entities (explicit columns, JSONB only for `extensions`):
`BiologicalSystem`, `Donor`, `CellSource`, `Platform`, `Device`, `Assay`, `Sample`,
`Exposure`, `EndpointMeasurement`, `QCResult`, `OntologyTerm`, `OntologyMapping`,
`ProvenanceActivity`, `RawDataFile`, `AnalysisScript`, `AuditLog`.
Reuse `Project`/`ContextOfUseCard`/`NAMStudy`/`EvidenceItem`(→ValidationEvidence)/
`ClaimNode`(→EvidenceClaim)/`ExportPackage`.
Shared trait `NamCoreFields` (label, description, version, validationStatus,
created/updated, extensions JSONB). Hand-written migration `Version20260701*`.
API Platform resources scoped to a Project.

## Phase 3 — Endpoint data standardization
`POST /api/v1/projects/{id}/endpoint-measurements/import` (CSV): preview columns →
map to NAM-CORE fields → validate required → normalize units → store → summary.
`EndpointMeasurementImporter` service + `UnitNormalizer`. Frontend
`/projects/[id]/endpoints` workspace: upload, mapping UI, preview, errors, summary.

## Phase 4 — Ontology mapping
`OntologyTerm` + `OntologyMapping`. Seed `standards/vocab/nam-seed-vocabulary.json`
(liver-organoid terms → CL/UBERON/ChEBI/OBI/MONDO/NCIT/UCUM/NCBITaxon).
`GET/POST /api/v1/ontology/terms`, `POST /api/v1/ontology/map`,
`PATCH /api/v1/ontology/mappings/{id}/approve|reject`. Frontend mapping panel;
block "AI-ready" while mandatory terms unmapped.

## Phase 5 — Validation (structural + SHACL semantic)
`standards/shacl/nam-core-v0.1.ttl` (COU, biological system, exposure, endpoint,
claim, provenance shapes). Python sidecar `services/validator` (pyshacl + rdflib)
in Docker Compose. `POST /api/v1/projects/{id}/semantic-validation` returns
errors/warnings with entity/field/rule/fix/blocking. Frontend "Semantic Validation" tab.

## Phase 6 — FAIR / AI-readiness scoring
`ReadinessScorer` service, 10 dimensions (0/1/2). `GET /api/v1/projects/{id}/readiness-report`.
Frontend Readiness Dashboard (bar chart, gaps, blockers). Labelled "POC FAIR/AI-readiness assessment."

## Phase 7 — Exports
`GET /api/v1/projects/{id}/exports/{jsonld,turtle,ro-crate,isa-tab,parquet}`.
JSON-LD context `standards/context/nam-core.context.jsonld`. RO-Crate + ISA-Tab as ZIP.
Parquet via Python sidecar. Export Center download buttons + "what's included".

## Phase 8 — Review gates + audit trail
Strengthen `ExportGate` (COU fields, unresolved ontology, blocking validation, provenance
gaps, pending claims). Export status: draft / internally reviewed / ready for regulatory
review / archived snapshot. `AuditLog` entity + `AuditLogger` service +
`GET /api/v1/projects/{id}/audit-log`. Strengthen eCTD mapper fields. Frontend audit + snapshot views.

## Phase 9 — Docs, demo, tests
Docs: README refresh + `docs/NAM_CORE_SCHEMA.md`, `ONTOLOGY_MAPPING.md`,
`VALIDATION_RULES.md`, `EXPORTS.md`, `REGULATORY_POSITIONING.md`, `POC_DEMO_SCRIPT.md`.
Demo files under `demo/` (raw CSVs + seed json + expected report) with deliberate issues
(missing unit, unmapped endpoint, missing passage, pending claim, missing provenance) plus a
corrected state. Backend + frontend + integration tests.

## Constraints honoured
No broken demo; five workspaces preserved; TS strict kept; no hardcoded regulatory
conclusions; explicit schemas over opaque JSON; conservative regulatory language throughout.
