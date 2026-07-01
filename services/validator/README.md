# NAM-CORE Validator

A small, self-contained Python sidecar for the NAM-CORE toolkit. It runs
SHACL validation over a NAM-CORE JSON-LD graph and converts flat table rows
into Parquet files packaged as a ZIP.

**POC — this is a proof-of-concept validator, NOT an official regulatory,
SEND, or accredited validator.** The bundled SHACL ruleset
(`shapes/nam-core-v0.1.ttl`) expresses toolkit semantics only.

## Endpoints

- `GET /health` — liveness check. Returns
  `{"status":"ok","service":"nam-core-validator"}`.

- `POST /validate` — body: `{ "jsonld": <NAM-CORE JSON-LD graph object>,
  "shapes_ttl": "<optional turtle string>" }`. Loads the JSON-LD into an
  rdflib graph, runs `pyshacl.validate(..., inference="rdfs")` against the
  supplied shapes (or the bundled `shapes/nam-core-v0.1.ttl`), and returns a
  normalized report:

  ```json
  {
    "conforms": true,
    "violation_count": 0,
    "warning_count": 0,
    "results": [
      {
        "severity": "Violation",
        "focus_node": "...",
        "path": "... | null",
        "message": "...",
        "source_shape": "... | null",
        "value": "... | null"
      }
    ]
  }
  ```

  Bad input (e.g. malformed JSON-LD or shapes) returns HTTP 400 with
  `{"error": "..."}`.

- `POST /parquet` — body:
  `{ "tables": { "endpoint_measurements": [ {..row..}, ... ], "samples": [...],
  "exposures": [...], "assays": [...], "validation_evidence": [...] } }`.
  Each non-empty table (list of flat dict rows) becomes a `<name>.parquet`
  file inside an in-memory ZIP. Ragged rows are normalized to the union of all
  keys (missing values become `null`). Returns the ZIP as `application/zip`
  (`nam-core-parquet.zip`). Empty/missing tables are skipped; if no tables are
  usable, returns HTTP 400.

## How the API uses it

Runs in Docker Compose alongside the Symfony API. The API calls `/validate`
with the JSON-LD export from
`GET /api/v1/projects/{id}/exports/jsonld` and surfaces violations to the user.
`/parquet` is used to produce the tabular data bundle for download.

## Run standalone

```bash
pip install -r requirements.txt
python app.py
```

The service listens on `0.0.0.0:8000`.

## Tests

```bash
pip install -r requirements.txt
pytest -q
```
