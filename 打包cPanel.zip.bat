@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo 正在打包 cPanel 上传用 zip（排除本机隧道工具）...

set OUT=%~dp0..\ai-ar-cpanel.zip
if exist "%OUT%" del /f /q "%OUT%"

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$root='%~dp0'; $out='%~dp0..\ai-ar-cpanel.zip'; if(Test-Path $out){Remove-Item $out -Force}; $tmp=Join-Path $env:TEMP ('ai-ar-cpanel-'+[guid]::NewGuid().ToString()); New-Item -ItemType Directory -Path $tmp | Out-Null; $dest=Join-Path $tmp 'ai-ar'; New-Item -ItemType Directory -Path $dest | Out-Null; Get-ChildItem -Force $root | Where-Object { $_.Name -notin @('tools','开启跨网络HTTPS.bat','开启HTTPS给手机用.bat','打包cPanel.zip.bat','.git') } | ForEach-Object { Copy-Item $_.FullName -Destination (Join-Path $dest $_.Name) -Recurse -Force }; $pub=Join-Path $dest 'config\public-url.txt'; if(Test-Path $pub){ Set-Content -Path $pub -Value '' -Encoding UTF8 }; Compress-Archive -Path $dest -DestinationPath $out -Force; Remove-Item $tmp -Recurse -Force; Write-Host ('已生成: '+$out) -ForegroundColor Green"

echo.
echo 上传到 cPanel: public_html 后解压，详见 CPANEL安装说明.md
pause
