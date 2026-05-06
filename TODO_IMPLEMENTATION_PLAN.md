# NAMO-to-IND Mapper - Precise Implementation Plan (Todo)

Last updated: 2026-05-06
Planning source: initial_prompt.md + current repository state

## 1) Current state review (completed)

### 1.1 Prompt and planning baseline
- [x] Reviewed and validated the implementation planning brief in initial_prompt.md.
- [x] Confirmed the brief is aligned with NAMO-first architecture and COU-driven regulatory packaging.

### 1.2 Repository state inspection
- [x] Confirmed monorepo layout with existing backend and frontend:
  - [x] api (Symfony 7 + API Platform + Doctrine)
  - [x] frontend (Next.js + React + TypeScript + Tailwind)
- [x] Confirmed Docker Compose stack with PostgreSQL 16, API service, and frontend service.
- [x] Confirmed initial backend migration exists and already creates core domain tables.
- [x] Confirmed backend domain model already includes:
  - [x] Project
  - [x] ContextOfUseCard
  - [x] NAMStudy
  - [x] EvidenceItem
  - [x] ClaimNode
  - [x] ClaimEdge
  - [x] ECTDMapping
  - [x] ExportPackage
- [x] Confirmed export endpoint exists and enforces human-review gate for claims.
- [x] Confirmed frontend already implements all five workspaces plus export center.
- [x] Confirmed frontend currently operates on local demo store and does not yet integrate with backend API.

### 1.3 Gap assessment (high priority)
- [x] Identified architecture mismatch between README claim and implementation reality:
  - [x] UI currently local-state driven (demo data), not API-persisted.
  - [x] Backend entities do not yet expose explicit serializer groups in inspected files.
  - [x] Export API currently fetches all ECTD mappings globally (not project-scoped).
- [x] Identified model alignment gaps:
  - [x] Frontend NAMModelType differs from backend/brief NAMO class taxonomy.
  - [x] Claim edge vocabulary differs from brief target vocabulary.
- [x] Identified missing quality gates:
  - [x] No automated cross-stack integration tests currently documented.
  - [x] No explicit ontology term validation pipeline implemented.

## 2) Target architecture decisions (locked)

### 2.1 Runtime and stack
- [x] Keep current stack (no replatform):
  - [x] Frontend: Next.js 15 + TS + Tailwind
  - [x] Backend: Symfony 7 + API Platform + Doctrine ORM
  - [x] DB: PostgreSQL 16
  - [x] Orchestration: Docker Compose

### 2.2 Data strategy
- [x] Keep relational core entities for auditability and traceability.
- [x] Keep JSON/JSONB for flexible NAMO payload segments.
- [x] Adopt hybrid model policy:
  - [x] Canonical regulated fields remain first-class columns where already defined.
  - [x] NAMO-rich substructures remain JSON payloads with validation rules.

### 2.3 Integration strategy
- [x] Frontend moves from demo-only local store to API-backed data access.
- [x] Demo data retained as optional seed/bootstrap mode.
- [x] Export generation remains backend responsibility only.

## 3) Delivery plan by phase

## Phase 0 - Foundation hardening (Sprint 1)

### 0.1 API serialization and contract stabilization
- [x] Add explicit serializer groups for all API resources (read/write) and sensitive-field control.
- [x] Ensure all relations needed by frontend are exposed in predictable shape.
- [x] Publish/verify OpenAPI schema and freeze v1 field names.

Acceptance criteria:
- [x] GET collection and item responses are stable and documented.
- [x] POST/PUT payload examples work for all core entities.
- [x] Frontend can consume API without ad hoc field transformation hacks.

### 0.2 Backend correctness fixes
- [x] Scope eCTD mapping retrieval by project in export flow.
- [x] Add backend validation for consistency:
  - [x] ClaimNode context_of_use belongs to same Project.
  - [x] EvidenceItem study belongs to same Project context chain.
  - [x] ECTDMapping references claim/study from same project.
- [x] Add uniqueness/integrity checks where missing.

Acceptance criteria:
- [x] Export payload contains only project-owned records.
- [x] Invalid cross-project references are rejected with 4xx.
- [x] No silent data contamination across projects.

### 0.3 Migration and schema audit
- [x] Generate follow-up migration(s) for any discovered schema drift.
- [x] Add DB indexes for likely query paths:
  - [x] claim_nodes(project_id, review_status)
  - [x] evidence_items(study_id, domain, status)
  - [x] ectd_mappings(study_id, claim_id, ectd_section)
