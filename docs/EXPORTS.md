# Exports

> **Positioning.** Exports are **standardization artifacts** — structured evidence
> packages for downstream review, reuse, and computation. They are **not** an
> official regulatory submission, a SEND/CDISC deliverable, or a claim of
> validation. Formal package formats are subject to the aggregated
> [review gate](./VALIDATION_RULES.md); data-level serializations carry
> conservative POC disclaimers. Everything **requires qualified human review**
> before inclusion in any filing.

Two families of endpoints:

- **NAM-CORE v1 exports** — reusable, standards-aligned serializations of the
  canonical graph: `/api/v1/projects/{id}/exports/{jsonld,turtle,isa-tab,parquet,ro-crate}`
  (`api/src/Controller/V1/NamCoreExportController.php`).
- **Legacy export** — the original evidence-package dossier in JSON/CSV/MD/TXT:
  `POST /api/projects/{id}/export/download?format=…`
  (`api/src/Controller/ExportController.php`), plus `/generate` and `/history`.

The canonical graph is built by `ProjectGraphBuilder` (JSON-LD via
`standards/context/nam-core.context.jsonld`; flat tables for tabular formats).

---

## JSON

- **Endpoint:** legacy `POST /api/projects/{id}/export/download?format=json`;
  also embedded as `nam-core.json` inside the RO-Crate.
- **Included:** complete machine-readable evidence package — project, COU, study,
  validation matrix, claims, eCTD mappings (legacy), or the canonical NAM-CORE
  graph (RO-Crate variant).
- **Consumer:** general integration / archival; **AI pipelines** for the NAM-CORE
  graph variant.
- **Disclaimer:** internal evidence organization only; not a submission.

## CSV

- **Endpoint:** legacy `…/export/download?format=csv`; canonical
  `endpoint_measurements.csv` inside RO-Crate; and the Parquet CSV fallback.
- **Included:** the validation evidence matrix (legacy) or the canonical
  endpoint-measurements table (NAM-CORE).
- **Consumer:** **regulators/scientists** wanting a spreadsheet appendix;
  statisticians.
- **Disclaimer:** tabular convenience export; not a submission dataset.

## Parquet

- **Endpoint:** `GET /api/v1/projects/{id}/exports/parquet` (ZIP).
- **Included:** the flat NAM-CORE tables (`ProjectGraphBuilder::toTables()`),
  including `endpoint_measurements`.
- **Consumer:** **AI / data-science pipelines** (columnar, computable).
- **Implementation:** real Parquet is produced by the **pyarrow sidecar**
  (`services/validator`, via `ValidatorSidecarClient`). When the sidecar is not
  configured, the endpoint falls back to **CSV-in-ZIP** with a `README.txt`
  explaining how to enable native `.parquet` (set `VALIDATOR_URL`). The endpoint
  always yields a computable artifact.
- **Disclaimer:** POC computability artifact.

## JSON-LD

- **Endpoint:** `GET /api/v1/projects/{id}/exports/jsonld` (`application/ld+json`).
- **Included:** the full NAM-CORE graph with the
  `standards/context/nam-core.context.jsonld` `@context` (dcterms, PROV, OBO,
  QUDT, schema.org). This is the exact document the SHACL validator evaluates.
- **Consumer:** **AI pipelines**, semantic-web / knowledge-graph tooling.
- **Disclaimer:** POC standardization serialization.

## RDF / Turtle

- **Endpoint:** `GET /api/v1/projects/{id}/exports/turtle` (`text/turtle`).
- **Included:** the same graph serialized as RDF/Turtle (`TurtleSerializer` over
  the JSON-LD).
- **Consumer:** RDF triple stores, SPARQL, **AI/knowledge-graph pipelines**.
- **Disclaimer:** POC standardization serialization.

## ISA-Tab

- **Endpoint:** `GET /api/v1/projects/{id}/exports/isa-tab` (ZIP;
  `IsaTabExporter`).
- **Included:** Investigation / Study / Assay tab-delimited files describing the
  study, samples, exposures, and measurements.
- **Consumer:** **scientists** and life-science data repositories using the ISA
  framework.
- **Disclaimer:** ISA-Tab is provided for **interoperability, not submission** —
  it is not an eCTD or SEND deliverable.

## RO-Crate

- **Endpoint:** `GET /api/v1/projects/{id}/exports/ro-crate` (ZIP; `RoCrateBuilder`).
- **Included:** a self-describing research object bundling `nam-core.json`,
  `metadata.jsonld`, `endpoint_measurements.csv`, `validation-report.json`,
  `readiness-report.json`, `dossier.md`, `ectd-mapping.txt`, `provenance.json`, and
  `ro-crate-metadata.json`. The crate's `nam:packageStatus` is `complete` only when
  the review gate is open; otherwise `draft`.
- **Consumer:** **regulators/scientists/AI pipelines** — a portable, reviewable
  package.
- **Disclaimer:** a **formal format** — subject to the review gate. Marked `draft`
  while blockers remain; still not an official submission.

## Markdown

- **Endpoint:** legacy `…/export/download?format=md`; `dossier.md` inside RO-Crate.
- **Included:** human-readable evidence dossier — project/drug header, a prominent
  POC disclaimer, standardization status (validation errors/warnings, readiness
  score, gate status), and readiness dimensions.
- **Consumer:** **human reviewers**; convertible to Word/PDF.
- **Disclaimer:** internal dossier; requires qualified human review.

## TXT / eCTD structure

- **Endpoint:** legacy `…/export/download?format=txt`; `ectd-mapping.txt` inside
  RO-Crate.
- **Included:** a proposed eCTD Module 4 folder structure / mapping proposal with
  document placement (e.g. 4.2.3.7.3, 2.6.6, 2.6.2).
- **Consumer:** **regulatory affairs** planning document placement.
- **Disclaimer:** a **structured proposal, not regulatory advice**; placement
  depends on context of use and regulatory strategy.

---

## Format summary

| Format | Endpoint | Primary consumer | Formal? |
|---|---|---|---|
| JSON | `…/export/download?format=json` | Integration / AI | draft |
| CSV | `…/export/download?format=csv` | Regulator / scientist | draft |
| Parquet | `/exports/parquet` | AI / data science | data-level |
| JSON-LD | `/exports/jsonld` | AI / semantic web | data-level |
| RDF/Turtle | `/exports/turtle` | AI / knowledge graph | data-level |
| ISA-Tab | `/exports/isa-tab` | Scientist (interop) | data-level |
| RO-Crate | `/exports/ro-crate` | Regulator / scientist / AI | **formal (gated)** |
| Markdown | `…/export/download?format=md` | Human reviewer | draft |
| TXT / eCTD | `…/export/download?format=txt` | Regulatory affairs | draft |

Every export writes an entry to the [audit log](./VALIDATION_RULES.md).

## Related documents

- [NAM_CORE_SCHEMA.md](./NAM_CORE_SCHEMA.md)
- [VALIDATION_RULES.md](./VALIDATION_RULES.md)
- [REGULATORY_POSITIONING.md](./REGULATORY_POSITIONING.md)
