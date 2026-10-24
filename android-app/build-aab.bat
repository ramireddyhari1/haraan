@echo off
setlocal
cd /d "%~dp0"
if exist "C:\Program Files\Zulu\zulu-17\bin\java.exe" (
    set "JAVA_HOME=C:\Program Files\Zulu\zulu-17"
) else if not defined JAVA_HOME (
    if exist "C:\Program Files\Android\Android Studio\jbr\bin\java.exe" set "JAVA_HOME=C:\Program Files\Android\Android Studio\jbr"
)
echo Building release AAB... > build-aab.log
call gradlew.bat :app:bundleRelease --console=plain >> build-aab.log 2>&1
if errorlevel 1 (echo BUILD FAILED >> build-aab.log & exit /b 1)
for /f "tokens=*" %%v in ('findstr /r "versionName" app\build.gradle.kts') do set VL=%%v
copy /y "app\build\outputs\bundle\release\app-release.aab" "..\haraan-56-1.0.55-release.aab" >> build-aab.log
echo DONE >> build-aab.log
