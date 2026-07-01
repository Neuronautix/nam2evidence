Endpoint Data Tab
=================

The Endpoint Data tab imports and standardizes endpoint measurement CSV files.

Workflow
--------

The page has three stages:

1. paste or upload CSV;
2. preview and map columns;
3. import and inspect stored measurements.

CSV input
---------

Paste CSV text or upload a ``.csv`` file. The app expects rows describing
endpoint measurements, such as endpoint label, value, unit, timepoint, sample,
assay, exposure, batch, and provenance fields.

Preview
-------

Click **Preview** before importing. The preview shows:

* detected columns;
* suggested target-field mappings;
* sample rows;
* total row count.

Column mapping
--------------

For each CSV column, choose a NAM-CORE target field or ignore it. Review the
suggestions carefully; automated mapping is only a starting point.

Import summary
--------------

After import, the summary shows:

* imported row count;
* total rows;
* errors;
* warnings;
* unit normalizations;
* blocking status.

Stored measurements
-------------------

The table shows standardized endpoint measurements with:

* endpoint;
* value;
* unit;
* timepoint;
* QC status;
* validation status;
* provenance status.

Common blockers
---------------

Missing unit:
  Add or map the measurement unit.

Non-numeric value:
  Correct the value or mark exclusion status where appropriate.

Missing provenance:
  Link the row to a raw file or source data record.

Unmapped endpoint:
  Use the Ontology tab to approve or create a controlled mapping.
