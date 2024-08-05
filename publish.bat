@echo off
setlocal

set "source_path=%CD%"

@REM get current folder name, set as plugin_name
for %%A in ("%source_path%") do set "plugin_name=%%~nxA"

set "zip_file=%plugin_name%.zip"

set "exclude=node_modules;.git;zip.ps1;publish.bat;.gitignore;jsconfig.json;package.json;package-lock.json;wp-manifest.cjs;vite.config.js;%zip_file%"

echo Creating zip file...
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0zip.ps1" -source_path "%source_path%" -plugin_name "%plugin_name%" -exclude "%exclude%"

set "install_name=devlibrary2021"
@REM set "remote_path=/sites/%install_name%/wp-content/plugins/fivebyfive/modules/"
set "remote_path=/sites/%install_name%/ff-modules/"
set "ssh_host=%install_name%@%install_name%.ssh.wpengine.net"

echo Uploading to remote(%install_name%)...

scp %zip_file% %ssh_host%:%remote_path%

endlocal