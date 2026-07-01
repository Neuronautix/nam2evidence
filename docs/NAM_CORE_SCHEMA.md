# NAM-CORE v0.1 Schema

> **Positioning.** NAM-CORE is a proof-of-concept data model for **standardizing**
> NAM-derived nonclinical data into an explicit, queryable, ontology-linkable
> structure. It is a **SEND-like tabular core** — it is *not* an official SEND/CDISC
> deliverable, an FDA-accepted standard, or a claim that the underlying method is
> validated. Standardization is a prerequisite for review; it is not itself
> scientific validation or regulatory interpretation. All records require
> qualified human review before use in any submission.

NAM-CORE v0.1 sits *underneath* the five legacy evidence workspaces
(Context of Use, NAM Study, Validation Matrix, Claim Graph, eCTD Mapping) and
gives them a normalized, machine-readable spine. Each canonical entity is a
Doctrine ORM entity with a `namcore_*` table, a stable **ULID** identifier, and a
project-scoped `ManyToOne` relationship to `Project`.

Schema version constant: `NAM-CORE v0.1` (`ProjectGraphBuilder::SCHEMA_VERSION`).

---

## Shared fields (`NamCoreEntityTrait`)

Every canonical NAM-CORE entity mixes in `NamCoreEntityTrait`
(`api/src/Entity/NamCore/NamCoreEntityTrait.php`), which contributes:

| Field | Type | Meaning |
|---|---|---|
| `id` | ULID | Stable internal identifier (custom generator). |
| `label` | string(255) | Human-readable label. |
| `description` | text, nullable | Free-text description. |
| `version` | string(20), default `0.1` | Per-record version (distinct from the schema version). |
| `validationStatus` | string(20), default `unvalidated` | `unvalidated \| valid \| warnings \| errors`. |
| `extensions` | JSONB (`json`) | Forward-compatible open-ended metadata bag. |
| `createdAt` | datetime (immutable) | Set on construction. |
| `updatedAt` | datetime (immutable) | Touched on `PreUpdate`. |

A few entities (`OntologyTerm`, `OntologyMapping`, `AuditLog`) do **not** use the
trait; they define their own `id`/timestamps and are noted below.

### Extension strategy — explicit columns over opaque JSON

The core design rule: **core domain fields live on the entity as explicit,
queryable, validated columns.** Foreign keys are real `ManyToOne` relations, not
strings buried in a blob. The `extensions` JSONB bag exists **only** for genuinely
open-ended metadata (future organ-on-chip, omics, imaging, electrophysiology
payloads) so the model stays modular without turning into an opaque document
store. During import, unresolved business-key references are also parked in
`extensions` under `unresolved_*` keys so gaps stay visible and navigable rather
than silently dropped.

---

## Canonical entities

Purpose + notable (non-trait) fields. Mandatory fields are marked **required**
(`#[Assert\NotBlank]` or a non-nullable relation). All `project` relations are
DB-mandatory (`nullable: false`) unless noted.

### Reused legacy entities (mapped into NAM-CORE)

| NAM-CORE role | Backing entity | JSON-LD type |
|---|---|---|
| Project | `Project` | `nam:Project` |
| ContextOfUse | `ContextOfUseCard` | `nam:ContextOfUse` |
| NAMStudy | `NAMStudy` | `nam:NAMStudy` |
| ValidationEvidence | `EvidenceItem` | `nam:ValidationEvidence` |
| EvidenceClaim | `ClaimNode` | `nam:EvidenceClaim` |
| ExportPackage | `ExportPackage` | `nam:EvidencePackage` |

- **Project** — top-level program container. `name` **required**, `drugName`
  **required**, `sponsor`, `reviewStatus` (default `pending`). Owns
  `ContextOfUseCard`s.
