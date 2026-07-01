# Ontology Mapping

> **Positioning.** Ontology mapping makes NAM-CORE data **FAIR/AI-ready** by
> linking free-text source values to controlled vocabulary terms. Every mapping is
> **human-approved** — the software only *suggests*. Controlled terminology is a
> standardization activity, not scientific or regulatory validation. This is a
> proof-of-concept; the seed vocabulary is deliberately small and project-curated.

The ontology layer connects two entities
(`api/src/Entity/NamCore/OntologyTerm.php`, `OntologyMapping.php`): a catalogue of
controlled **terms** and project-scoped **mappings** from a source value to a term.

---

## Supported ontologies

Terms are seeded locally with their canonical IRIs. **No live internet lookup is
required** at runtime — IRIs are references to the canonical term pages, not
fetched. Curate/expand per project.

| Source vocabulary | Prefix | Typical source type (`maps`) |
|---|---|---|
| Cell Ontology | `CL` | `cell_type` |
| Uberon (anatomy) | `UBERON` | `anatomy` |
| ChEBI (chemistry) | `CHEBI` | `chemical` |
| PubChem | (via ChEBI cross-refs) | `chemical` |
| Ontology for Biomedical Investigations | `OBI` | `assay` |
| MONDO (disease) | `MONDO` | `disease` |
| NCI Thesaurus | `NCIT` | `disease`, `endpoint` |
| UCUM / QUDT / Units of Measurement Ontology | `UO` (UCUM/QUDT in JSON-LD context) | `unit` |
| NCBI Taxonomy | `NCBITaxon` | `species` |
| Internal NAM vocabulary | `NAM` | project-specific endpoints/terms |

`source_entity_type` on a mapping is one of: `cell_type`, `anatomy`, `chemical`,
`assay`, `disease`, `unit`, `species`, `endpoint`.

---

## Mapping workflow

Mappings move through four states (`OntologyMapping::STATUS_*`):

```
unmapped ──▶ suggested ──▶ approved
                   │
                   └──────▶ rejected
```

- **unmapped** — no candidate term. `mapping_confidence = 0.0`.
- **suggested** — a seed term matched the source value (case-insensitive
  label/synonym match). `mapping_confidence = 0.6`. Auto-created on `POST .../map`.
- **approved** — a human reviewer confirmed the term. `mapping_confidence = 1.0`,
  `reviewedBy`/`reviewerNote` recorded, audit-logged.
- **rejected** — a human reviewer rejected the mapping (audit-logged).

### Manual human-approval model

The system never self-approves. Auto-suggestion is a convenience only; a mapping
counts as resolved **only** when a human PATCHes it to `approved`. Approval
requires a term to be present (either already suggested or supplied via
`term_curie`); approving a mapping with no term returns `422`.

### How "AI-ready" is blocked while mandatory terms are unmapped

Mappings flagged `mandatory: true` gate readiness and formal export:

- The **SemanticValidator** raises a **blocking error** for any mandatory mapping
  whose status is not `approved`.
- The **ReadinessScorer** `controlled_terminology` dimension scores by the ratio of
  approved mappings, and the **ExportReadinessGate** surfaces
  `mandatory_unmapped` and blocks formal package export.

So a project cannot present itself as AI-ready while any mandatory term is
unmapped, suggested-but-unconfirmed, or rejected-and-unremapped. See
[VALIDATION_RULES.md](./VALIDATION_RULES.md).

---

## Local seed vocabulary (15 terms)

Loaded from `standards/vocab/nam-seed-vocabulary.json` (vocabulary version `0.1`,
curated for the COU-HEP-001 liver-organoid demo):

| Label | CURIE | Source | Maps |
|---|---|---|---|
| hepatocyte-like cell | `CL:0002310` | Cell Ontology | cell_type |
| liver organoid | `CL:0000182` | Cell Ontology | cell_type |
| liver | `UBERON:0002107` | Uberon | anatomy |
| hepatotoxicity | `MONDO:0005359` | MONDO | disease |
| ATP viability assay | `OBI:0002119` | OBI | assay |
| LDH release assay | `OBI:0002994` | OBI | assay |
| mitochondrial membrane potential | `NCIT:C17610` | NCI Thesaurus | endpoint |
| reactive oxygen species | `CHEBI:26523` | ChEBI | endpoint |
| bile acid accumulation | `NCIT:C154834` | NCI Thesaurus | endpoint |
| acetaminophen | `CHEBI:46195` | ChEBI | chemical |
| kinase inhibitor | `CHEBI:38637` | ChEBI | chemical |
| micromolar | `UO:0000064` | UO / UCUM | unit |
| hour | `UO:0000032` | UO / UCUM | unit |
| percent | `UO:0000187` | UO / UCUM | unit |
| Homo sapiens | `NCBITaxon:9606` | NCBI Taxonomy | species |

Each term carries `label`, `curie`, `iri`, `definition`, `synonyms`, `source`, and
`term_version`. Auto-suggestion matches on `label` or any `synonym`
(case-insensitive), which is why demo endpoints like `mmp_jc1` (MMP) or
`lipid_peroxidation_index` (novel, not seeded) need human attention.

### Seed loader command

```bash
# Idempotent: creates terms by CURIE, skips existing.
docker compose exec api php bin/console app:load-ontology-seed
```

Reads `standards/vocab/nam-seed-vocabulary.json` and upserts each entry into
`namcore_ontology_term`.

---

## Endpoints

Base namespace: `/api/v1/ontology` and `/api/v1/projects/{id}`.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/api/v1/ontology/terms` | List/search terms. Query: `?prefix=CL`, `?q=liver` (label/CURIE/synonym). |
| `POST` | `/api/v1/ontology/terms` | Create a term (idempotent by `curie`). Body: `label`, `ontology_prefix`, `curie` (required), `iri`, `definition`, `synonyms[]`, `source`, `term_version`. |
| `POST` | `/api/v1/ontology/map` | Create a mapping + auto-suggest. Body: `project_id`, `source_entity_type`, `source_value`, `source_field?`, `source_entity_id?`, `mandatory?`. |
| `PATCH` | `/api/v1/ontology/mappings/{id}/approve` | Human approval. Body: `term_curie?`, `reviewer_note?`, `reviewed_by?`. Sets status `approved`, confidence `1.0`. |
| `PATCH` | `/api/v1/ontology/mappings/{id}/reject` | Human rejection. Body: `reviewer_note?`, `reviewed_by?`. |
| `GET` | `/api/v1/projects/{id}/ontology-mappings` | All mappings for a project + review summary. |

The project-mappings summary returns counts:
`total`, `approved`, `suggested`, `unmapped`, `rejected`, and
`mandatory_unresolved`.

```bash
# Example: suggest a mapping, then approve it.
curl -X POST http://localhost:8080/api/v1/ontology/map \
  -H 'Content-Type: application/json' \
  -d '{"project_id":"<ULID>","source_entity_type":"endpoint","source_value":"mitochondrial membrane potential","mandatory":true}'

curl -X PATCH http://localhost:8080/api/v1/ontology/mappings/<ULID>/approve \
  -H 'Content-Type: application/json' \
  -d '{"reviewed_by":"reviewer@example.com","reviewer_note":"Confirmed NCIT:C17610"}'
```

All create/approve/reject actions are written to the [audit log](./VALIDATION_RULES.md).
