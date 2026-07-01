# POC Demo Script

A step-by-step **before/after** walkthrough that takes the canonical
COU-HEP-001 (CX-4471 / iPSC-derived liver-organoid hepatotoxicity) project from
raw, gap-ridden CSVs to a standardized, human-reviewed, exportable evidence
package.

> **Reminder.** This demo standardizes NAM data and surfaces gaps. It does **not**
> validate the NAM, assert IND readiness, or produce an official submission.
> "Ready" here means *internally complete and human-reviewed enough to package*.

Demo inputs live in `demo/`:

- `demo/endpoint_measurements_raw.csv` — 12 endpoint rows with **deliberate
  issues**.
- `demo/exposure_design.csv` — test articles + concentrations/timepoints.
- `demo/sample_sheet.csv` — samples, donors, cell source, **passage numbers (some
  blank)**.

---

## 0. Start the stack

```bash
docker compose up --build
docker compose exec -T api php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T api php bin/console app:load-demo-data --force
docker compose exec -T api php bin/console app:load-ontology-seed
```

**Expected:** frontend at http://localhost:3000, API at http://localhost:8080/api,
demo project COU-HEP-001 loaded, 15 seed ontology terms available.

`[SCREENSHOT PLACEHOLDER: docker compose up healthy; project dashboard showing COU-HEP-001]`

---

## 1. View the five legacy workspaces ("before" — narrative already populated)

Open the project and step through the sidebar:

1. **Context of Use** — COU-HEP-001, CX-4471, IND-enabling, four documented
   limitations.
2. **NAM Study** — NAM-STUDY-001: 7-concentration response, 5 endpoints, 3 donors.
3. **Validation Matrix** — eight-domain evidence items.
4. **Claim Graph** — 5 claims, **all `human_review_required`**.
5. **eCTD Mapping** — proposed sections (4.2.3.7.3, 2.6.6, 2.6.2, …).

**Expected:** the narrative dossier exists, but there is **no standardized,
computable endpoint table yet** — that is the "before" state the NAM-CORE layer
fixes.

`[SCREENSHOT PLACEHOLDER: five-tab legacy workspace sidebar]`

---

## 2. Inspect / import the endpoint CSV

Preview the raw CSV (no mapping → column preview + auto-suggested mapping):

```bash
PID=<project-ULID>   # from GET /api/v1/projects
csv=$(cat demo/endpoint_measurements_raw.csv | python3 -c 'import json,sys;print(json.dumps(sys.stdin.read()))')
curl -s -X POST http://localhost:8080/api/v1/projects/$PID/endpoint-measurements/import \
  -H 'Content-Type: application/json' -d "{\"csv\": $csv}"
```

**Expected:** `mode: "preview"` with `columns`, a `suggested_mapping`
(`study→study_id`, `value→value`, `unit→unit`, `endpoint→endpoint_id`, …), sample
rows, and `target_fields`.

`[SCREENSHOT PLACEHOLDER: Endpoints workspace — upload + column-mapping UI with suggestions]`

---

## 3. Map columns and import

Re-POST with the confirmed `mapping` to store rows:

```bash
curl -s -X POST http://localhost:8080/api/v1/projects/$PID/endpoint-measurements/import \
  -H 'Content-Type: application/json' \
  -d "{\"csv\": $csv, \"mapping\": {\"study\":\"study_id\",\"assay\":\"assay_id\",\"sample\":\"sample_id\",\"exposure\":\"exposure_id\",\"endpoint\":\"endpoint_id\",\"endpoint_iri\":\"endpoint_ontology_iri\",\"value\":\"value\",\"unit\":\"unit\",\"timepoint\":\"timepoint_value\",\"timepoint_unit\":\"timepoint_unit\",\"replicate\":\"replicate_id\",\"batch\":\"batch_id\",\"donor\":\"donor_id\",\"raw_file\":\"raw_file_id\",\"qc_status\":\"qc_status\"}}"
```

**Expected:** `mode: "import"`, `summary.imported: 12`, with a **non-zero
`error_count`/`warning_count`** — the import is deliberately forgiving so gaps
surface instead of aborting.

---

## 4. See the validation blockers ("before" gaps)

```bash
curl -s http://localhost:8080/api/v1/projects/$PID/semantic-validation | jq '{conforms,error_count,warning_count,blocking_count}'
```

**Expected blockers, tied to the deliberate demo issues:**

| Demo issue (row in raw CSV) | Rule / gap |
|---|---|
| `ldh_release` row `S-005` has an **empty `unit`** | EndpointMeasurementShape — *missing unit* (blocking). |
| `atp_viability` row `S-010` value `QNS` (**non-numeric**) | EndpointMeasurementShape — *value not numeric* (blocking). |
| `lipid_peroxidation_index` (row `S-009`) has **no `endpoint_iri`** and no raw file | *unmapped endpoint* (warning) + *provenance gap* (blocking). |
| `sample_sheet.csv` blank **`passage_number`** on donor-B samples | Biological/donor characterization gap (warning). |
| All 5 claims `human_review_required` | EvidenceClaimShape — *pending claim* blocks formal export. |
| `mmp_jc1` / novel oxidative panel not seeded | Missing ontology mapping (warning → mandatory blocker once flagged). |

