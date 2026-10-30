# External statements (TIB Knowledge Loom and similar)

> **Positioning.** An external statement is *captured, source-attributed evidence from
> outside the project* — first of all a [TIB Knowledge Loom](https://knowledgeloom.tib.eu/)
> statement (a DOI-identified scientific statement with declared data, code and analysis
> links). Capturing one standardizes *where it came from and what it said*. It does not
> make the statement true, validated, or regulatorily relevant. A statement becomes
> relevant to a project only through a link that a **named human reviewer approved**, and
> it never substitutes for the project's own validation evidence or for human review of
> claims.

This is the first delivered slice of the
[Loom integration plan](development/integration-plan-loom-sciclaim2explore.md) (Lane A).

---

## Why this exists

Guidance expectations such as *human biological relevance* or *comparison with an
established method* are usually met with **published** findings, not with the project's own
endpoint rows. Until now a `RequirementAssessment` could only link an `EvidenceItem`. External
statements give published, DOI-identified findings a first-class, provenance-preserving home
— without letting them leak into the measurement table, the claim gate or the readiness
score.

## Model

Two entities in `App\Entity\NamCore` (tables `namcore_external_statement`,
`namcore_external_statement_link`, migration `Version20261030000001`):

### `ExternalStatement` — an immutable capture

| Field | Meaning |
|---|---|
| `sourceSystem` | `loom` (others later). |
| `statementUri` + `sourceVersion` | Identity within a project (unique together with `project`). A changed statement must arrive as a new `sourceVersion`; the old capture is never rewritten. |
| `recordUri`, `originalWorkDoi` | The Loom record (work) and the underlying publication. |
| `statementText`, `statementKind` | Verbatim text; `author_claim \| curated_statement \| analytical_finding` kept distinct. |
| `datasetLinks`, `codeLinks` | Declared data/code behind the statement. |
| `studyContext` | Population/model, exposure, comparator, outcome, unit, time window, design — filled only from what the source declares. |
| `uncertainty` | As declared; no derived score. |
| `licence`, `accessScope` | `public \| restricted \| unknown`. Anything but `public` never leaves the project in an export and can never be approved as *supporting* evidence. |
| `rawPayload`, `payloadSha256` | Exact bytes captured and their SHA-256. |
| `capturedAt`, `capturedBy`, `captureStatus` | `captured_unverified` until a named human confirms the capture matches the source (`captured_verified`). |

### `ExternalStatementLink` — a human-decided edge

Exactly one target (database check constraint + validator): a `RequirementAssessment`, a
`ClaimNode` or an `EvidenceItem`, always in the statement's own project.

| Field | Values |
|---|---|
| `relationship` | `supports \| partially_supports \| contradicts \| contextualizes \| not_comparable` |
| `linkOrigin` | `deterministic_rule \| machine_suggestion \| human_entered` |
| `reviewStatus` | `suggested \| approved \| rejected` — only `approved` means anything |
| `reviewedBy`, `reviewedAt`, `reviewerNote`, `comparabilityNote` | Required for any decision |

Rules enforced at the API and in the entity validator:

- a decision (`approve`, `reject`, `verify`) without `reviewed_by` is refused (422);
- `supports` / `partially_supports` can be approved **only** for a statement that is
  `public` **and** `captured_verified`; `contextualizes` and `not_comparable` are allowed
  for any statement;
- cross-project targets are refused;
- `not_comparable` is a first-class outcome so the reviewer can record *why* a similar-looking
  statement does not apply to this Context of Use (different organ, endpoint class, exposure…).

## What external statements do **not** do

- They are **not** `EvidenceItem`s and never satisfy the SHACL / PHP rule *decision-informing
  claims must link validation evidence*. An approved `supports` link on a claim leaves that
  error in place (covered by `ExternalStatementApiTest`).
- They add **no** readiness dimension and **no** export-gate blocker. A pending suggestion is a
  *warning* in semantic validation (`ExternalStatementLinkShape`, workspace
  `external-evidence`), never a blocker.
- Text, links and metadata are rendered as **data**. Nothing in a captured payload is
  interpreted as an instruction.

## API (`/api/v1`, additive)

| Method & path | Purpose |
|---|---|
| `POST /projects/{id}/external-statements/import` | Capture statements (`external-statement-capture/0.1.0`). Byte-identical replay → `200 replayed`; different bytes under an existing `statement_uri` + `source_version` → `409`, **nothing written**; missing `captured_by` → `400`. |
| `GET /projects/{id}/external-statements` | Statements with their links and a summary (`?access_scope=`, `?capture_status=`). |
| `PATCH /projects/{id}/external-statements/{sid}/verify` | A named human confirms the capture matches its source (`reviewed_by` required; may also set `access_scope`/`licence`). |
| `GET /projects/{id}/external-statement-targets` | Linkable records (requirement assessments, claims, evidence items) for the UI. |
| `POST /projects/{id}/external-statement-links` | Create a link (default `suggested`). `target_id` accepts a ULID or a business key (`FDA-NAM-TECH-003`, `CLAIM-003`, `EVID-007`). |
| `GET /projects/{id}/external-statement-links` | All links in the project. |
| `PATCH /projects/{id}/external-statement-links/{lid}/approve` / `…/reject` | Human decision; `reviewed_by` required; `relationship` and `comparability_note` may be set at decision time (e.g. reject as `not_comparable`). |

Every import, verification and decision is written to the append-only audit log.

### Capture contract `external-statement-capture/0.1.0`

```json
{
  "source_system": "loom",
  "captured_by": "j.doe@sponsor.org",
  "capture_status": "captured_unverified",
  "record": {
    "record_uri": "https://doi.org/10.xxxx/record",
    "original_work_doi": "10.xxxx/paper",
    "source_version": "2026-10-01",
    "licence": "CC-BY-4.0",
    "access_scope": "public",
    "authors": ["…"]
  },
  "statements": [
    {
      "statement_uri": "https://doi.org/10.xxxx/record.s1",
      "kind": "curated_statement",
      "text": "…verbatim…",
      "dataset_links": [{ "uri": "…", "label": "…", "checksum": "sha256:…", "licence": "…" }],
      "code_links": [{ "uri": "…", "label": "…" }],
      "study_context": { "model": "…", "exposure": "…", "comparator": "…", "outcome": "…", "measurement_unit": "…", "time_window": "…", "design": "…" },
      "uncertainty": { "…": "…" }
    }
  ]
}
```

Record-level `licence`, `access_scope`, `source_version`, `record_uri`, `original_work_doi` and
`authors` are defaults that each statement may override. The adapter that fills this from a
live Loom export is **not** part of this slice (Gate 0 of the plan: the Loom wire format and
licence terms still have to be inspected); today the payload is produced by hand or from a
fixture.

## Exports

`ProjectGraphBuilder` adds `nam:ExternalStatement` nodes to the JSON-LD graph for **public**
statements only, with `prov:wasDerivedFrom` = the statement DOI, `dcterms:license`, the
capture hash and, for **approved** links only, `nam:externalLinks` (`nam:target`,
`nam:relationship`, `nam:reviewedBy`, `nam:comparabilityNote`). Restricted or unknown-scope
statements are absent from every serialization. Suggested and rejected links are never
exported.

## Demo

```bash
docker compose exec -T api php bin/console app:load-demo-data --force
docker compose exec -T api php bin/console app:load-guidance-profiles --with-demo-assessments
docker compose exec -T api php bin/console app:load-loom-demo              # "before": 5 statements captured, 7 links suggested
docker compose exec -T api php bin/console app:load-loom-demo --reviewed   # "after": recorded reviewer decisions applied
```

`demo/loom_statements.json` is **synthetic** (DOIs under the `10.5555` example prefix). It
contains three liver-organoid statements that plausibly inform FDA-NAM-TECH-003, TECH-006 and
FIT-001, one kidney-organoid statement that a naive keyword rule wrongly suggests for CLAIM-004
(the reviewer marks it `not_comparable`), and one `restricted` pre-release statement that is
useful context for EVID-005 but is kept out of every export. Both commands are idempotent.

## Related documents

- [development/integration-plan-loom-sciclaim2explore.md](development/integration-plan-loom-sciclaim2explore.md)
- [REGULATORY_EVIDENCE_MODEL.md](REGULATORY_EVIDENCE_MODEL.md)
- [VALIDATION_RULES.md](VALIDATION_RULES.md) · [EXPORTS.md](EXPORTS.md) · [REGULATORY_POSITIONING.md](REGULATORY_POSITIONING.md)
