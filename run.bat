@echo off
REM OpenPoly M-01 one-shot runner (ASCII only, no encoding traps)
REM Usage: just double-click this file in Windows Explorer

setlocal EnableExtensions EnableDelayedExpansion

cd /d "%~dp0"

echo.
echo ============================================================
echo   OpenPoly  M-01 one-shot runner
echo   CWD: %CD%
echo ============================================================
echo.

REM ---------- 0. Environment check ----------
echo [0/5] Environment check
echo --------------------------------------------------------

where composer >nul 2>&1
if errorlevel 1 (
    echo   [ERROR] composer not found
    echo   Fix: PowerShell:  iwr -useb getcomposer.org/installer ^| iex
    goto END_FAIL
)
echo   composer: OK

where node >nul 2>&1
if errorlevel 1 (
    echo   [WARN]  node not found, will skip wp-env step
    set "HAS_NODE=0"
) else (
    echo   node: OK
    set "HAS_NODE=1"
)

where docker >nul 2>&1
if errorlevel 1 (
    echo   [WARN]  docker not found, wp-env cannot start
    set "HAS_DOCKER=0"
) else (
    echo   docker: OK
    set "HAS_DOCKER=1"
)

where git >nul 2>&1
if errorlevel 1 (
    echo   [ERROR] git not found
    goto END_FAIL
)
echo   git: OK

echo.

REM ---------- 1. composer install ----------
echo [1/5] composer install (dev deps)
echo --------------------------------------------------------
call composer install --no-progress
if errorlevel 1 goto END_FAIL

echo.

REM ---------- 2. Quality gates ----------
echo [2/5] Quality gates: lint / stan / test:unit / compat
echo --------------------------------------------------------

echo   --- lint ---
call composer lint
if errorlevel 1 goto END_FAIL

echo   --- stan ---
call composer stan
if errorlevel 1 goto END_FAIL

echo   --- test:unit ---
call composer test:unit
if errorlevel 1 goto END_FAIL

echo   --- compat ---
call composer compat
if errorlevel 1 goto END_FAIL

echo.

REM ---------- 3. wp-env ----------
echo [3/5] wp-env start
echo --------------------------------------------------------
if "!HAS_NODE!"=="0" goto WPENV_SKIP_NODE
if "!HAS_DOCKER!"=="0" goto WPENV_SKIP_DOCKER
echo   Starting WordPress container (first run ~2min)...
call npx wp-env start
if errorlevel 1 (
    echo   [WARN] wp-env start failed, continuing
)
goto WPENV_DONE

:WPENV_SKIP_NODE
echo   [SKIP] no node
goto WPENV_DONE

:WPENV_SKIP_DOCKER
echo   [SKIP] no docker

:WPENV_DONE

echo.

REM ---------- 4. git commit ----------
echo [4/5] git first commit
echo --------------------------------------------------------
if not exist .git (
    git init
    git branch -M main
)
git add .
git commit -m "chore: M-01 bootstrap skeleton + wp-env + CI"
if errorlevel 1 goto END_FAIL

echo.

REM ---------- 5. push ----------
echo [5/5] push to GitHub
echo --------------------------------------------------------
git remote -v | findstr origin >nul
if errorlevel 1 (
    echo   [INFO] no remote configured
    echo     1. Create empty repo openpoly on GitHub (no README/.gitignore)
    echo     2. git remote add origin ^<your-repo-URL^>
    echo     3. git push -u origin main
) else (
    git push -u origin main
    if errorlevel 1 (
        echo   [WARN] push failed, check repo / permissions
    )
)

echo.
echo ============================================================
echo   DONE. M-01 finished.
echo ============================================================
echo.
goto END_OK

:END_FAIL
echo.
echo ============================================================
echo   FAILED.  Send the first error line to the agent.
echo ============================================================
echo.

:END_OK
echo Press any key to close this window...
pause >nul
exit /b 0
