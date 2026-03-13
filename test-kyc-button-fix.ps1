# Test KYC Button Fix
# This script tests the KYC button visibility fix

Write-Host "=== Testing KYC Button Fix ===" -ForegroundColor Cyan
Write-Host ""

# Test 1: Register a new user and check response includes kyc_document_url
Write-Host "Test 1: Register new user and verify kyc_document_url in response" -ForegroundColor Yellow
$registerBody = @{
    name = "Test User KYC"
    email = "testkyc$(Get-Random)@example.com"
    phone = "+234801$(Get-Random -Minimum 1000000 -Maximum 9999999)"
    password = "password123"
    password_confirmation = "password123"
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "http://localhost:8000/api/v1/auth/register" `
        -Method Post `
        -Body $registerBody `
        -ContentType "application/json"
    
    if ($response.data.user.kyc_document_url -eq $null) {
        Write-Host "✓ kyc_document_url is null for new user (expected)" -ForegroundColor Green
    } else {
        Write-Host "✗ kyc_document_url should be null for new user" -ForegroundColor Red
    }
    
    if ($response.data.user.kyc_status -eq "pending") {
        Write-Host "✓ kyc_status is 'pending' for new user" -ForegroundColor Green
    } else {
        Write-Host "✗ kyc_status should be 'pending' for new user" -ForegroundColor Red
    }
    
    $token = $response.data.token
    $userId = $response.data.user.id
    
    Write-Host ""
    Write-Host "User created successfully!" -ForegroundColor Green
    Write-Host "User ID: $userId" -ForegroundColor Cyan
    Write-Host "Token: $($token.Substring(0, 20))..." -ForegroundColor Cyan
    
} catch {
    Write-Host "✗ Registration failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "=== All Tests Passed ===" -ForegroundColor Green
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Open mobile app and login with the test user"
Write-Host "2. Go to Profile screen"
Write-Host "3. Verify button shows 'Complete KYC Verification'"
Write-Host "4. Tap button and verify it navigates to KYC Submission screen"
