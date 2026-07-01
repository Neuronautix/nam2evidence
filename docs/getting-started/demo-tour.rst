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

1. **Overview** - start with the standardization status hero, readiness score,
   before/after panel, top blockers, and audience-specific paths.
2. **Endpoint Data** - inspect the raw-to-canonical workflow: CSV import,
   column mapping, unit normalization, validation summary, and stored
   ``EndpointMeasurement`` rows.
3. **Ontology** - approve or reject controlled-vocabulary mappings.
4. **Semantic Validation** - see blockers grouped by workspace.
5. **Readiness** - read the FAIR/AI-readiness score and recommended fixes.
6. **Provenance** - check whether endpoint values trace back to raw files.
7. **Context of Use** - read the regulatory question and intended use.
8. **NAM Study** - inspect model-system metadata and assay details.
9. **Validation Matrix** - see the evidence framework and status labels.
10. **Claim Graph** - review claims that must be human-approved.
11. **eCTD Mapping** - inspect one downstream regulatory consumer of the
    standardized package.
12. **Audit** - review recorded changes.
13. **Export Center** - download packages once review gates allow it.

What the before-state demo teaches
----------------------------------

The normal demo intentionally contains blockers:

* a missing endpoint unit;
* an unmapped endpoint;
* a donor with missing passage metadata;
* a measurement without raw-file provenance;
* claims that still require human review.

These are not bugs. They show how the app prevents premature formal packaging.

What to foreground in a live demo
---------------------------------

Lead with this story:

   Before standardization, a NAM study is a spreadsheet, a protocol note, and a
   regulatory argument. After, it is a validated, ontology-linked,
   provenance-aware, context-of-use evidence package for scientists, reviewers,
   and AI pipelines.

Then show three concrete transformations:

* **Scientist** - Endpoint Data turns messy rows into canonical endpoint
  measurements with units, assays, samples, and provenance flags.
* **AI pipeline** - Ontology and exports create controlled terms and
  machine-readable JSON-LD, RDF/Turtle, Parquet, ISA-Tab, and RO-Crate outputs.
* **Reviewer** - Semantic Validation, Readiness, Claim Graph, and Audit expose
  blockers, human review state, and defensible traceability.

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
