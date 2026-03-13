# Android Build Script for Ajo Platform Mobile App (PowerShell)
# This script helps you build and install the Android app

$ErrorActionPreference = "Stop"

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Ajo Platform - Android Build Script" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# Check if Flutter is installed
try {
    $flutterVersion = flutter --version 2>&1
    Write-Host "✓ Flutter found" -ForegroundColor Green
    Write-Host $flutterVersion
    Write-Host ""
} catch {
    Write-Host "Error: Flutter is not installed or not in PATH" -ForegroundColor Red
    Write-Host "Please install Flutter from https://flutter.dev/docs/get-started/install"
    exit 1
}

# Check Flutter doctor
Write-Host "Checking Flutter environment..."
flutter doctor
Write-Host ""

# Navigate to mobile directory
Set-Location $PSScriptRoot

# Clean previous builds
Write-Host "Cleaning previous builds..."
flutter clean
Write-Host "✓ Clean complete" -ForegroundColor Green
Write-Host ""

# Get dependencies
Write-Host "Getting dependencies..."
flutter pub get
Write-Host "✓ Dependencies installed" -ForegroundColor Green
Write-Host ""

# Ask user what to build
Write-Host "What would you like to build?"
Write-Host "1) Debug APK (recommended for testing)"
Write-Host "2) Release APK"
Write-Host "3) Debug App Bundle (AAB)"
Write-Host "4) Release App Bundle (AAB)"
Write-Host "5) Install and run on connected device"
$choice = Read-Host "Enter your choice (1-5)"

switch ($choice) {
    "1" {
        Write-Host ""
        Write-Host "Building debug APK..."
        flutter build apk --debug
        Write-Host ""
        Write-Host "✓ Build complete!" -ForegroundColor Green
        Write-Host "APK location: build\app\outputs\flutter-apk\app-debug.apk"
    }
    "2" {
        Write-Host ""
        Write-Host "Building release APK..."
        flutter build apk --release
        Write-Host ""
        Write-Host "✓ Build complete!" -ForegroundColor Green
        Write-Host "APK location: build\app\outputs\flutter-apk\app-release.apk"
    }
    "3" {
        Write-Host ""
        Write-Host "Building debug App Bundle..."
        flutter build appbundle --debug
        Write-Host ""
        Write-Host "✓ Build complete!" -ForegroundColor Green
        Write-Host "AAB location: build\app\outputs\bundle\debug\app-debug.aab"
    }
    "4" {
        Write-Host ""
        Write-Host "Building release App Bundle..."
        flutter build appbundle --release
        Write-Host ""
        Write-Host "✓ Build complete!" -ForegroundColor Green
        Write-Host "AAB location: build\app\outputs\bundle\release\app-release.aab"
    }
    "5" {
        Write-Host ""
        Write-Host "Checking for connected devices..."
        flutter devices
        Write-Host ""
        $confirm = Read-Host "Do you want to continue with installation? (y/n)"
        if ($confirm -eq "y" -or $confirm -eq "Y") {
            Write-Host "Installing and running app..."
            flutter run
        } else {
            Write-Host "Installation cancelled"
        }
    }
    default {
        Write-Host "Invalid choice" -ForegroundColor Red
        exit 1
    }
}

Write-Host ""
Write-Host "======================================" -ForegroundColor Cyan
Write-Host "Build process complete!" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:"
Write-Host "1. Transfer the APK to your Android device"
Write-Host "2. Enable 'Install from Unknown Sources' on your device"
Write-Host "3. Install the APK"
Write-Host "4. Make sure backend services are running"
Write-Host "5. Configure API URL in app settings"
Write-Host ""
Write-Host "For more information, see ANDROID_BUILD_GUIDE.md"
