Semantic Validation Tab
=======================

The Semantic Validation tab checks NAM-CORE data against structural and semantic
rules.

Purpose
-------

This screen helps identify data gaps that affect standardization, provenance,
review gates, and export readiness.

Top status
----------

The header shows:

* conforms or does not conform;
* error count;
* warning count;
* blocking count;
* completion percentage;
* whether the pyshacl sidecar was available.

Errors and warnings
-------------------

Issues are grouped by workspace. Expand a group to see:

* rule;
* entity;
* field;
* message;
* recommended fix;
* blocking status.

How to resolve issues
---------------------

1. Read the workspace group.
2. Open the related sidebar tab.
3. Fix the missing or invalid data.
4. Return to Semantic Validation and refresh by reloading the page.

Examples
--------

Missing donor passage:
  Fix in study or NAM-CORE sample metadata.

Mandatory ontology mapping unresolved:
  Approve or correct mapping in the Ontology tab.

Missing raw-file provenance:
  Review the Provenance and Endpoint Data tabs.

Sidecar note
------------

The validator sidecar is optional. If unavailable, the app still runs native
checks. Sidecar availability provides an RDF/SHACL second opinion.
