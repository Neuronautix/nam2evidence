External Statements Tab
=======================

The External Statements tab holds scientific statements captured from outside
the project, such as statements published in the TIB Knowledge Loom. Each one
is identified by its own DOI and carries the data and code links its source
declared.

Purpose
-------

Published findings often answer guidance expectations that the project's own
data cannot, for example biological relevance of the model system or comparison
with an established method. This tab lets you keep such findings next to the
project with their exact origin, and decide, as a named reviewer, whether and
how each one relates to a requirement, a claim or a validation-evidence row.

What you see
------------

For each captured statement:

* the verbatim text, its DOI, record, version and capture hash;
* access scope (public, restricted, unknown) and licence;
* capture status (unverified until a reviewer confirms it matches the source);
* the declared study context and the datasets or code behind the statement;
* its links to project records, each with a relationship and a review status.

Human review rule
-----------------

The tool only suggests. A link counts for nothing until you approve it, and
every decision is recorded with your reviewer identity in the audit trail.

1. Enter your reviewer identity in the box at the top right.
2. Open the statement's raw capture if you need to check what was captured.
3. Confirm the capture matches its source. Only public, verified statements can
   be approved as *supporting* evidence.
4. For each suggested link, approve it, reject it, or reject it as *not
   comparable* and say why (organ, endpoint class, exposure, population).
5. Create your own link when the tool suggested nothing.

What this tab never does
------------------------

* It never turns an external statement into validation evidence. A
  decision-informing claim still needs the project's own evidence rows.
* It never changes a claim's review status, the readiness score or the export
  gate. Pending suggestions appear as warnings in Semantic Validation only.
* It never exports restricted or unknown-scope statements.
