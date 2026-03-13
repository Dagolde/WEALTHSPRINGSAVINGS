#!/usr/bin/env pwsh
# Test KYC Status Fix

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "KYC Status Loading Fix Test" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Check if model was updated
Write-Host "Step 1: Checking KYC model..." -ForegroundColor Yellow
$kycModel = Get-Content "mobile/lib/models/kyc.dart" -Raw

if ($kycModel -match "@JsonKey\(name: 'kyc_status'\)") {
    Write-Host "✓ KYC model has correct field mapping" -ForegroundColor Green
} else {
    Write-Host "✗ KYC model missing @JsonKey annotation" -ForegroundColor Red
}

if ($kycModel -match "@JsonKey\(name: 'kyc_rejection_reason'\)") {
    Write-Host "✓ Rejection reason field mapped correctly" -ForegroundColor Green
} else {
    Write-Host "✗ Rejection reason field not mapped" -ForegroundColor Red
}

# Step 2: Check if .g.dart file was generated
Write-Host "`nStep 2: Checking generated files..." -ForegroundColor Yellow
if (Test-Path "mobile/lib/models/kyc.g.dart") {
    Write-Host "✓ kyc.g.dart file exists" -ForegroundColor Green
    
    $generatedFile = Get-Content "mobile/lib/models/kyc.g.dart" -Raw
    if ($generatedFile -match "kyc_status") {
        Write-Host "✓ Generated file includes kyc_status mapping" -ForegroundColor Green
    } else {
        Write-Host "⚠ Generated file may need regeneration" -ForegroundColor Yellow
    }
} else {
    Write-Host "✗ kyc.g.dart file not found" -ForegroundColor Red
    Write-Host "Run: cd mobile && flutter pub run build_runner build --delete-conflicting-outputs" -ForegroundColor Yellow
}

# Step 3: Check repository error handling
Write-Host "`nStep 3: Checking repository error handling..." -ForegroundColor Yellow
$kycRepo = Get-Content "mobile/lib/repositories/kyc_repository.dart" -Raw

if ($kycRepo -match "debugPrint") {
    Write-Host "✓ Debug logging added" -ForegroundColor Green
} else {
    Write-Host "✗ Debug logging missing" -ForegroundColor Red
}

if ($kycRepo -match "response.data == null") {
    Write-Host "✓ Null checks added" -ForegroundColor Green
} else {
    Write-Host "✗ Null checks missing" -ForegroundColor Red
}

# Step 4: Check backend endpoint
Write-Host "`nStep 4: Testing backend endpoint..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8002/api/v1/user/kyc/status" -Method GET -Headers @{"Accept"="application/json"} -UseBasicParsing -ErrorAction Stop
    Write-Host "⚠ Endpoint accessible but requires authentication" -ForegroundColor Yellow
} catch {
    if ($_.Exception.Response.StatusCode -eq 401) {
        Write-Host "✓ Endpoint exists (401 Unauthorized - expected)" -ForegroundColor Green
    } else {
        Write-Host "✗ Endpoint not accessible: $($_.Exception.Message)" -ForegroundColor Red
    }
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Fix Summary" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Issue: KYC status failed to load" -ForegroundColor White
Write-Host "Cause: JSON field name mismatch (kyc_status vs status)" -ForegroundColor White
Write-Host "Fix: Added @JsonKey annotations to map field names" -ForegroundColor White
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Rebuild mobile app: flutter run" -ForegroundColor White
Write-Host "2. Login to the app" -ForegroundColor White
Write-Host "3. Navigate to Profile > KYC Status" -ForegroundColor White
Write-Host "4. Verify KYC status loads successfully" -ForegroundColor White
Write-Host ""
Write-Host "Expected Results:" -ForegroundColor Yellow
Write-Host "- No KYC: Shows 'Complete KYC Verification' button" -ForegroundColor White
Write-Host "- Pending: Shows 'Under Review' timeline" -ForegroundColor White
Write-Host "- Verified: Shows green checkmark" -ForegroundColor White
Write-Host "- Rejected: Shows rejection reason and resubmit button" -ForegroundColor White
Write-Host ""
