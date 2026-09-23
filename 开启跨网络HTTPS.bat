@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo ========================================
echo  跨网络使用（不用同一 WiFi）
echo  1. 先开 XAMPP Apache
echo  2. 本窗口保持打开（关掉就断）
echo  3. 出现 https 地址后，打开 Settings 刷新二维码
echo ========================================
echo.

set TOOLS=%~dp0tools
if not exist "%TOOLS%" mkdir "%TOOLS%"
set CF=%TOOLS%\cloudflared.exe
set OUT=%TOOLS%\tunnel-out.log
set ERR=%TOOLS%\tunnel-err.log
set URLFILE=%~dp0config\public-url.txt

if not exist "%CF%" (
  echo 正在下载 cloudflared...
  powershell -NoProfile -Command "Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%CF%'"
)

if exist "%OUT%" del /f /q "%OUT%"
if exist "%ERR%" del /f /q "%ERR%"

start /b "" "%CF%" tunnel --url http://127.0.0.1:80 > "%OUT%" 2> "%ERR%"

echo 等待公网地址...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$deadline=(Get-Date).AddMinutes(2); $found=$false; while((Get-Date) -lt $deadline){ Start-Sleep -Seconds 1; $t=''; foreach($f in @('%OUT%','%ERR%')){ if(Test-Path $f){ $t += Get-Content $f -Raw -EA SilentlyContinue } }; if($t -match 'https://[a-z0-9-]+\.trycloudflare\.com'){ $u=$Matches[0].TrimEnd('/'); $pub=$u+'/ai-ar-object-recognition/'; [IO.File]::WriteAllText('%URLFILE%',$pub); Write-Host ''; Write-Host '公网地址已保存:' -ForegroundColor Green; Write-Host '  '$pub -ForegroundColor Cyan; Write-Host '请打开 http://localhost/ai-ar-object-recognition/settings.php 刷新看二维码'; Write-Host '手机用任何网络扫码即可。本窗口不要关。' -ForegroundColor Yellow; $found=$true; break } }; if(-not $found){ Write-Host '超时未拿到地址，请检查网络后重试' -ForegroundColor Red }"

echo.
echo 隧道运行中... 按 Ctrl+C 结束
powershell -NoProfile -Command "Get-Content '%ERR%' -Wait -ErrorAction SilentlyContinue"
pause