`[SCREENSHOT PLACEHOLDER: Semantic Validation tab — red blockers with recommended_fix and workspace links]`

---

## 5. Approve ontology mappings

Suggest + approve the mappings for the terms that need controlled vocabulary
(e.g. MMP → `NCIT:C17610`), marking mandatory ones:

```bash
curl -s -X POST http://localhost:8080/api/v1/ontology/map -H 'Content-Type: application/json' \
  -d "{\"project_id\":\"$PID\",\"source_entity_type\":\"endpoint\",\"source_value\":\"mitochondrial membrane potential\",\"mandatory\":true}"
# → status "suggested"; then:
curl -s -X PATCH http://localhost:8080/api/v1/ontology/mappings/<MID>/approve -H 'Content-Type: application/json' \
  -d '{"reviewed_by":"reviewer@example.com","reviewer_note":"Confirmed"}'
```

**Expected:** `GET /api/v1/projects/$PID/ontology-mappings` shows
`mandatory_unresolved` dropping toward `0` as approvals land.

`[SCREENSHOT PLACEHOLDER: Ontology mapping panel — suggested vs approved, mandatory badges]`

---

## 6. Resolve the remaining blockers

- Fix the missing `unit` on `S-005` (map or justify) and the non-numeric `QNS`
  value on `S-010` (correct or set `exclusion_status=excluded` with a reason).
- Add a raw-file / analysis-activity provenance link for `S-009`.
- Record passage numbers on the donor-B samples.
- **Review the 5 claims** and move each from `human_review_required` to
  `approved` (Claim Graph workspace / claim-review API).

Re-run validation:

```bash
curl -s http://localhost:8080/api/v1/projects/$PID/semantic-validation | jq '{conforms,blocking_count}'
```

**Expected:** `conforms: true`, `blocking_count: 0`.

`[SCREENSHOT PLACEHOLDER: Semantic Validation tab now green]`

---

## 7. Generate the readiness report ("after")

```bash
curl -s http://localhost:8080/api/v1/projects/$PID/readiness-report | jq '{label,total_score,max_score,percentage,blocking_gaps}'
curl -s http://localhost:8080/api/v1/projects/$PID/export-gate | jq '{blocked,export_status}'
```

**Expected:** `label: "POC FAIR/AI-readiness assessment"`, 10 dimensions each
0–2, a total out of 20, `blocking_gaps: []`, and `export_status:
"internally_reviewed"` (`blocked: false`).

`[SCREENSHOT PLACEHOLDER: Readiness Dashboard — 10-dimension bar chart, gaps cleared]`

---

## 8. Export all formats

```bash
for fmt in jsonld turtle isa-tab parquet ro-crate; do
  curl -s -OJ http://localhost:8080/api/v1/projects/$PID/exports/$fmt
done
# legacy dossier formats:
for fmt in json csv md txt; do
  curl -s -OJ -X POST "http://localhost:8080/api/projects/$PID/export/download?format=$fmt"
done
```

**Expected:** JSON-LD + Turtle graphs, ISA-Tab ZIP, Parquet ZIP (native `.parquet`
if the sidecar is up, else CSV fallback with a README), and an RO-Crate ZIP whose
`nam:packageStatus` is now **`complete`**. See [EXPORTS.md](./EXPORTS.md).

`[SCREENSHOT PLACEHOLDER: Export Center — download buttons + "what's included"]`

---

## 9. Inspect the audit trail

```bash
curl -s http://localhost:8080/api/v1/projects/$PID/audit-log | jq '.count, .entries[0]'
```

**Expected:** an append-only log of every `import`, `approve`/`reject`, `validate`,
`review_gate`, and `export` action with `old_value`/`new_value`, `user_or_role`,
`reason`, and timestamp.

`[SCREENSHOT PLACEHOLDER: Audit Trail view — chronological entries]`

---

## Before vs. after

| | Before | After |
|---|---|---|
| Endpoint data | narrative only, non-computable | canonical `EndpointMeasurement` table |
| Units | some missing / non-numeric values | normalized + mapped/justified |
| Ontology | unmapped source terms | human-approved mappings |
| Claims | all `human_review_required` | reviewed / approved |
| Provenance | gaps | raw file / activity linked |
| Export gate | **blocked** (draft) | **internally reviewed** |
| Exports | JSON/CSV/MD/TXT dossier | + JSON-LD, Turtle, ISA-Tab, Parquet, RO-Crate |

## Related documents

- [NAM_CORE_SCHEMA.md](./NAM_CORE_SCHEMA.md)
- [ONTOLOGY_MAPPING.md](./ONTOLOGY_MAPPING.md)
- [VALIDATION_RULES.md](./VALIDATION_RULES.md)
- [EXPORTS.md](./EXPORTS.md)
- [REGULATORY_POSITIONING.md](./REGULATORY_POSITIONING.md)
