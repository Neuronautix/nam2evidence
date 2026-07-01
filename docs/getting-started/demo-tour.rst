Demo Tour
=========

The default demo project is a hepatotoxicity example: CX-4471 tested in
iPSC-derived liver organoids. It is designed to show both useful structure and
intentional data gaps.

Open the demo
-------------

1. Start the app with ``castor start``.
2. Open http://localhost:3000.
3. Select the demo project from the project list.
4. Use the left sidebar to move through the workspaces.

Suggested tour order
--------------------

Use this order the first time you explore the app:

1. **Overview** - see the project summary and workspace cards.
2. **Context of Use** - read the regulatory question and intended use.
3. **NAM Study** - inspect model-system metadata and assay details.
4. **Validation Matrix** - see the evidence framework and status labels.
5. **Claim Graph** - review claims that must be human-approved.
6. **Endpoint Data** - inspect standardized measurements and unresolved rows.
7. **Ontology** - approve or reject suggested controlled-vocabulary mappings.
8. **Semantic Validation** - see blockers grouped by workspace.
9. **Readiness** - read the FAIR/AI-readiness score and recommended fixes.
10. **Provenance** - check whether endpoint values trace back to raw files.
11. **Audit** - review recorded changes.
12. **Export Center** - download packages once review gates allow it.

What the before-state demo teaches
----------------------------------

The normal demo intentionally contains blockers:

* a missing endpoint unit;
* an unmapped endpoint;
* a donor with missing passage metadata;
* a measurement without raw-file provenance;
* claims that still require human review.

These are not bugs. They show how the app prevents premature formal packaging.

What the corrected demo teaches
-------------------------------

Start with:

.. code-block:: powershell

   castor start --corrected

The corrected state is useful when you want to see the cleaner downstream
experience after blockers are resolved.

Demo interpretation
-------------------

The demo is synthetic proof-of-concept data. Use it to learn the workflow and
screen behavior. Do not interpret it as real scientific evidence.
