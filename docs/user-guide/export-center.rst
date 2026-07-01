Export Center Tab
=================

The Export Center packages project evidence into downloadable formats.

Review gate
-----------

The top panel shows whether export is enabled. Pending human-review claims block
legacy dossier export.

If blocked:

1. open the Claim Graph tab;
2. review pending claims;
3. approve only justified claims;
4. return to Export Center.

Legacy dossier exports
----------------------

The Export Center provides:

JSON Package
  Complete machine-readable evidence package.

Validation Evidence Matrix CSV
  CSV export of validation evidence items.

Markdown Dossier
  Human-readable dossier suitable for conversion to Word or PDF.

eCTD Module 4 Folder Map
  Text representation of proposed evidence placement.

NAM-CORE reusable exports
-------------------------

The reusable exports include:

JSON-LD
  Linked-data JSON export.

RDF/Turtle
  RDF graph in Turtle syntax.

ISA-Tab ZIP
  Experimental metadata interoperability package.

Parquet
  Columnar dataset for analytics. Uses the validator sidecar when available and
  falls back as documented by the app.

RO-Crate ZIP
  Bundle containing NAM-CORE JSON, JSON-LD, endpoint CSV, validation report,
  readiness report, Markdown dossier, eCTD text, and provenance.

Export warning
--------------

Downloaded outputs are review-supportive artifacts. They are not regulatory
submissions, regulatory advice, or acceptance guarantees.
