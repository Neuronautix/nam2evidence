Core Concepts
=============

Project
-------

A project groups one evidence package. It has a name, drug name, sponsor, review
status, and links to all workspaces.

Context of Use
--------------

The Context of Use card states the specific question the NAM evidence is meant
to support. It anchors interpretation of the entire project.

NAM Study
---------

The NAM Study tab stores model-system and experimental metadata. The demo uses
NAMO-aligned fields such as model class, species, cell type, assay metadata,
outputs, and provenance.

Evidence Matrix
---------------

The Validation Matrix records evidence across a fitness-for-purpose framework.
Each item has a status such as met, partial, not met, or not applicable.

Claim Graph
-----------

Claims translate data and evidence into reviewable statements. Claims requiring
human review block export until approved.

NAM-CORE
--------

NAM-CORE is the app's proof-of-concept standardization layer. It turns raw
endpoint measurements, ontology mappings, provenance, and validation results
into reusable structured exports.

Review Gate
-----------

A review gate is a blocker that prevents formal export. Examples include
pending claims, mandatory unmapped ontology terms, semantic validation errors,
or missing provenance.

Readiness Score
---------------

The readiness score is an internal maturity heuristic. It helps identify gaps.
It is not a regulatory determination.