- **ContextOfUse** (`ContextOfUseCard`) — declares the regulatory question and
  intended use that govern interpretation. Notable: `couId` **required/unique**,
  `namType` **required** (`Organoid | OrganOnChip | QSARModel | CellBasedAssay | …`),
  `regulatoryQuestion` **required**, `intendedUse`, `biologicalDomain`,
  `limitations[]`, `acceptanceCriteria[]`,
  `regulatoryConfidenceLevel` (`exploratory | supportive | decision_informing | potentially_pivotal`).
- **NAMStudy** — NAMO-aligned study record (JSONB-heavy). `studyId`
  **required/unique**, plus `modelSystem`, `experimentalDesign`, `assayMetadata`,
  `dataOutputs`, `provenance` (all JSON). Belongs to a Project and a ContextOfUse.
- **ValidationEvidence** (`EvidenceItem`) — one row of the eight-domain matrix.
  `evidenceId` **required/unique**, `domain` (8-value Choice), `question`
  **required**, `status` (`met | partial | not_met | not_applicable`),
  `metricValue`, `threshold`, `passFail`.
- **EvidenceClaim** (`ClaimNode`) — weight-of-evidence claim node. `claimId`
  **required/unique**, `claimText` **required**, `nodeType`, `claimType`
  (`mechanistic | empirical | comparative | predictive`), `confidence` (four-tier),
  `supportingEvidence[]`, `ectdTargetSections[]`, `reviewStatus`
  (`pending | human_review_required | approved | rejected`, default
  `human_review_required`).

### New standardization entities (`namcore_*`)

- **BiologicalSystem** — the test system exercised by a study. `modelSystemType`
  **required** (`organoid | organ_on_chip | tissue_on_chip | 2d_culture | 3d_culture | co_culture | cell_line | qsar | pbpk | …`),
  `speciesLabel`/`speciesOntologyIri`, `anatomyLabel`/`anatomyOntologyIri`,
  `cellTypeLabel`/`cellTypeOntologyIri`, `cellSource` (→CellSource),
  `differentiationProtocol`.
- **Donor** — biological donor of origin. `donorCode` **required**, `speciesLabel`,
  `sex`, `ageValue`/`ageUnit`, `passageNumber`, `healthStatus`.
- **CellSource** — provenance of the cells. `sourceType` **required**
  (`ipsc | primary | cell_line | …`), `vendor`, `catalogNumber`, `lotNumber`,
  `cellTypeLabel`/`cellTypeOntologyIri`, `differentiationProtocol`, `donor` (→Donor).
- **Platform** — instrument/measurement platform family. `platformType`
  **required**, `vendor`, `model`.
- **Device** — concrete instrument instance. `deviceType` **required**, `vendor`,
  `model`, `serialNumber`, `firmwareVersion`, `platform` (→Platform).
- **Assay** — measurement technique. `assayType` **required**, `method`, `readout`,
  `technologyLabel`/`technologyOntologyIri` (OBI), `namStudy` (→NAMStudy).
- **Sample** — physical/biological sample. `sampleCode` **required**, `batchId`,
  `replicateId`, `biologicalSystem` (→BiologicalSystem), `donor` (→Donor).
- **Exposure** — a test-article treatment. `testArticle` **required**,
  `testArticleOntologyIri` (ChEBI), `concentrationValue`/`concentrationUnit`
  (+IRI), `timepointValue`/`timepointUnit`, `vehicle`.
- **EndpointMeasurement** — the canonical tabular core (see below).
- **QCResult** — a QC metric vs. threshold. `metricName` **required**,
  `metricValue`, `threshold`, `passFail` (`pass | fail | warn`), `assay` (→Assay).
- **ProvenanceActivity** — PROV-style activity. `activityType` **required**
  (`ingestion | processing | analysis | export`), `softwareName`,
  `softwareVersion`, `scriptReference`, `analysisScript` (→AnalysisScript),
  `agentName`, `agentRole`, `startedAt`, `endedAt`.
- **RawDataFile** — ingested raw file. `fileName` **required**, `checksum`,
  `checksumAlgorithm`, `uploadDate`, `sourceSystem`
  (`eln | lims | instrument_export | manual_upload`), `instrumentVersion`,
  `mediaType`, `byteSize`.
