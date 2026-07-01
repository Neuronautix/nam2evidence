# Demo dataset — CX-4471 liver organoid hepatotoxicity

Synthetic files for the NAM-CORE standardization POC. **Illustrative data only — not
real study results.** They extend the existing `app:load-demo-data` project (COU-HEP-001).

## Files
| File | Role |
|------|------|
| `endpoint_measurements_raw.csv` | Raw endpoint export — the **"before" state** with deliberate issues |
| `endpoint_measurements_corrected.csv` | Same data with every blocker resolved — the **"after" state** |
| `sample_sheet.csv` | Sample → biological system / donor / cell source / passage |
| `exposure_design.csv` | Test articles, concentrations, timepoints, vehicle |
| `device_metadata.csv` | Instruments / platforms |
| `assay_protocol.md` | Endpoint methods and design |
| `analysis_script_reference.txt` | Provenance: repo, container, software |
| `validation_evidence.csv` | Validation Evidence Matrix rows |
| `ontology_mapping_seed.json` | Suggested source-value → ontology-term mappings |
| `expected_validation_report.json` | Expected before/after validation outcomes |

## Deliberate issues in the "before" state
These surface as navigable validation blockers in the Semantic Validation and Readiness
dashboards:

1. **Missing unit** — `S-005` (LDH release) has an empty unit.
2. **Unmapped endpoint** — `S-009` uses `lipid_peroxidation_index`, absent from the seed
   vocabulary and left as a mandatory-but-unapproved ontology mapping.
3. **Missing donor passage** — `DONOR-02` rows in `sample_sheet.csv` have no passage number.
4. **Claim human_review_required** — the legacy demo claims (CLAIM-001…005) start pending review.
5. **Missing raw-file provenance** — `S-009` links to neither a raw file nor an analysis activity.
6. *(bonus)* **Non-numeric value** — `S-010` has `QNS` instead of a number.

## Corrected state
`endpoint_measurements_corrected.csv` fills the unit, maps and provenances `S-009`, and
fixes the non-numeric value. Combined with approving the mandatory ontology mappings,
recording the `DONOR-02` passage, and approving the claims, the export gate opens
(`export_status: internally_reviewed`).

See `../docs/POC_DEMO_SCRIPT.md` for the full step-by-step walkthrough.
