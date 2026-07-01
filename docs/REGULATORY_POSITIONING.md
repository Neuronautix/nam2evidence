# Regulatory Positioning

This document states, explicitly and conservatively, what the nam2evidence
NAM-CORE toolkit **is** and — just as importantly — what it **is not**. Read it
before drawing any conclusion from the tool's outputs.

---

## What this tool is

- A **proof-of-concept toolkit for standardizing NAM-derived nonclinical data**
  into an explicit, queryable, ontology-linkable data model (NAM-CORE v0.1).
- A **context-of-use-driven** workspace: the regulatory question and intended use
  are declared first and govern how everything downstream is interpreted.
- A **FAIR / AI-readiness proof-of-concept**: it maps free text to controlled
  vocabularies, checks structural and semantic completeness, and emits reusable,
  machine-readable exports (JSON-LD, RDF/Turtle, Parquet, ISA-Tab, RO-Crate).
- A producer of a **structured evidence package** — a **SEND-like tabular core**
  plus provenance, validation, and readiness metadata — intended to be
  **review-supportive**.
- A **human-in-the-loop** system: the software suggests; qualified people decide.

## What this tool is NOT

- ❌ It does **not** claim **FDA acceptance** or acceptance by any regulatory
  authority.
- ❌ It does **not** produce an **official SEND/CDISC** deliverable. NAM-CORE is a
  *SEND-like* POC core, **not an official submission standard**.
- ❌ It does **not** make a project **automatically IND-ready**. A green readiness
  score is an internal maturity heuristic, not a regulatory determination.
- ❌ It does **not** perform **NAM validation**. Software cannot validate a
  scientific method; it can only standardize and check the data describing it.
- ❌ It does **not** "convert" NAM data into animal-study equivalents.
- ❌ It does **not** provide **regulatory advice**. The eCTD mapping is a
  structured *proposal*, not advice.

---

## Four distinct concepts (do not conflate them)

| Concept | What it means here | Who owns it |
|---|---|---|
| **Data standardization** | Putting data into an explicit, consistent, ontology-linked structure. | This tool. |
| **Scientific validation** | Establishing that the NAM method is fit for its scientific purpose. | Scientists / method developers. |
| **Regulatory interpretation** | Judging what the evidence means for a regulatory decision. | Regulatory professionals & authorities. |
| **Human approval** | A qualified person signing off before use. | Named reviewers. |

The tool operates **only in the first column** and provides gates that *force* the
other three to happen outside the software.

---

## How it supports — but does not replace — regulatory judgment

- Every claim starts as `human_review_required`; **formal export is blocked** until
  a qualified reviewer clears it.
- Ontology mappings are only "resolved" when **a human approves** them.
- The **review gate** blocks formal packages on missing COU fields, unsupported
  decision-informing claims, unresolved mandatory mappings, blocking validation
  errors, structural gaps, and provenance gaps.
- The gate asserts only that a package is **internally complete and reviewed enough
  to package** — never that it is **regulatorily adequate**.
- An **append-only audit trail** records who changed, approved, or exported what,
  and why.

The tool makes the evidence easier to review; it never makes the decision.

---

## Why context of use governs interpretation

A NAM has no fixed regulatory weight in the abstract — its value depends entirely
on **the specific question it is being used to answer.** The Context of Use card
is therefore mandatory and first-class: it pins the decision question, intended
use, biological domain, limitations, acceptance criteria, and a conservative
four-tier support level (`exploratory → supportive → decision_informing →
potentially_pivotal`). Readiness, validation, and eCTD placement are all
interpreted *relative to that declared context* — the same data may be supportive
in one context and insufficient in another.

## Why standardization is different from validation

**Standardizing** data — making it complete, consistent, ontology-linked, and
computable — is a **necessary precondition** for meaningful review, but it says
**nothing** about whether the underlying science is sound. A perfectly
standardized, fully "AI-ready" dataset can still describe a method that is not fit
for purpose. This tool deliberately keeps the two separate: it will tell you the
data is well-structured; it will never tell you the method is validated or the
evidence sufficient. Those judgments remain with qualified scientists, regulatory
professionals, and the reviewing authority.

## Related documents

- [NAM_CORE_SCHEMA.md](./NAM_CORE_SCHEMA.md)
- [VALIDATION_RULES.md](./VALIDATION_RULES.md)
- [EXPORTS.md](./EXPORTS.md)
- [ONTOLOGY_MAPPING.md](./ONTOLOGY_MAPPING.md)
