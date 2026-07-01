Reference
=========

This Sphinx guide focuses on starting and using the app. The repository also
contains deeper technical reference documents in Markdown:

.. list-table::
   :header-rows: 1

   * - Document
     - Purpose
   * - ``NAM_CORE_SCHEMA.md``
     - NAM-CORE v0.1 entity and field reference.
   * - ``ONTOLOGY_MAPPING.md``
     - Supported ontologies and mapping workflow.
   * - ``VALIDATION_RULES.md``
     - Structural, semantic, and review-gate validation logic.
   * - ``EXPORTS.md``
     - Export formats, endpoints, intended consumers, and disclaimers.
   * - ``REGULATORY_POSITIONING.md``
     - Explicit regulatory positioning and limitations.
   * - ``POC_DEMO_SCRIPT.md``
     - Detailed demo narrative and expected outputs.

Build outputs
-------------

Generated Sphinx HTML is written to ``docs/_build/html`` when you run:

.. code-block:: powershell

   python -m sphinx -b html docs docs\_build\html

API documentation
-----------------

When Docker is running, API documentation is available at:

.. code-block:: text

   http://localhost:8080/api/docs
