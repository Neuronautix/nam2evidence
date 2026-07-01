Admin Commands
==============

Most users should use Castor.

Start standard demo
-------------------

.. code-block:: powershell

   castor start

Start corrected demo
--------------------

.. code-block:: powershell

   castor start --corrected

Install Castor on Windows
-------------------------

.. code-block:: powershell

   .\scripts\install-castor.ps1 -InstallPhpWithWinget

Docker commands used by Castor
------------------------------

The Castor startup task runs these core operations:

.. code-block:: powershell

   docker compose up -d --build
   docker compose exec -T api php bin/console doctrine:migrations:migrate --no-interaction
   docker compose exec -T api php bin/console app:load-demo-data --force
   docker compose exec -T api php bin/console app:load-ontology-seed
   docker compose exec -T api php bin/console app:load-namcore-demo

For corrected NAM-CORE demo data:

.. code-block:: powershell

   docker compose exec -T api php bin/console app:load-namcore-demo --corrected

Stop containers
---------------

.. code-block:: powershell

   docker compose down

Recreate only API container
---------------------------

Use this after changing API mounts or environment:

.. code-block:: powershell

   docker compose up -d api

Build Sphinx documentation
--------------------------

Install Sphinx if needed:

.. code-block:: powershell

   python -m pip install -r docs\requirements.txt

Build HTML docs:

.. code-block:: powershell

   python -m sphinx -b html docs docs\_build\html

Open ``docs\_build\html\index.html`` in a browser.
