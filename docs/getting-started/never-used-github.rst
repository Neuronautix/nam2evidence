Never Used GitHub Before?
=========================

This page is for scientists and regulatory professionals who have landed on this
project and are not sure what they are looking at. Nothing here assumes you have
written code. If you already use GitHub, skip to
:doc:`non-coder-start`.

What is this website?
---------------------

GitHub is a place where software is stored publicly so anyone can read it, copy
it, and suggest changes. A **repository** (or "repo") is one project's folder of
files. You are looking at the repository for nam2evidence.

Two things follow from that:

* **You can read everything.** Nothing is hidden. That is deliberate: a tool that
  makes claims about regulatory evidence should be inspectable.
* **The front page is a file.** The text on the repository's front page comes
  from a file called ``README.md``. It is documentation, not an application.
  Reading it does not run anything.

What can I do without installing anything?
------------------------------------------

Quite a lot:

* Read the **screenshots** in the README to see what the application looks like.
* Read the :doc:`demo-tour` to follow what a full session looks like.
* Read the :doc:`../glossary` if a term is unfamiliar.
* Read ``docs/REGULATORY_POSITIONING.md`` for what the tool does and does not
  claim.

You only need to install something if you want to click around the application
yourself with your own data.

Getting the files onto your computer
------------------------------------

There are two ways. Both give you the same files.

**Option A — download a ZIP (no extra software)**

1. Go to the repository page:
   https://github.com/Neuronautix/nam2evidence
2. Click the green **Code** button near the top right.
3. Choose **Download ZIP**.
4. Find the downloaded file (usually in your ``Downloads`` folder) and unzip it.
5. You now have a folder called ``nam2evidence-main``. Remember where you put it.

This is the simplest route. The drawback is that updating later means downloading
a fresh ZIP.

**Option B — use Git (updates with one command later)**

1. Install `Git <https://git-scm.com/downloads>`_, accepting the default options.
2. Open PowerShell (press the Windows key, type ``PowerShell``, press Enter).
3. Choose where to put the folder, for example your Documents folder:

   .. code-block:: powershell

      cd $HOME\Documents

4. Copy the repository:

   .. code-block:: powershell

      git clone https://github.com/Neuronautix/nam2evidence.git

5. You now have a folder called ``nam2evidence`` inside Documents.

Later, ``git pull`` inside that folder fetches the newest version.

Words you will keep seeing
--------------------------

.. list-table::
   :header-rows: 1
   :widths: 22 78

   * - Word
     - What it means here
   * - Repository / repo
     - One project's folder of files, stored on GitHub.
   * - Clone
     - Make a copy of that folder on your own computer.
   * - Commit
     - One saved change, with a note explaining it.
   * - Branch
     - A parallel version of the files, used to work on something without
       disturbing the main version.
   * - Pull request
     - A proposal to merge a branch's changes into the main version, so others
       can review it first.
   * - Issue
     - A written report of a bug, question, or request. This is where to write
       if something does not work.
   * - Terminal / PowerShell
     - A window where you type commands instead of clicking. The setup steps use
       it, but you only ever copy and paste.
   * - Docker
     - Software that runs the application in a self-contained box, so you do not
       have to install a database and a web server yourself.

Something went wrong. Where do I ask?
-------------------------------------

Open an **issue** on the repository: go to the **Issues** tab, click **New
issue**, and describe what you did and what happened. Include the command you
ran and any error text — pasting the exact message helps far more than
describing it.

See also ``SUPPORT.md`` in the repository root.

Next step
---------

When you are ready to run the application:

* :doc:`non-coder-start` — installing and starting it, step by step.
* :doc:`demo-tour` — what to look at once it is running.
