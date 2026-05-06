# NAMO-to-IND Mapper: Implementation Planning Brief

## Current objective

Build a context-of-use-driven evidence packaging tool for NAM-derived nonclinical data.

The product should not claim to "convert NAMO into an IND." It should generate a structured regulatory evidence package that maps NAM metadata, validation evidence, and reviewer-approved claims into the nonclinical parts of an IND/eCTD submission where that evidence belongs.

## Planning status

### Product framing and source discovery

- [x] Reframe the tool as a regulatory evidence packaging workbench, not an automated IND author.
- [x] Confirm NAMO is the semantic foundation, not just a vocabulary list.
- [x] Confirm NAMO is LinkML-based and can support JSON Schema, Python models, OWL, and related generated artifacts.
- [x] Confirm NAMO already models the major NAM categories needed for this product: organoids, organ-on-chip, tissue-on-chip, 2D and 3D cultures, co-cultures, QSAR, PBPK, ML models, digital twins, and metabolic models.
- [x] Confirm NAMO already includes structured validation/concordance concepts that can anchor regulatory credibility scoring.
- [x] Confirm NAMO examples and use cases provide concrete YAML/JSON patterns that can seed MVP payloads and fixtures.

### Work that remains before implementation can start cleanly

- [ ] Lock the MVP use case and sample package around one concrete regulatory question.
- [ ] Choose the implementation stack for frontend, backend, storage, graph layer, and document export.
- [ ] Define the canonical internal data model that combines NAMO-aligned metadata with regulatory packaging entities that NAMO does not natively provide.
- [ ] Define the rule set that maps validated evidence into eCTD Module 4 targets and secondary Module 2 summary links.
- [ ] Define the review workflow so no generated claim is exportable until human approval.
- [ ] Define the import path for NAMO example YAML/JSON and future curated datasets.
- [ ] Define the validation pipeline: structural validation, ontology term validation, business rules, and export gating.
- [ ] Define the output package formats for MVP and post-MVP.

## Problem statement

NAM-derived data is difficult to use in regulatory workflows because the evidence is usually fragmented across model descriptions, assay metadata, validation studies, benchmark results, and narrative interpretation. NAMO solves much of the metadata standardization problem, but it does not by itself produce a submission-ready evidence package, a regulatory claim review process, or an eCTD placement map.

This tool should bridge that gap.

## Product thesis

The product is a regulatory evidence workbench with five connected artifacts:

```text
Context-of-Use Card
        -> defines intended regulatory question
NAM Study Metadata
        -> captures NAMO-aligned model and study description
Validation Evidence Matrix
        -> evaluates relevance, reproducibility, and credibility
Weight-of-Evidence Claim Graph
        -> links evidence, assumptions, contradictions, and limitations
eCTD Export Map
        -> places approved evidence into IND-oriented nonclinical structure
```

## Source-backed facts that should drive the design

### What NAMO already gives us

- [x] Top-level organization: `Dataset` contains `Study` and `ModelSystem` records.
- [x] `ModelSystem` is the base abstraction; `NAMModel` is the non-animal branch.
- [x] The major NAM branches are `CellularSystem`, `MicrophysiologicalSystem`, and `InSilicoModel`.
- [x] Concrete classes already exist for `Organoid`, `OrganOnChip`, `TissueOnChip`, `TwoDCellCulture`, `ThreeDCellCulture`, `CoCulture`, `CellLineModel`, `QSARModel`, `PBPKModel`, `MLModel`, `DigitalTwin`, and `MetabolicModel`.
- [x] Shared identifiers and human-readable metadata already exist via `NamedThing` fields such as `id`, `name`, `description`, and `type`.
- [x] `Study` already contains a `context_of_use` field, which aligns well with the regulatory anchoring requirement.
- [x] `NAMModel` already captures cross-cutting fields such as `biological_organization_level`, `spatial_context`, `complexity_level`, and `references`.
- [x] NAMO already models validation through `StructuredConcordanceResult`, `MolecularSimilarity`, `PathwayConcordance`, `PhenotypeOverlap`, `FunctionalParity`, `Reproducibility`, `FunctionalAssay`, `CellTypeCoverage`, `QualityControlMetric`, and related statistics classes.
- [x] NAMO already integrates with external ontologies and controlled identifiers including UBERON, Cell Ontology, ChEBI, NCBITaxon, OBI, and ECO.

### What the tool must add on top of NAMO

