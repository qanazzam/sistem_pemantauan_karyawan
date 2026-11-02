@echo off
echo ========================================================
echo   SIMPEG PU - Menjalankan Database MySQL (Docker)
echo ========================================================
echo.
docker compose up -d
echo.
if %ERRORLEVEL% EQU 0 (
    echo [SUKSES] Database MySQL 'sistem_pemantauan_karyawan' berhasil berjalan di port 3306!
    echo Anda sekarang dapat membuka aplikasi desktop SIMPEG-PU.
) else (
    echo [GAGAL] Pastikan Docker Desktop sudah terinstal dan sedang berjalan.
)
echo.
pause
