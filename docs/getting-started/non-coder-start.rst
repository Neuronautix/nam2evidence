Start the App Without Coding
============================

This page assumes you are on Windows and want to run the app, not develop it.
You only need Docker Desktop, PowerShell, and the commands below.

What you will open
------------------

After the app starts, use these addresses in your browser:

.. list-table::
   :header-rows: 1

   * - What
     - Address
     - Use it for
   * - nam2evidence app
     - http://localhost:3000
     - Main user interface
   * - API documentation
     - http://localhost:8080/api/docs
     - Technical API browser
   * - Validator health
     - http://localhost:8000/health
     - Check validator sidecar availability

One-time setup
--------------

1. Install Docker Desktop.

   * Start Docker Desktop before running nam2evidence.
   * Wait until Docker says it is running.

2. Open PowerShell in the repository folder.

   If the repository is still in its original folder name, this is:

   .. code-block:: powershell

      cd C:\Users\damie\Documents\GitHub\NAMO-to-IND-Mapper

3. Install Castor, the task runner used by the project.

   .. code-block:: powershell

      .\scripts\install-castor.ps1 -InstallPhpWithWinget

   The installer handles the current Windows situation where the winget PHP 8.4
   package can point at a stale download. If that happens, it downloads the
   official PHP 8.4 ZIP into your user-local bin folder and makes Castor use
   that private runtime.

4. Open a new PowerShell window if the installer says PATH was updated.

5. Confirm Castor works.

   .. code-block:: powershell

      castor --version

Start the application
---------------------

Run:

.. code-block:: powershell

   castor start

This does the routine startup work for you:

* builds and starts Docker services;
* applies database migrations;
* loads the demo project;
* loads the seed ontology vocabulary;
* loads the NAM-CORE demo data.

When the command finishes, open:

.. code-block:: text

   http://localhost:3000

Start with corrected demo data
------------------------------

The normal demo intentionally includes issues so you can see blockers and review
workflows. To load a cleaner "resolved" state instead:

.. code-block:: powershell

   castor start --corrected

If PowerShell still cannot find castor
--------------------------------------

Open a new PowerShell window first. If it still fails, run Castor by its full
installed path:

.. code-block:: powershell

   C:\Users\damie\.local\bin\castor.cmd start

If you do not want to use Castor at all, run:

.. code-block:: powershell

   .\start.ps1

Stop the application
--------------------

To stop the Docker services:

.. code-block:: powershell

   docker compose down

This stops the containers but keeps the database volume. Your local demo data
remains available when you start again.

Reset the demo data
-------------------

To reload the standard before-state demo:

.. code-block:: powershell

   castor start

To reload the corrected state:

.. code-block:: powershell

   castor start --corrected

What not to do
--------------

Do not use outputs as a regulatory submission without qualified human review.
Do not treat green checks as regulatory acceptance. The app is a standardization
and evidence-organization tool, not an authority.