- [ ] Regulatory package concepts such as evidence items, claims, claim edges, review decisions, export packages, and eCTD placement records.
- [ ] A context-of-use object with regulatory-specific constraints and acceptance criteria beyond NAMO's base `Study.context_of_use` string.
- [ ] Rule-driven mapping from evidence to CTD/eCTD sections.
- [ ] Human review states and approval gating.
- [ ] Narrative dossier assembly for Module 2/4 support documents.
- [ ] A contradiction and limitation model for weight-of-evidence reasoning.

## Relevant NAMO implementation findings

### Schema and technical implications

- [x] NAMO is built with LinkML, using a single YAML schema to generate multiple computational representations.
- [x] Generated targets explicitly include Python dataclasses/Pydantic models, JSON Schema, OWL, documentation, and related artifacts.
- [x] This means the mapper should avoid inventing a parallel metadata schema if the same structure can be imported or derived from NAMO.
- [ ] Decide whether the product will embed generated NAMO models directly, consume exported JSON Schema, or maintain a translation layer.

### Example and fixture implications

- [x] The NAMO repository provides example input/output instances in YAML and JSON under `examples/` and `examples/output/`.
- [x] Example outputs cover multiple clinically relevant slices, including hepatic organoids, kidney organoids, cardiotoxicity organoids, airway chips, tissue chips, QSAR hepatotoxicity models, PBPK models, ML cardiotoxicity models, and multi-organ chip platforms.
- [x] The examples show concrete fields worth preserving in the mapper, such as `organ_modeled`, `cell_types`, `cell_source`, `microfluidic_design`, `mechanical_forces`, `structured_concordance`, `model_performance`, `references`, and quality control details.
- [ ] Build the MVP fixtures by adapting the existing NAMO examples instead of inventing synthetic structures from scratch.

### Use-case implications

- [x] NAMO's use cases explicitly include integrated NAM databases for regulatory science, model selection platforms, AI/ML training data curation, and multi-organ system integration.
- [x] The regulatory science use case includes validation status, concordance metrics, reproducibility metrics, and QC thresholds that map directly to this project's evidence matrix needs.
- [x] The implementation guidance emphasizes ontology validation, provenance tracking, quantitative metrics, reproducibility documentation, versioning, API-first integration, and schema compliance checks.
- [ ] Convert these source use cases into implementation epics and acceptance criteria for this repository.

## Recommended MVP scope

### Primary MVP use case

- [ ] Adopt a single end-to-end demonstration package:

  `Human liver organoid NAM for hepatotoxicity risk assessment before IND submission`

This is the cleanest first slice because NAMO already includes hepatic organoid and hepatotoxicity-oriented examples, and because the regulatory question is narrow enough to make evidence validation and eCTD placement concrete.

### Regulatory positioning

- [x] The tool should output a regulatory support level, not a binary "IND-ready" score.
- [ ] Use a bounded vocabulary such as:
  - `exploratory`
  - `supportive`
  - `decision-informing`
  - `potentially pivotal within defined context of use`

### Explicit non-goals for MVP

- [ ] Do not attempt full automated authoring of an IND.
- [ ] Do not attempt broad coverage of all NAM modalities in the first release.
- [ ] Do not make final regulatory acceptability determinations without human review.
- [ ] Do not treat NAMO as a replacement for submission structure, review workflow, or regulatory reasoning.

## Core artifacts to implement

### 1. Context-of-Use card

Purpose: define exactly what the evidence is allowed to support.

Required fields for MVP:

- [ ] `cou_id`
- [ ] `regulatory_question`
- [ ] `drug_development_stage`
- [ ] `intended_use`
- [ ] `decision_supported`
- [ ] `nam_type`
- [ ] `biological_domain`
- [ ] `endpoint_class`
- [ ] `population_relevance`
- [ ] `limitations`
- [ ] `acceptance_criteria`
- [ ] `regulatory_support_level`
- [ ] `review_status`

Acceptance conditions:

- [ ] Cannot create downstream claim records without a COU.
- [ ] Every evidence item and export target must reference a COU.
- [ ] COU versions must be tracked.

### 2. NAM study metadata object

Purpose: store model and study metadata in a NAMO-aligned structure.

Required MVP requirements:

- [ ] Preserve NAMO-like identifiers and class typing.
- [ ] Support import/export of at least one organoid example format in YAML and JSON.
- [ ] Support top-level entities for study, model system, assay metadata, outputs, provenance, and references.
- [ ] Preserve ontology-linked terms for organ, cell type, disease, chemicals, and evidence.
- [ ] Capture provenance for SOP, lab/site, software version, date, and source references.