- [x] Confirm nullable/required columns match business rules.

Acceptance criteria:
- [x] Fresh boot + migrations succeed in clean environment.
- [x] Existing data migrates without loss.

## Phase 1 - Frontend to API integration (Sprint 2)

### 1.1 Data access layer
- [x] Implement typed API client in frontend/lib.
- [x] Replace direct local-store CRUD calls with API calls for:
  - [x] Projects
  - [x] COU cards
  - [x] NAM studies
  - [x] Evidence items
  - [x] Claim nodes/edges
  - [x] ECTD mappings
- [x] Keep local storage only for UI state (non-domain data) where needed.

Acceptance criteria:
- [x] Create/edit actions persist to backend.
- [x] Page refresh retains server state.
- [x] API error states are rendered with user-readable messages.

### 1.2 Incremental cutover strategy
- [x] Add feature flag for demo mode vs API mode.
- [x] Keep existing demo dataset available for standalone demos.
- [x] Add empty-state flows for brand-new projects with no data.

Acceptance criteria:
- [x] Demo mode still functions.
- [x] API mode becomes default in compose/dev environment.

### 1.3 UI consistency and taxonomy alignment
- [x] Align frontend NAM model enumerations with backend and NAMO targets.
- [x] Align claim edge relationship vocabulary with product brief decisions.
- [x] Normalize confidence/review labels and color semantics across screens.

Acceptance criteria:
- [x] Same domain values accepted in both frontend and backend.
- [x] No transformation layer required for enum mismatch.

## Phase 2 - Validation and regulatory logic (Sprint 3)

### 2.1 Evidence matrix rule engine (MVP)
- [ ] Implement service-level rule evaluation for mandatory evidence domains.
- [ ] Add computed matrix completeness and pass status endpoints.
- [ ] Add support-level gating rules tied to evidence status thresholds.

Acceptance criteria:
- [ ] System returns deterministic readiness state per project.
- [ ] Missing mandatory rows block claim promotion/export as configured.

### 2.2 Human review workflow hardening
- [ ] Enforce claim lifecycle transitions server-side.
- [ ] Record reviewer metadata (who/when/decision/comment).
- [ ] Expose review timeline/audit API.

Acceptance criteria:
- [ ] Illegal transitions are rejected.
- [ ] Export blocked until all required claims approved.
- [ ] Review audit is queryable.

### 2.3 eCTD mapping governance
- [ ] Add constrained section dictionary for MVP list.
- [ ] Require justification field for each mapping.
- [ ] Validate claim/study to section compatibility rules.

Acceptance criteria:
- [ ] Every mapping is explainable and traceable.
- [ ] Invalid section codes rejected at API boundary.

## Phase 3 - NAMO ingestion and ontology validation (Sprint 4)

### 3.1 NAMO example import pipeline
- [ ] Add import endpoint/service for NAMO-style YAML/JSON payloads.
- [ ] Map imported fields into NAMStudy + related entities.
- [ ] Store raw source payload for audit and reprocessing.

Acceptance criteria:
- [ ] At least one hepatic organoid example imports end-to-end.
- [ ] Imported data appears correctly in study, evidence, and mapping views.

### 3.2 Ontology validation (MVP scope)
- [ ] Implement CURIE syntax validation for ontology-linked IDs.
- [ ] Implement allow-list prefixes (UBERON, CL, MONDO, CHEBI, ECO, NCBITaxon, OBI).
- [ ] Mark unresolved/invalid ontology terms with warnings/errors.

Acceptance criteria:
- [ ] Invalid identifiers fail validation with actionable messages.
- [ ] Valid identifiers are preserved unchanged through export.

### 3.3 Provenance and reproducibility requirements
- [ ] Enforce minimal provenance fields on NAMStudy records.
- [ ] Require reference capture (DOI/PMID/URL) for key evidence rows.
- [ ] Add data completeness score for import quality.

Acceptance criteria:
- [ ] Study cannot be marked complete without required provenance.
- [ ] Export includes provenance and references in package payload.

## Phase 4 - Export package maturity (Sprint 5)

### 4.1 Export formats and API parity
- [ ] Keep backend JSON snapshot export as canonical.
- [ ] Add backend-generated CSV + Markdown outputs matching frontend semantics.
- [ ] Add endpoint for eCTD folder map text output.

