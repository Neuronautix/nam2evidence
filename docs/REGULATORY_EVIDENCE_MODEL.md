# Regulatory evidence model

nam2evidence v0.2 introduces a versioned, source-cited layer for representing regulatory or standards-derived evidence expectations. The purpose is to support traceable review against a declared Context of Use (CoU), not to automate regulatory judgment.

## Model

**GuidanceProfile** identifies one curated interpretation of an external source document at a specific version. It records authority, jurisdiction, source status, profile version, canonical source URL, source-document version when available, retrieval metadata and an optional source hash.

**GuidanceRequirement** belongs to exactly one GuidanceProfile version. It stores a stable requirement key, a careful requirement representation/paraphrase, evidence domain, applicability metadata, source locator, importance classification and interpretation note.

**RequirementAssessment** evaluates one GuidanceRequirement for one project and one CoU. The unique scope is project + CoU + requirement. The assessment therefore remains anchored to the exact GuidanceProfile version through the requirement relationship.

**RequirementEvidenceLink** explicitly links the assessment to an existing EvidenceItem with one of four relationships: supports, partially_supports, contradicts or contextualizes. The link also carries provenance describing how or why the edge was created.

## Conservative assessment vocabulary

Assessment status is deliberately restricted to:

- supported
- partial
- missing
- not_applicable
- requires_human_assessment

These values describe evidence coverage only. They do not mean validated, accepted, qualified, IND-ready, submission-ready or otherwise regulatorily adequate.

Generation origin is recorded separately:

- deterministic_rule
- machine_suggestion
- human_entered

Human review is also separate:

- unreviewed
- human_review_required
- human_reviewed

A machine-origin assessment therefore cannot be confused with a human-reviewed conclusion.

## Versioning and provenance

GuidanceProfile uses a stable profile key plus an explicit profile version. The pair is unique. A changed curation or interpretation must create a new version instead of rewriting the reference data used by existing assessments.

Guidance profiles and requirements are read-only through the v1 HTTP API. Issue #6 will add a curated FDA profile through a controlled loader/seed workflow.

Every requirement retains:

- the exact GuidanceProfile version;
- the profile canonical source URL;
- a source locator (section/page/paragraph/table as appropriate);
- an optional interpretation note for conditional or ambiguous material.

Profiles can retain a content hash and retrieval metadata so later snapshots can state exactly which external artifact was consulted.

## Project and CoU isolation

RequirementAssessment stores both project and ContextOfUseCard references. Validation rejects a CoU from another project.

RequirementEvidenceLink currently links the existing EvidenceItem model. Validation rejects evidence from another project or another CoU. This prevents a project-level evidence pool from silently supporting a requirement in an unrelated context.

The evidence-link abstraction is intentionally narrow in this foundation. Future issues may add other canonical evidence types, but they must preserve the same project/CoU isolation and provenance guarantees.

## API

Read-only reference endpoints:

- GET /api/v1/guidance-profiles
- GET /api/v1/guidance-profiles/{id}
- GET /api/v1/guidance-profiles/{id}/requirements

Project-scoped assessment endpoints:

- GET /api/v1/projects/{id}/requirement-assessments
- POST /api/v1/projects/{id}/requirement-assessments
- PATCH /api/v1/projects/{id}/requirement-assessments/{assessmentId}

The list endpoint can be filtered by context_of_use_id and profile_id.

Assessment creation and updates are written to the existing append-only project audit log. Human-reviewed assessments require an explicit reviewed_by value.

## What this model does not do

It does not:

- decide that a NAM is scientifically validated;
- decide that evidence is regulatorily adequate;
- predict acceptance;
- convert guidance prose into universal mandatory rules;
- replace SHACL or structural validation;
- replace qualified scientific/regulatory review.

The v0.2 Evidence Navigator will use this layer to make evidence requirements, gaps, provenance and human judgment inspectable rather than compressing them into a single regulatory-readiness score.