- **AnalysisScript** — versioned analysis reference. `name` **required**,
  `repositoryUrl`, `reference`, `language`, `scriptVersion`.
- **OntologyTerm** — controlled-vocabulary term (no trait). `label`/`ontologyPrefix`/`curie`
  **required**, `curie` unique, `iri`, `definition`, `synonyms[]`, `source`,
  `termVersion`. See [ONTOLOGY_MAPPING.md](./ONTOLOGY_MAPPING.md).
- **OntologyMapping** — human-reviewable source→term mapping (no trait).
  `sourceEntityType`, `sourceValue`, `ontologyTerm` (→OntologyTerm),
  `mappingStatus` (`unmapped | suggested | approved | rejected`),
  `mappingConfidence`, `mandatory`, `reviewedBy`, `reviewerNote`.
- **AuditLog** — append-only audit event (no trait). `project` (nullable),
  `entityType`, `entityId`, `action`
  (`create | update | delete | approve | reject | import | export | validate | review_gate`),
  `oldValue`/`newValue` (JSON), `userOrRole` (default `system`), `reason`,
  `createdAt`.

---

## Canonical `EndpointMeasurement` field list

One row = **one measured value, for one endpoint, on one sample, under one
exposure, at one timepoint.** Foreign keys are explicit and queryable; only the
trait's `extensions` bag is free-form. `value` (numeric) and `valueRaw` (verbatim
imported string) sit side by side so non-numeric imports are captured, surfaced by
validation, and corrected without data loss.

| Field | Type | Required | Notes |
|---|---|---|---|
| `project` | →Project | ✅ | Owning project. |
| `study` | →NAMStudy | | Optional link to the NAM study. |
| `assay` | →Assay | | |
| `sample` | →Sample | | |
| `biologicalSystem` | →BiologicalSystem | | |
| `exposure` | →Exposure | | |
| `donor` | →Donor | | |
| `device` | →Device | | |
| `rawDataFile` | →RawDataFile | | Provenance (raw file). |
| `analysisActivity` | →ProvenanceActivity | | Provenance (derived). |
| `endpointId` | string(120) | ✅ | Stable endpoint key, e.g. `atp_viability`. |
| `endpointLabel` | string(255) | | Display label. |
| `endpointOntologyIri` | string(255) | | e.g. OBI/NCIT IRI. |
| `value` | float, nullable | | Parsed numeric value (`null` if non-numeric). |
| `valueRaw` | string(255) | | Verbatim imported value (audit). |
| `unit` | string(60) | | Normalized unit. |
| `unitOntologyIri` | string(255) | | e.g. UO/UCUM IRI. |
| `timepointValue` | float | | |
| `timepointUnit` | string(60) | | |
| `replicateId` | string(120) | | |
| `batchId` | string(120) | | |
| `qcStatus` | string(20) | | `pending \| pass \| fail \| warn`. |
| `exclusionStatus` | string(20) | | `included \| excluded`. |
| `exclusionReason` | text | | Required if excluded. |

> **Import-time minimum** (`EndpointMeasurementImporter`): `endpoint_id`, `value`,
> `unit` must be mapped for a valid canonical row. Semantic validation additionally
> requires a numeric `value`, a `unit`, and a provenance link (raw file *or*
> analysis activity). See [VALIDATION_RULES.md](./VALIDATION_RULES.md).

---

## Related documents

- [ONTOLOGY_MAPPING.md](./ONTOLOGY_MAPPING.md) — controlled vocabularies & mapping workflow.
- [VALIDATION_RULES.md](./VALIDATION_RULES.md) — structural, SHACL, and QC/review layers.
- [EXPORTS.md](./EXPORTS.md) — export formats & intended consumers.
- [REGULATORY_POSITIONING.md](./REGULATORY_POSITIONING.md) — what the tool does and does not claim.