Acceptance criteria:
- [ ] Same source of truth used for all exported formats.
- [ ] Frontend download actions call backend artifacts (not client-only synthesis).

### 4.2 Traceability guarantees
- [ ] Ensure each exported claim links to evidence IDs and COU.
- [ ] Ensure each eCTD mapping links to claim/study and rationale.
- [ ] Add export manifest with checksums + schema version.

Acceptance criteria:
- [ ] Audit trail from export package to source records is complete.
- [ ] Export history endpoint shows reproducible package snapshots.

### 4.3 Module 2 support links
- [ ] Include references for 2.4 and 2.6 summaries in export metadata.
- [ ] Annotate where narrative synthesis is required by human authoring.

Acceptance criteria:
- [ ] Export package clearly indicates Module 4 placement and Module 2 linkage.

## Phase 5 - Quality, security, and release readiness (Sprint 6)

### 5.1 Test coverage
- [ ] Backend unit tests for validators, review gate, and export builder.
- [ ] Backend API tests for CRUD + workflow transitions.
- [ ] Frontend component tests for matrix, claims, export gate UI.
- [ ] End-to-end integration smoke tests (compose environment).

Acceptance criteria:
- [ ] CI pipeline passes on main workflows.
- [ ] Regression tests cover export blocking and project scoping.

### 5.2 Security and hardening
- [ ] Add input sanitization and strict validation across write endpoints.
- [ ] Review CORS and environment defaults for non-dev deployment.
- [ ] Add rate limiting and request logging policy for API.

Acceptance criteria:
- [ ] No critical security findings in baseline review.
- [ ] Production env checklist documented.

### 5.3 Documentation and handover
- [ ] Update README to reflect real API-backed behavior and setup modes.
- [ ] Add architecture decision record(s) for key choices.
- [ ] Add operator runbook for migrations, seed data, and rollback.

Acceptance criteria:
- [ ] New contributor can run system and validate flow from docs only.

## 4) Workstream backlog (cross-phase)

### WS-A Domain model alignment
- [ ] Decide final ClaimEdge relationship vocabulary:
  - [ ] supports
  - [ ] contradicts/refutes
  - [ ] qualifies
  - [ ] depends_on/requires
- [ ] Decide whether to add derived_from and maps_to_ectd_section edge records or keep in node fields.
- [ ] Normalize naming between API and UI for confidence/review status.

### WS-B API and DX
- [ ] Add pagination/filtering for large evidence sets.
- [ ] Add query endpoints by project and workspace.
- [ ] Add lightweight health/status endpoint for compose readiness checks.

### WS-C Performance and observability
- [ ] Instrument API timing for export generation.
- [ ] Add structured logs for validation failures and review transitions.
- [ ] Add basic metrics counters (exports, blocked exports, approval latency).

## 5) Definition of Done for MVP release

- [ ] End-to-end user flow works in API mode:
  - [ ] Create project
  - [ ] Complete COU
  - [ ] Add NAM study
  - [ ] Complete evidence matrix
  - [ ] Approve claims
  - [ ] Create eCTD mappings
  - [ ] Export package
- [ ] Export package is project-scoped and auditable.
- [ ] Human review gate is enforced server-side.
- [ ] Traceability from eCTD mapping -> claim/evidence -> study -> COU is demonstrable.
- [ ] README and developer docs match actual behavior.

## 6) Immediate next actions (this week)

### P1 tasks
- [x] Implement project-scoped eCTD filtering in backend export controller.
- [x] Add serializer groups and verify API payload shapes.
- [x] Build frontend API client and wire Project + COU pages first.
- [x] Add integration test covering export-blocked-when-pending-claims.

### P2 tasks
- [x] Wire Study and Validation pages to API.
- [x] Wire Claim Graph status changes to API.
- [x] Replace client-built exports with backend export endpoints.

## 7) Deferred (post-MVP)

- [ ] Dedicated graph database for claim graph analytics.
- [ ] Advanced ontology resolution against remote ontology services.
- [ ] Full PDF generation pipeline with regulatory formatting templates.
- [ ] Multi-tenant authn/authz and role-based reviewer permissions.

## 8) Planning maintenance rules

- [ ] Update this file at end of each implementation session.
- [ ] Move completed items from immediate actions into phase checklists.
- [ ] Keep objective and acceptance criteria synchronized with initial_prompt.md.
- [ ] Do not mark a task complete without linked code/tests/docs evidence.
