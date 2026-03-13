#!/usr/bin/env pwsh
# Fix white screen issue in Flutter app

Write-Host "Diagnosing white screen issue..." -ForegroundColor Cyan
Write-Host ""

# Step 1: Check if .env file exists
Write-Host "1. Checking .env file..." -ForegroundColor Yellow
if (Test-Path ".env") {
    Write-Host "   .env file exists" -ForegroundColor Green
} else {
    Write-Host "   ERROR: .env file missing!" -ForegroundColor Red
    Write-Host "   Creating .env file..." -ForegroundColor Yellow
    Copy-Item ".env.example" ".env" -ErrorAction SilentlyContinue
}

# Step 2: Test with simple main
Write-Host ""
Write-Host "2. Testing with simplified main.dart..." -ForegroundColor Yellow
Write-Host "   Backing up current main.dart..." -ForegroundColor Gray
Copy-Item "lib/main.dart" "lib/main.backup.dart" -Force

Write-Host "   Switching to simple test version..." -ForegroundColor Gray
Copy-Item "lib/main_simple.dart" "lib/main.dart" -Force

Write-Host ""
Write-Host "Simple test app is now active!" -ForegroundColor Green
Write-Host ""
Write-Host "Run: flutter run" -ForegroundColor Cyan
Write-Host ""
Write-Host "If the app shows 'App is Working!', the issue is in the original main.dart" -ForegroundColor Yellow
Write-Host ""
Write-Host "To restore original main.dart:" -ForegroundColor Cyan
Write-Host "  Copy-Item lib/main.backup.dart lib/main.dart -Force" -ForegroundColor Gray
