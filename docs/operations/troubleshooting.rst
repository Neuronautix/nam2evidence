Troubleshooting
===============

Project list is empty
---------------------

Check that the seed commands ran:

.. code-block:: powershell

   docker compose exec -T api php bin/console app:load-demo-data --force
   docker compose exec -T api php bin/console app:load-ontology-seed
   docker compose exec -T api php bin/console app:load-namcore-demo

API error in a tab
------------------

Check the backend URL:

.. code-block:: text

   http://localhost:8080/api

If the API is unavailable:

.. code-block:: powershell

   docker compose ps
   docker compose up -d api

Ontology seed file missing
--------------------------

The API container must mount ``standards``:

.. code-block:: yaml

   - ./standards:/var/www/standards:ro

Then recreate the API container:

.. code-block:: powershell

   docker compose up -d api

Symfony says DebugBundle is missing
-----------------------------------

If ``castor start`` fails with ``Class "Symfony\\Bundle\\DebugBundle\\DebugBundle"
not found``, the API container is running in ``APP_ENV=dev`` but the Docker
``api_vendor`` volume was created before development Composer dependencies were
installed.

Run:

.. code-block:: powershell

   docker compose exec -T api composer install --prefer-dist --no-interaction --optimize-autoloader --no-scripts
   castor start

If the API container cannot start at all, rebuild and recreate the stack:

.. code-block:: powershell

   docker compose down
   docker compose up -d --build
   castor start

NAM-CORE demo CSV missing
-------------------------

The API container must mount ``demo``:

.. code-block:: yaml

   - ./demo:/var/www/demo:ro

Then rerun:

.. code-block:: powershell

   docker compose exec -T api php bin/console app:load-namcore-demo

Export disabled
---------------

Usually a claim still requires review. Open the Claim Graph tab and approve or
reject pending claims. Also inspect Semantic Validation and Readiness for
blocking gaps.

Validator sidecar unavailable
-----------------------------

Semantic Validation still runs native checks. To verify the sidecar:

.. code-block:: text

   http://localhost:8000/health

If unavailable:

.. code-block:: powershell

   docker compose up -d validator

Frontend does not refresh
-------------------------

Refresh the browser. If data still looks stale, restart the stack:

.. code-block:: powershell

   docker compose down
   castor start
