@echo off
echo ========================================================
echo   SIMPEG PU - Menghentikan Database MySQL (Docker)
echo ========================================================
echo.
docker compose down
echo.
echo Database telah dihentikan (data tetap tersimpan aman di volume Docker).
pause
