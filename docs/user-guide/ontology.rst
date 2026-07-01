Ontology Tab
============

The Ontology tab reviews mappings from source values to controlled vocabulary
terms.

Purpose
-------

Ontology mapping makes data more reusable and machine-readable. It also supports
FAIR and AI-readiness checks.

Summary cards
-------------

The page summarizes:

* total mappings;
* approved mappings;
* suggested mappings;
* unmapped values;
* rejected mappings;
* mandatory unresolved mappings.

Mandatory unresolved mappings
-----------------------------

Mandatory unresolved mappings can block AI-ready or formal package status.
Resolve these before export.

Approve a mapping
-----------------

1. Review the source value.
2. Review the suggested term label and CURIE.
3. Click **Approve** if the term is correct.

Reject a mapping
----------------

Click **Reject** when the suggested term is wrong. Rejected mappings should be
followed by a better mapping process before formal use.

Browse ontology terms
---------------------

Use the search box to find terms in the local seed vocabulary. Results show
label, CURIE, ontology prefix, and definition when available.

Review guidance
---------------

Ontology approval is human-in-the-loop. Do not approve a term just because it is
close. It must represent the source value in the project context.
