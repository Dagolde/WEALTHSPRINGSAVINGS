#!/usr/bin/env pwsh
# Regenerate JSON serialization code for models

Write-Host "Regenerating JSON serialization code..." -ForegroundColor Yellow

Set-Location mobile

# Run build_runner to generate .g.dart files
flutter pub run build_runner build --delete-conflicting-outputs

if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ JSON serialization code generated successfully" -ForegroundColor Green
} else {
    Write-Host "✗ Failed to generate JSON serialization code" -ForegroundColor Red
    exit 1
}

Set-Location ..

Write-Host ""
Write-Host "Done! You can now run the app." -ForegroundColor Green
