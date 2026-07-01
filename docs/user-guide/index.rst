User Guide
==========

This guide explains each screen in the nam2evidence application. It is written
for people who need to review, curate, standardize, and package NAM-derived
evidence, not for people editing the code.

Basic workflow
--------------

The app is organized around a project. Inside each project, use the sidebar
tabs from top to bottom:

1. define the context of use;
2. inspect or import NAM study metadata;
3. review validation evidence;
4. review claims;
5. map evidence to eCTD Module 4;
6. standardize endpoints and ontology mappings;
7. run semantic validation and readiness checks;
8. inspect provenance and audit trail;
9. export only after review gates are cleared.

Human review rule
-----------------

The app can structure evidence and highlight blockers. It cannot decide that a
NAM is valid, acceptable, or sufficient for a regulatory decision. Treat every
export as review-supportive material that needs qualified human sign-off.

.. toctree::
   :maxdepth: 1

   concepts
   home-projects
   project-overview
   context-of-use
   nam-study
   import-namo
   validation-matrix
   claim-graph
   ectd-mapping
   endpoints
   ontology
   semantic-validation
   readiness
   provenance
   audit
   export-center
