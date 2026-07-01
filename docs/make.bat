@echo off
setlocal

set SPHINXBUILD=sphinx-build
set SOURCEDIR=%~dp0
set BUILDDIR=%~dp0_build

if "%1" == "" goto help

%SPHINXBUILD% -M %1 %SOURCEDIR% %BUILDDIR% %SPHINXOPTS% %O%
goto end

:help
%SPHINXBUILD% -M help %SOURCEDIR% %BUILDDIR% %SPHINXOPTS% %O%

:end
endlocal
