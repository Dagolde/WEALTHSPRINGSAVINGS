# Test Mobile App Control Endpoints

$API_BASE = "http://localhost:8002/api/v1"
$ADMIN_EMAIL = "admin@ajo.test"
$ADMIN_PASSWORD = "password"

Write-Host "=== Testing Mobile App Control Endpoints ===" -ForegroundColor Cyan
Write-Host ""

# 1. Login as admin
Write-Host "1. Logging in as admin..." -ForegroundColor Yellow
$loginResponse = Invoke-RestMethod -Uri "$API_BASE/auth/admin/login" -Method Post -Body (@{
    email = $ADMIN_EMAIL
    password = $ADMIN_PASSWORD
} | ConvertTo-Json) -ContentType "application/json"

if ($loginResponse.success) {
    $token = $loginResponse.data.token
    Write-Host "   ✓ Login successful" -ForegroundColor Green
} else {
    Write-Host "   ✗ Login failed" -ForegroundColor Red
    exit 1
}

$headers = @{
    "Authorization" = "Bearer $token"
    "Accept" = "application/json"
    "Content-Type" = "application/json"
}

Write-Host ""

# 2. Test Get Mobile Settings
Write-Host "2. Testing Get Mobile Settings..." -ForegroundColor Yellow
try {
    $settingsResponse = Invoke-RestMethod -Uri "$API_BASE/admin/mobile/settings" -Method Get -Headers $headers
    Write-Host "   ✓ Mobile settings retrieved" -ForegroundColor Green
    Write-Host "   App Version: $($settingsResponse.data.app_version)" -ForegroundColor Gray
} catch {
    Write-Host "   ✗ Failed" -ForegroundColor Red
}

Write-Host ""

# 3. Test Get App Usage
Write-Host "3. Testing Get App Usage..." -ForegroundColor Yellow
try {
    $usageResponse = Invoke-RestMethod -Uri "$API_BASE/admin/mobile/usage" -Method Get -Headers $headers
    Write-Host "   ✓ App usage retrieved" -ForegroundColor Green
    Write-Host "   Active Sessions: $($usageResponse.data.active_sessions)" -ForegroundColor Gray
} catch {
    Write-Host "   ✗ Failed" -ForegroundColor Red
}

Write-Host ""

# 4. Test Get Active Sessions
Write-Host "4. Testing Get Active Sessions..." -ForegroundColor Yellow
try {
    $sessionsResponse = Invoke-RestMethod -Uri "$API_BASE/admin/mobile/sessions?per_page=10" -Method Get -Headers $headers
    Write-Host "   ✓ Active sessions retrieved" -ForegroundColor Green
} catch {
    Write-Host "   ✗ Failed" -ForegroundColor Red
}

Write-Host ""
Write-Host "=== All Tests Complete ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "Access the admin dashboard at:" -ForegroundColor Yellow
Write-Host "http://localhost:8002/admin-dashboard/index.html" -ForegroundColor Cyan
