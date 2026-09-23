@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo 开启 HTTPS 公网隧道，让手机相机可以正常使用...
echo 请先启动 XAMPP Apache，本窗口保持打开。
echo.

set TOOLS=%~dp0tools
if not exist "%TOOLS%" mkdir "%TOOLS%"
set CF=%TOOLS%\cloudflared.exe

if not exist "%CF%" (
  echo 正在下载 cloudflared...
  powershell -NoProfile -Command "Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%CF%'"
)

echo 启动后把出现的 https://xxxx.trycloudflare.com/ai-ar-object-recognition/settings.php
echo 用手机打开，或把该地址生成二维码再扫。
echo.
"%CF%" tunnel --url http://127.0.0.1:80
pause
