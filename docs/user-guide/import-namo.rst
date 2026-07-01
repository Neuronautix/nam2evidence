Import NAMO Tab
===============

The Import NAMO tab lets you paste or upload NAMO-aligned YAML or JSON and map
it into the project NAM Study record.

Input methods
-------------

You can:

* paste YAML or JSON into the text box;
* upload a ``.yaml``, ``.yml``, or ``.json`` file.

Parsing
-------

The page tries JSON first, then YAML. A valid import must be a top-level object
or mapping. Arrays are rejected.

Minimum required content
------------------------

The parsed object should include at least one study identifier or name field:

* ``id`` or ``study_id``;
* ``name``, ``study_name``, or ``title``.

Mapped preview
--------------

After parsing succeeds, the page shows how the payload will map into:

* ``study_id``;
* ``study_name``;
* ``model_system``;
* ``experimental_design``;
* ``assay_metadata``;
* ``data_outputs``;
* ``provenance``;
* ``references``.

Confirm import
--------------

Click **Confirm import** only after checking the preview. The app saves the
mapped study and provides a link to the NAM Study page.

Common errors
-------------

Invalid YAML/JSON:
  Fix indentation, commas, quotes, or brackets.

Top-level array:
  Wrap the content in an object with study fields.

Missing name/id:
  Add a study identifier or title.