Acceptance conditions:

- [ ] Payload validates structurally.
- [ ] Ontology identifiers validate syntactically and by allowed vocabulary where feasible.
- [ ] Imported example data can round-trip without dropping required fields.

### 3. Validation evidence matrix

Purpose: evaluate whether the NAM is credible for the stated COU.

Minimum evidence domains:

- [ ] Analytical validity
- [ ] Technical reproducibility
- [ ] Biological relevance
- [ ] Reference compound performance
- [ ] Exposure relevance
- [ ] Data integrity and provenance
- [ ] Limitation analysis
- [ ] Regulatory alignment

Design constraints from NAMO findings:

- [x] Reuse structured concordance dimensions where possible instead of inventing parallel fields.
- [x] Support quantitative metrics, thresholds, pass/fail values, and methodological notes.
- [x] Support reproducibility details such as inter-laboratory consistency, replicate count, and quality control metrics.

Acceptance conditions:

- [ ] Every matrix row has status, evidence source, and reviewer note.
- [ ] Failed required rows block promotion of support level above a configured threshold.
- [ ] The matrix can cite both direct experimental evidence and literature references.

### 4. Weight-of-evidence claim graph

Purpose: separate data, interpretation, claims, limitations, and regulatory placement.

Required node types:

- [ ] Claim
- [ ] Evidence item
- [ ] Study
- [ ] Model system
- [ ] Limitation
- [ ] Assumption
- [ ] Reviewer decision
- [ ] Export target

Required edge types:

- [ ] `supports`
- [ ] `contradicts`
- [ ] `qualifies`
- [ ] `depends_on`
- [ ] `limited_by`
- [ ] `derived_from`
- [ ] `conforms_to`
- [ ] `maps_to_ectd_section`

Acceptance conditions:

- [ ] Claims cannot be marked exportable unless linked to evidence and review approval.
- [ ] Contradictory evidence must remain visible in the graph.
- [ ] Every exportable claim must reference both COU and target eCTD section.

### 5. eCTD export map

Purpose: place approved evidence into a submission-oriented nonclinical structure.

Initial mapping targets for MVP:

- [ ] `4.2.1.1 Primary pharmacodynamics`
- [ ] `4.2.1.2 Secondary pharmacodynamics`
- [ ] `4.2.1.3 Safety pharmacology`
- [ ] `4.2.2 Pharmacokinetics`
- [ ] `4.2.3.2 Repeat-dose toxicity` where justified
- [ ] `4.2.3.3 Genotoxicity` where justified
- [ ] `4.2.3.7.3 Mechanistic studies`
- [ ] `4.2.3.7.5` and `4.2.3.7.6` where justified for metabolite or impurity support
- [ ] `4.3 Literature references`
- [ ] Cross-links to `2.4 Nonclinical Overview` and `2.6 Nonclinical Written and Tabulated Summaries`

Acceptance conditions:

- [ ] Export map records source evidence, rationale, and confidence.
- [ ] Every mapped section is traceable back to claims and underlying studies.
- [ ] Export output can produce at least Markdown and JSON in MVP.

## Functional requirements for the full tool

### Data ingestion and curation

- [ ] Import NAMO-aligned YAML and JSON examples.
- [ ] Create and edit records through forms for COU, study metadata, evidence matrix, and claim graph.
- [ ] Preserve original imported payloads for auditability.
- [ ] Capture references using DOI, PMID, URLs, and regulatory guidance citations.

### Validation pipeline

- [ ] Structural schema validation.
- [ ] Ontology term validation for CL, UBERON, MONDO, ChEBI, and other chosen sources.
- [ ] Business-rule validation for required evidence domains per COU.
- [ ] Export gating validation before dossier generation.

### Review workflow

- [ ] Draft -> validated -> reviewer pending -> approved/rejected lifecycle.
- [ ] Human sign-off requirement for claim promotion and export.
- [ ] Review comments attached to evidence rows and claims.
- [ ] Change history for COU, evidence, and export decisions.

### Search and traceability

- [ ] Filter by NAM type, organ, endpoint, development stage, support level, and review status.
- [ ] Trace from eCTD section back to claim, evidence, study, and original metadata.
- [ ] Trace from a model system to all supporting validation records and references.

### Export formats

- [ ] JSON evidence package.
- [ ] Markdown dossier.
- [ ] CSV validation matrix.
- [ ] JSON-LD or graph export for downstream semantic tooling.
- [ ] Later: PDF dossier and folderized eCTD-ready package structure.

