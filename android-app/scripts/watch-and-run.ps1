param(
  [string]$WorkspaceRoot = (Split-Path -Parent $PSScriptRoot),
  [string]$JavaHome = 'C:\Program Files\Android\Android Studio\jbr',
  [string]$PackageName = 'com.example.thanna',
  [string]$ActivityName = '.MainActivity',
  [switch]$Once
)

$ErrorActionPreference = 'Stop'

function Get-SdkDir {
  param([string]$Root)

  $localProperties = Join-Path $Root 'local.properties'
  if (Test-Path $localProperties) {
    $content = Get-Content $localProperties -Raw
    if ($content -match 'sdk\.dir=(.+)') {
      $sdkDir = $Matches[1].Trim()
      $sdkDir = $sdkDir -replace '\\', '\'
      $sdkDir = $sdkDir -replace '\:', ':'
      if (Test-Path $sdkDir) {
        return $sdkDir
      }
    }
  }

  $fallbackSdkDir = Join-Path $env:LOCALAPPDATA 'Android\Sdk'
  if (Test-Path $fallbackSdkDir) {
    return $fallbackSdkDir
  }

  throw "Unable to find the Android SDK. Check local.properties or set it manually in the script."
}

function Invoke-InstallAndLaunch {
  param(
    [string]$Root,
    [string]$JavaHomePath,
    [string]$SdkDir,
    [string]$Pkg,
    [string]$Act
  )

  Set-Location $Root
  $env:JAVA_HOME = $JavaHomePath
  $env:Path = "$JavaHomePath\bin;" + $env:Path

  $gradlew = Join-Path $Root 'gradlew.bat'
  $adb = Join-Path $SdkDir 'platform-tools\adb.exe'
  $emulator = Join-Path $SdkDir 'emulator\emulator.exe'

  Ensure-DeviceReady -AdbPath $adb -EmulatorPath $emulator -AvdName 'medium_phone'

  Write-Host "[$(Get-Date -Format HH:mm:ss)] Building and installing..."
  & $gradlew installDebug
  if ($LASTEXITCODE -ne 0) {
    throw 'Gradle installDebug failed.'
  }

  Write-Host "[$(Get-Date -Format HH:mm:ss)] Restarting app on emulator..."
  & $adb shell am force-stop $Pkg | Out-Null
  & $adb shell am start -n "$Pkg/$Act" | Out-Null
}

function Get-FirstDeviceState {
  param([string]$AdbPath)

  $lines = & $AdbPath devices 2>$null
  foreach ($line in $lines) {
    if ($line -match '^emulator-[0-9]+\s+(\S+)$') {
      return $Matches[1]
    }
  }

  return $null
}

function Wait-ForBootCompleted {
  param([string]$AdbPath)

  $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
  while ($stopwatch.Elapsed.TotalMinutes -lt 5) {
    $boot = & $AdbPath shell getprop sys.boot_completed 2>$null
    if (($boot | Select-Object -First 1).Trim() -eq '1') {
      return
    }
    Start-Sleep -Seconds 2
  }

  throw 'Timed out waiting for the emulator to finish booting.'
}

function Ensure-DeviceReady {
  param(
    [string]$AdbPath,
    [string]$EmulatorPath,
    [string]$AvdName
  )

  $state = Get-FirstDeviceState -AdbPath $AdbPath
  if ($state -eq 'device') {
    return
  }

  if ($state -eq 'offline') {
    Write-Host "Waiting for the running emulator to come online..."
    & $AdbPath wait-for-device | Out-Null
    Wait-ForBootCompleted -AdbPath $AdbPath
    return
  }

  Write-Host "No emulator detected. Starting AVD '$AvdName'..."
  Start-Process -FilePath $EmulatorPath -ArgumentList @('-avd', $AvdName) | Out-Null
  & $AdbPath wait-for-device | Out-Null
  Wait-ForBootCompleted -AdbPath $AdbPath
}

function Test-IgnoredPath {
  param([string]$FullPath)

  return ($FullPath -match '\\(build|\.gradle|\.idea|\.kotlin)\\')
}

$workspaceRoot = (Resolve-Path $WorkspaceRoot).Path
$sdkDir = Get-SdkDir -Root $workspaceRoot
$watchExtensions = @('.kt', '.kts', '.java', '.xml', '.properties', '.toml')

if ($Once) {
  Invoke-InstallAndLaunch -Root $workspaceRoot -JavaHomePath $JavaHome -SdkDir $sdkDir -Pkg $PackageName -Act $ActivityName
  exit 0
}

$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $workspaceRoot
$watcher.IncludeSubdirectories = $true
$watcher.NotifyFilter = [System.IO.NotifyFilters]'FileName, LastWrite, Size, DirectoryName'
$watcher.EnableRaisingEvents = $true

$script:pending = $false
$script:lastChange = Get-Date
$script:isRunning = $false

$sourceIdentifiers = @(
  'android-watch-changed',
  'android-watch-created',
  'android-watch-deleted',
  'android-watch-renamed'
)

foreach ($sourceIdentifier in $sourceIdentifiers) {
  Get-EventSubscriber | Where-Object { $_.SourceIdentifier -eq $sourceIdentifier } | ForEach-Object {
    Unregister-Event -SubscriptionId $_.SubscriptionId -ErrorAction SilentlyContinue
  }
  Get-Event | Where-Object { $_.SourceIdentifier -eq $sourceIdentifier } | ForEach-Object {
    Remove-Event -EventIdentifier $_.EventIdentifier -ErrorAction SilentlyContinue
  }
}

Register-ObjectEvent -InputObject $watcher -EventName Changed -SourceIdentifier 'android-watch-changed' | Out-Null
Register-ObjectEvent -InputObject $watcher -EventName Created -SourceIdentifier 'android-watch-created' | Out-Null
Register-ObjectEvent -InputObject $watcher -EventName Deleted -SourceIdentifier 'android-watch-deleted' | Out-Null
Register-ObjectEvent -InputObject $watcher -EventName Renamed -SourceIdentifier 'android-watch-renamed' | Out-Null

Write-Host "Watching $workspaceRoot for changes..."
Invoke-InstallAndLaunch -Root $workspaceRoot -JavaHomePath $JavaHome -SdkDir $sdkDir -Pkg $PackageName -Act $ActivityName

while ($true) {
  $event = Wait-Event -Timeout 1
  if ($null -ne $event) {
    $fullPath = $null
    if ($event.SourceEventArgs.PSObject.Properties.Name -contains 'FullPath') {
      $fullPath = $event.SourceEventArgs.FullPath
    }

    if ($null -ne $fullPath) {
      $ext = [System.IO.Path]::GetExtension($fullPath).ToLowerInvariant()
      if ($watchExtensions -contains $ext -and -not (Test-IgnoredPath -FullPath $fullPath)) {
        $script:pending = $true
        $script:lastChange = Get-Date
      }
    }

    Remove-Event -EventIdentifier $event.EventIdentifier
  }

  if ($script:pending -and -not $script:isRunning) {
    if (((Get-Date) - $script:lastChange).TotalMilliseconds -ge 1000) {
      $script:pending = $false
      $script:isRunning = $true
      try {
        Invoke-InstallAndLaunch -Root $workspaceRoot -JavaHomePath $JavaHome -SdkDir $sdkDir -Pkg $PackageName -Act $ActivityName
      }
      catch {
        Write-Host $_.Exception.Message -ForegroundColor Red
      }
      finally {
        $script:isRunning = $false
      }
    }
  }
}
