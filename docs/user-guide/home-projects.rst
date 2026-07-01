Home and Projects
=================

The home screen lists projects and lets you create a new project.

What you see
------------

The header shows the app name and a **New Project** button. The main page shows
project cards with status and summary details.

Create a project
----------------

1. Click **New Project**.
2. Enter the project name, description, drug name, and sponsor.
3. Save the project.
4. Open the project card to enter the workspace area.

Demo projects
-------------

After ``castor start`` the demo project should be available. If the project list
is empty, check that the API is running and that the seed commands completed.

Data mode
---------

The home page displays the current data mode. In normal Docker startup it uses
API mode, meaning the frontend reads from the Symfony backend. Demo mode is a
standalone frontend mode used mainly for local UI exploration.
