#!/usr/bin/env pwsh
# Test KYC Status Fix

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "KYC Status Loading Fix Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if model was updated
Write-Host "Checking KYC model..." -ForegroundColor Yellow
$kycModel = Get-Content "mobile/lib/models/kyc.dart" -Raw

$hasJsonKey = $kycModel -match "@JsonKey\(name: 'kyc_status'\)"
$hasRejectionKey = $kycModel -match "@JsonKey\(name: 'kyc_rejection_reason'\)"

if ($hasJsonKey) {
    Write-Host "Pass - KYC model has correct field mapping" -ForegroundColor Green
} else {
    Write-Host "Fail - KYC model missing JsonKey annotation" -ForegroundColor Red
}

if ($hasRejectionKey) {
    Write-Host "Pass - Rejection reason field mapped correctly" -ForegroundColor Green
} else {
    Write-Host "Fail - Rejection reason field not mapped" -ForegroundColor Red
}

# Check if .g.dart file was generated
Write-Host "`nChecking generated files..." -ForegroundColor Yellow
if (Test-Path "mobile/lib/models/kyc.g.dart") {
    Write-Host "Pass - kyc.g.dart file exists" -ForegroundColor Green
} else {
    Write-Host "Fail - kyc.g.dart file not found" -ForegroundColor Red
    Write-Host "Run: cd mobile && flutter pub run build_runner build" -ForegroundColor Yellow
}

# Check repository error handling
Write-Host "`nChecking repository error handling..." -ForegroundColor Yellow
$kycRepo = Get-Content "mobile/lib/repositories/kyc_repository.dart" -Raw

$hasDebugPrint = $kycRepo -match "debugPrint"
$hasNullCheck = $kycRepo -match "response.data == null"

if ($hasDebugPrint) {
    Write-Host "Pass - Debug logging added" -ForegroundColor Green
} else {
    Write-Host "Fail - Debug logging missing" -ForegroundColor Red
}

if ($hasNullCheck) {
    Write-Host "Pass - Null checks added" -ForegroundColor Green
} else {
    Write-Host "Fail - Null checks missing" -ForegroundColor Red
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Issue: KYC status failed to load" -ForegroundColor White
Write-Host "Cause: JSON field name mismatch" -ForegroundColor White
Write-Host "Fix: Added JsonKey annotations" -ForegroundColor White
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Rebuild mobile app: flutter run" -ForegroundColor White
Write-Host "2. Login to the app" -ForegroundColor White
Write-Host "3. Navigate to Profile > KYC Status" -ForegroundColor White
Write-Host "4. Verify KYC status loads successfully" -ForegroundColor White
Write-Host ""
