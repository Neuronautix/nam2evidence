NAM Study Tab
=============

The NAM Study tab describes the model system, experimental design, assays,
outputs, and provenance.

Main sections
-------------

Study header
  Study title, study ID, linked Context of Use, and creation date.

Model System
  NAMO class, species, cell type, tissue origin, culture conditions, vendor,
  catalog number, passage, and maturity indicators.

Experimental Design
  JSON-backed study design fields such as dose design, timepoints, controls, or
  replication.

Assay Metadata
  Primary endpoints, methods, readouts, units, instrument, and software.

Data Outputs
  Key derived metrics such as TC50, NOAEL, safety multiples, and endpoint
  response summaries.

Provenance
  Source files, scripts, versioning, and other reproducibility details.

Editing JSON sections
---------------------

Some sections are edited as JSON objects.

1. Click **Edit**.
2. Update the JSON carefully.
3. Fix any syntax error shown under the field.
4. Click **Save**.

If the Save button is disabled, one of the JSON boxes is invalid.

Review checklist
----------------

Before relying on the study:

* confirm species, cell type, tissue origin, and NAMO class;
* confirm passage and maturity indicators;
* confirm assays have methods and units;
* confirm key outputs are traceable to source data;
* confirm provenance describes files, scripts, and versions.
