# Android App Auto-Run

This project can rebuild and relaunch the app automatically when code changes.

## Watch mode

Run the PowerShell watcher:

```powershell
Set-Location "c:\Users\harih\Downloads\book and vibe php version\android-app"
powershell.exe -ExecutionPolicy Bypass -File .\scripts\watch-and-run.ps1
```

## One-time refresh

If you only want to rebuild and relaunch once:

```powershell
Set-Location "c:\Users\harih\Downloads\book and vibe php version\android-app"
powershell.exe -ExecutionPolicy Bypass -File .\scripts\watch-and-run.ps1 -Once
```

## VS Code task

Use the task **Android: Watch and run** from the terminal/task picker.
