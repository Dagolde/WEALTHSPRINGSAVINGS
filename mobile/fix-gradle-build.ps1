# Fix Gradle Build Issues
# This script cleans Gradle cache and rebuilds the project

$ErrorActionPreference = "Stop"

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Fixing Gradle Build Issues" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Clean Flutter build
Write-Host "Step 1: Cleaning Flutter build..." -ForegroundColor Blue
flutter clean
Write-Host "✓ Flutter build cleaned" -ForegroundColor Green
Write-Host ""

# Step 2: Clean Gradle cache (the locked file issue)
Write-Host "Step 2: Cleaning Gradle cache..." -ForegroundColor Blue
$gradleCache = "$env:USERPROFILE\.gradle"

# Stop any running Gradle daemons first
Write-Host "Stopping Gradle daemons..." -ForegroundColor Yellow
try {
    Set-Location android
    & .\gradlew --stop 2>$null
    Set-Location ..
} catch {
    # Gradle may not be initialized yet
}

Start-Sleep -Seconds 2

if (Test-Path $gradleCache) {
    Write-Host "Removing Gradle cache at: $gradleCache" -ForegroundColor Yellow
    
    # Remove the wrapper dists (where the lock is)
    $wrapperDists = "$gradleCache\wrapper\dists"
    if (Test-Path $wrapperDists) {
        try {
            Remove-Item -Path $wrapperDists -Recurse -Force -ErrorAction Stop
            Write-Host "✓ Gradle wrapper cache cleared" -ForegroundColor Green
        } catch {
            Write-Host "⚠ Could not remove wrapper cache (files may be locked)" -ForegroundColor Yellow
            Write-Host "  Try closing Android Studio and running this script again" -ForegroundColor Yellow
        }
    }
    
    # Remove caches
    $cachesDir = "$gradleCache\caches"
    if (Test-Path $cachesDir) {
        try {
            Remove-Item -Path $cachesDir -Recurse -Force -ErrorAction Stop
            Write-Host "✓ Gradle caches cleared" -ForegroundColor Green
        } catch {
            Write-Host "⚠ Could not remove caches (files may be locked)" -ForegroundColor Yellow
        }
    }
} else {
    Write-Host "✓ No Gradle cache found" -ForegroundColor Green
}
Write-Host ""

# Step 3: Clean Android build directory
Write-Host "Step 3: Cleaning Android build directory..." -ForegroundColor Blue
$androidBuild = "android\build"
if (Test-Path $androidBuild) {
    Remove-Item -Path $androidBuild -Recurse -Force
    Write-Host "✓ Android build directory cleaned" -ForegroundColor Green
} else {
    Write-Host "✓ No Android build directory found" -ForegroundColor Green
}
Write-Host ""

# Step 4: Clean app build directory
Write-Host "Step 4: Cleaning app build directory..." -ForegroundColor Blue
$appBuild = "android\app\build"
if (Test-Path $appBuild) {
    Remove-Item -Path $appBuild -Recurse -Force
    Write-Host "✓ App build directory cleaned" -ForegroundColor Green
} else {
    Write-Host "✓ No app build directory found" -ForegroundColor Green
}
Write-Host ""

# Step 5: Get Flutter dependencies
Write-Host "Step 5: Getting Flutter dependencies..." -ForegroundColor Blue
flutter pub get
Write-Host "✓ Dependencies fetched" -ForegroundColor Green
Write-Host ""

# Step 6: Verify Gradle versions
Write-Host "Step 6: Verifying Gradle configuration..." -ForegroundColor Blue
$gradleWrapper = Get-Content "android\gradle\wrapper\gradle-wrapper.properties" -Raw
if ($gradleWrapper -match "gradle-8\.7") {
    Write-Host "✓ Gradle version: 8.7" -ForegroundColor Green
} else {
    Write-Host "⚠ Gradle version may need update" -ForegroundColor Yellow
}

$buildGradle = Get-Content "android\build.gradle" -Raw
if ($buildGradle -match "gradle:8\.3\.0") {
    Write-Host "✓ Android Gradle Plugin: 8.3.0" -ForegroundColor Green
} else {
    Write-Host "⚠ Android Gradle Plugin may need update" -ForegroundColor Yellow
}
Write-Host ""

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Cleanup Complete!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Try building again:" -ForegroundColor White
Write-Host "   flutter run" -ForegroundColor Cyan
Write-Host ""
Write-Host "2. If you still get the timeout error:" -ForegroundColor White
Write-Host "   - Close Android Studio if it's open" -ForegroundColor White
Write-Host "   - Run this script again" -ForegroundColor White
Write-Host "   - Restart your computer if needed" -ForegroundColor White
Write-Host ""
Write-Host "3. Alternative: Build without validation:" -ForegroundColor White
Write-Host "   flutter run --android-skip-build-dependency-validation" -ForegroundColor Cyan
Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""
