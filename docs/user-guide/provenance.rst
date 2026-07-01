Provenance Tab
==============

The Provenance tab shows raw-to-processed lineage for endpoint measurements.

Purpose
-------

Reviewers need to know where values came from. Provenance connects standardized
measurements to assays, raw files, and processing steps.

Lineage table
-------------

The table shows:

* endpoint;
* lineage path;
* traced or missing-provenance status.

Missing provenance
------------------

If measurements lack raw-file provenance, the tab shows a warning. Missing
provenance can block readiness and formal export workflows.

Validation issues
-----------------

The lower section filters semantic validation issues related to provenance,
lineage, and raw data.

How to use it
-------------

1. Identify measurements marked missing provenance.
2. Check the Endpoint Data tab for the same rows.
3. Update source data or import mappings so raw files are linked.
4. Recheck Semantic Validation and Readiness.

Good provenance
---------------

Good provenance should answer:

* what raw file or source produced this value?
* what assay and sample does it belong to?
* what processing or script generated the standardized value?
* who reviewed or changed it?
