# FDA March 2026 NAM guidance profile

This profile is a **curated, machine-readable interpretation** of the FDA draft guidance *General Considerations for the Use of New Approach Methodologies in Drug Development* (March 2026). It is intended to make source expectations inspectable inside nam2evidence; it is not an FDA checklist, acceptance framework, or substitute for the guidance itself.

Official sources:

- Guidance landing page: https://www.fda.gov/regulatory-information/search-fda-guidance-documents/general-considerations-use-new-approach-methodologies-drug-development
- Official PDF (FDA media 191589): https://www.fda.gov/media/191589/download
- Docket: FDA-2025-D-6131
- FDA issuance date: 18 March 2026

As curated on 28 September 2026, FDA still labels the document **Draft Level 1**, **not for implementation**, and nonbinding.

## Curation scope

The seed focuses on Section III, **Validation Considerations**, which organizes the FDA framework around four features:

1. Context of Use
2. Human Biological Relevance
3. Technical Characterization
4. Fit-for-Purpose

The profile contains 16 requirements. They are paraphrases designed for evidence organization. The source locator on every requirement points back to the relevant FDA section, printed guidance page, and FDA line range.

The seed is stored at:

`standards/reference/fda-nam-general-considerations-2026-v1.json`

## What was deliberately not done

The curation does **not** convert every use of “should”, “describe”, “provide”, or “demonstrate” into a mandatory pass/fail criterion. FDA states that its guidance recommendations are nonbinding and that “should” denotes a suggestion or recommendation unless a specific legal requirement is cited.

Accordingly:

- every curated item uses `importance = recommended`, not `required`;
- method-dependent recommendations have explicit conditional applicability;
- scientific adequacy and regulatory relevance remain human judgments;
- no universal sensitivity, specificity, reproducibility, or comparator threshold is invented;
- examples for liver, neural, respiratory, organ-chip, or other systems are not treated as requirements for unrelated NAMs;
- the profile does not equate validation, qualification, fit-for-purpose use, or regulatory acceptance.

## Requirement map

| ID | FDA feature | Curated purpose | Applicability |
| --- | --- | --- | --- |
| FDA-NAM-COU-001 | Context of Use | Define intended use, regulatory purpose, decision context and data gap | All NAMs |
| FDA-NAM-BIO-001 | Human Biological Relevance | Describe physiological/model-system relevance | All NAMs; expert interpretation |
| FDA-NAM-BIO-002 | Human Biological Relevance | Show relevant toxicological findings can be evaluated | All NAMs; expert interpretation |
| FDA-NAM-BIO-003 | Human Biological Relevance | Relate modeled mechanisms to human outcomes | All NAMs; expert interpretation |
| FDA-NAM-TECH-001 | Technical Characterization | Document method details and variability sources | All NAMs |
| FDA-NAM-TECH-002 | Technical Characterization | Document statistics and interpretation criteria | All NAMs |
| FDA-NAM-TECH-003 | Technical Characterization | Characterize predictive performance for the CoU | All NAMs; metric adequacy is contextual |
| FDA-NAM-TECH-004 | Technical Characterization | Establish working-duration/material stability | Conditional |
| FDA-NAM-TECH-005 | Technical Characterization | Document cell/tissue source and preparation | Cell/tissue-based NAMs |
| FDA-NAM-TECH-006 | Technical Characterization | Characterize donor/biological variability | Conditional |
| FDA-NAM-TECH-007 | Technical Characterization | Justify reference compounds/controls | Context-dependent |
| FDA-NAM-TECH-008 | Technical Characterization | Document culture medium/maintenance | Culture-based NAMs |
| FDA-NAM-TECH-009 | Technical Characterization | Address platform/device technical effects | Platform/device-dependent |
| FDA-NAM-FIT-001 | Fit-for-Purpose | Compare with established method when available | Explicitly conditional |
| FDA-NAM-FIT-002 | Fit-for-Purpose | Describe benefits, limitations and constraints | All NAMs |
| FDA-NAM-FIT-003 | Fit-for-Purpose | Explain contribution to overall human risk/safety assessment | All NAMs; expert interpretation |

## Loading

After migrations:

```bash
docker compose exec -T api php bin/console app:load-guidance-profiles
```

The loader scans `standards/reference/*.json`. It is intentionally strict:

- the same `profile_key + version` can be loaded repeatedly when the seed is unchanged;
- a SHA-256 fingerprint of the seed is stored in `GuidanceProfile.sourceMetadata`;
- changing a seed while retaining the same profile version fails;
- revised curation must use a new profile version.

This is stricter than an upsert because existing RequirementAssessments must continue to mean exactly what they meant when they were created.

## Synthetic demonstration assessments

The existing COU-HEP-001 demo can be populated with illustrative assessments:

```bash
docker compose exec -T api php bin/console app:load-demo-data --force
docker compose exec -T api php bin/console app:load-guidance-profiles --with-demo-assessments
```

The fixture is `demo/fda_guidance_assessments.json`. It deliberately includes a mixture of:

- `supported`
- `partial`
- `missing`
- `not_applicable`
- `requires_human_assessment`

These are **synthetic nam2evidence demonstration statuses**, not FDA findings. Every seeded assessment remains `human_review_required`.

## Three layers that must remain distinct

**Source layer:** what the FDA draft guidance says, represented through versioned paraphrases and exact source locators.

**Project interpretation layer:** how a specific project/CoU maps its evidence to a curated requirement. This is represented by RequirementAssessment and RequirementEvidenceLink.

**Software-check layer:** deterministic structural, semantic, provenance, or future gap-engine checks. Software output may propose or support an assessment, but it is not the regulatory source and is not automatically a human-reviewed conclusion.

Maintaining this separation is a core design constraint for the Evidence Navigator in issue #7.

## Limitations

- The profile is based on a draft guidance and must be superseded/versioned if FDA changes or finalizes the document.
- This is a curated interpretation, not a reproduction of the full guidance.
- The `applicability_rules` JSON is an explicit representation for future deterministic/human review logic; issue #7 will implement the evidence-gap engine that consumes it.
- A requirement can be structurally well-supported while the underlying NAM remains scientifically unsuitable for the CoU.