## Proposed implementation epics

### Epic 1: Foundation and schema strategy

- [ ] Decide how NAMO models are consumed.
- [ ] Define local extension models for regulatory packaging entities.
- [ ] Create canonical sample payloads for the liver organoid hepatotoxicity case.
- [ ] Define validation boundaries between NAMO schema constraints and application business rules.

### Epic 2: COU and metadata authoring

- [ ] Build COU editor and persistence.
- [ ] Build NAM study metadata editor/importer.
- [ ] Add source reference capture and provenance fields.
- [ ] Validate ontology-linked fields.

### Epic 3: Validation matrix and evidence scoring

- [ ] Define the matrix row model.
- [ ] Support metrics, thresholds, pass/fail values, and notes.
- [ ] Implement configurable support-level rules.
- [ ] Seed rows from imported NAMO structured concordance data where available.

### Epic 4: Claim graph and review workflow

- [ ] Define graph storage model.
- [ ] Build claim/evidence/limitation relationships.
- [ ] Add review states, approval gating, and contradiction visibility.
- [ ] Build traceability views.

### Epic 5: eCTD mapping and export

- [ ] Define eCTD target model and mapping rationale fields.
- [ ] Map approved claims to Module 4 sections.
- [ ] Generate JSON and Markdown exports.
- [ ] Add summary links for Module 2 support artifacts.

## Important open decisions to resolve in the next planning pass

- [ ] Will the application store raw NAMO instances directly, transformed internal records, or both?
- [ ] Will the graph be implemented in relational tables first, JSON documents, or a dedicated graph layer?
- [ ] How strict should ontology validation be in MVP: CURIE syntax only, branch membership, or remote ontology resolution?
- [ ] Which evidence score thresholds change support level labels?
- [ ] Which eCTD mappings are deterministic versus reviewer-selected?
- [ ] Is the first export target only human-readable Markdown/JSON, or is an eCTD folder layout required in MVP?

## Suggested first implementation package

- [ ] `COU-001`: liver organoid hepatotoxicity support before IND
- [ ] `STUDY-001`: NAMO-aligned hepatic organoid study import
- [ ] `EVID-001`: validation matrix for reproducibility, biology, benchmark compounds, and exposure relevance
- [ ] `CLAIM-001`: no hepatotoxic signal under tested exposure range
- [ ] `MAP-001`: placement into mechanistic toxicology and related nonclinical sections

## Relevant sources

### Primary NAMO sources

- [x] https://github.com/monarch-initiative/namo
- [x] https://monarch-initiative.github.io/namo/
- [x] https://monarch-initiative.github.io/namo/manuscript/namo_ontology_manuscript/
- [x] https://monarch-initiative.github.io/namo/elements/
- [x] https://github.com/monarch-initiative/namo/tree/main/examples
- [x] https://github.com/monarch-initiative/namo/tree/main/examples/output
- [x] https://monarch-initiative.github.io/namo/use-cases/

### Source facts to carry into implementation planning

- [x] NAMO uses LinkML as the authoritative schema layer.
- [x] NAMO supports multi-format generation from one schema definition.
- [x] NAMO includes 48 classes, 156 properties, 12 enumerations, 6 integrated ontologies, and 23 example instances in the manuscript's reported release.
- [x] NAMO examples cover regulatory-relevant hepatotoxicity, cardiotoxicity, nephrotoxicity, airway disease, multi-organ screening, and computational prediction scenarios.
- [x] NAMO implementation guidance emphasizes provenance, ontology validation, reproducibility metrics, versioning, and API-first integration.

### Additional regulatory anchors already referenced in the concept

- [ ] FDA guidance on New Approach Methodologies and validation expectations
- [ ] FDA context-of-use framing for qualification-style evidence packages
- [ ] FDA eCTD comprehensive table of contents headings
- [ ] ICH M4S nonclinical safety organization

## Definition of a good next planning document

The next document produced from this brief should be a precise implementation plan with:

- [ ] explicit architecture choices
- [ ] concrete entities and schemas
- [ ] milestone sequencing
- [ ] acceptance criteria per epic
- [ ] example payloads and fixtures
- [ ] validation rules
- [ ] export specification
- [ ] testing strategy
- [ ] a short list of deferred items

If this brief is used as the planning source, the next pass should not re-open product framing. It should only convert the unchecked items into implementation decisions.
