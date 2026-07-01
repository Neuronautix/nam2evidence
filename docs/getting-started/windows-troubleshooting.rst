Windows Startup Troubleshooting
===============================

This page covers common Windows problems during startup.

``castor`` is not recognized
----------------------------

Cause:
  Castor is not installed, or the current PowerShell window has not picked up
  the updated user PATH.

Fix:

.. code-block:: powershell

   .\scripts\install-castor.ps1 -InstallPhpWithWinget

Then open a new PowerShell window and run:

.. code-block:: powershell

   castor --version

If it still fails:

.. code-block:: powershell

   C:\Users\damie\.local\bin\castor.cmd --version
   C:\Users\damie\.local\bin\castor.cmd start

Castor says PHP 8.4.1+ is required
----------------------------------

Current Castor releases require PHP 8.4.1 or newer. The project installer
handles this by installing a private PHP 8.4 runtime for Castor:

.. code-block:: powershell

   .\scripts\install-castor.ps1 -InstallPhpWithWinget

This does not require replacing every PHP on your system. The generated
``castor.cmd`` shim points directly at the private PHP runtime.

winget PHP 8.4 download returns 404
-----------------------------------

The winget PHP manifest can temporarily point to an old PHP ZIP that no longer
exists. The installer detects this and falls back to the official PHP Windows
"latest" ZIP.

If you see a winget error first, keep reading the output. The fallback should
continue with a direct PHP ZIP download.

Docker access is denied
-----------------------

Symptoms include:

.. code-block:: text

   open //./pipe/docker_engine: Access is denied

Fixes:

* Start Docker Desktop.
* Wait until Docker Desktop is fully running.
* Make sure your Windows user can use Docker Desktop.
* If your environment requires elevation, open PowerShell as Administrator.

Seed vocabulary file not found
------------------------------

Older compose configuration mounted only the API directory. The fixed
configuration mounts these repo-level directories into the API container:

.. code-block:: yaml

   - ./standards:/var/www/standards:ro
   - ./demo:/var/www/demo:ro

Apply it with:

.. code-block:: powershell

   docker compose up -d api
   docker compose exec -T api php bin/console app:load-ontology-seed

Port already in use
-------------------

Default ports are:

* frontend: ``3000``
* API: ``8080``
* validator: ``8000``
* PostgreSQL: ``5432``

If another application uses one of those ports, stop that application or edit
``docker-compose.yml`` to publish a different host port.

Hard reset for containers
-------------------------

This stops containers but keeps the database volume:

.. code-block:: powershell

   docker compose down
   castor start

Use volume deletion only when you intentionally want to discard local database
state.
