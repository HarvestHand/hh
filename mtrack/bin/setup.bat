@ECHO OFF
REM This is the windows equivalent of the setup bash script in this directory

REM Please change this variable to point to your php.exe location, please do not place quotation marks around the path
SET PHP_BIN=C:\Program Files\PHP\php.exe
SET PROJ_NAM=
SET REPO_NAME=
SET REPO_PATH=
SET REPO_TYPE=svn

REM Do not edit beyond this point

IF EXIST %CD%/init.php GOTO CDONEUP
IF EXIST %CD%/init.php GOTO CDONEUP
IF EXIST %CD%/bin/init.php GOTO RUN

ECHO "Error: cannot find bin\init.php, you can double click on setup.bat to run me properly"

:CDONEUP
CD ../

:RUN

CALL "%PHP_BIN%" "%CD%\bin\init.php" --repo %REPO_NAME% %REPO_TYPE% %REPO_PATH%
   --link %PROJ_NAME% %REPO_NAME% /%*
PAUSE