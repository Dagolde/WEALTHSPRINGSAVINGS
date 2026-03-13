# Test Analytics Dashboard
Write-Host "Testing Admin Dashboard Analytics..." -ForegroundColor Cyan

# Test if admin dashboard is accessible
Write-Host "`nTesting admin dashboard access..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8002/admin-dashboard/index.html" -Method GET -UseBasicParsing
    if ($response.StatusCode -eq 200) {
        Write-Host "✓ Admin dashboard is accessible" -ForegroundColor Green
    }
} catch {
    Write-Host "✗ Admin dashboard is NOT accessible: $_" -ForegroundColor Red
    Write-Host "Make sure nginx is running and configured correctly" -ForegroundColor Yellow
    exit 1
}

# Test if app.js is accessible
Write-Host "`nTesting app.js access..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8002/admin-dashboard/app.js" -Method GET -UseBasicParsing
    if ($response.StatusCode -eq 200) {
        Write-Host "✓ app.js is accessible" -ForegroundColor Green
        
        # Check if analytics function exists
        if ($response.Content -match "loadAnalytics") {
            Write-Host "✓ loadAnalytics function found in app.js" -ForegroundColor Green
        } else {
            Write-Host "✗ loadAnalytics function NOT found in app.js" -ForegroundColor Red
        }
        
        # Check if analytics charts are implemented
        if ($response.Content -match "createAnalyticsCharts") {
            Write-Host "✓ createAnalyticsCharts function found in app.js" -ForegroundColor Green
        } else {
            Write-Host "✗ createAnalyticsCharts function NOT found in app.js" -ForegroundColor Red
        }
    }
} catch {
    Write-Host "✗ app.js is NOT accessible: $_" -ForegroundColor Red
    exit 1
}

# Test analytics endpoints
Write-Host "`nTesting analytics API endpoints..." -ForegroundColor Yellow

# First, login to get token
Write-Host "Logging in as admin..." -ForegroundColor Yellow
$loginBody = @{
    email = "admin@ajo.test"
    password = "password"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-RestMethod -Uri "http://localhost:8002/api/v1/auth/admin/login" -Method POST -Body $loginBody -ContentType "application/json"
    $token = $loginResponse.data.token
    Write-Host "✓ Admin login successful" -ForegroundColor Green
} catch {
    Write-Host "✗ Admin login failed: $_" -ForegroundColor Red
    Write-Host "Make sure the admin user exists (run backend/database/seeders/AdminUserSeeder.php)" -ForegroundColor Yellow
    exit 1
}

# Test analytics endpoints
$headers = @{
    "Authorization" = "Bearer $token"
    "Accept" = "application/json"
}

$endpoints = @(
    "/api/v1/admin/analytics/users",
    "/api/v1/admin/analytics/groups",
    "/api/v1/admin/analytics/transactions",
    "/api/v1/admin/analytics/revenue"
)

foreach ($endpoint in $endpoints) {
    Write-Host "`nTesting $endpoint..." -ForegroundColor Yellow
    try {
        $response = Invoke-RestMethod -Uri "http://localhost:8002$endpoint" -Method GET -Headers $headers
        if ($response.success) {
            Write-Host "✓ $endpoint is working" -ForegroundColor Green
            $dataKeys = $response.data.PSObject.Properties.Name -join ', '
            Write-Host "  Data keys: $dataKeys" -ForegroundColor Gray
        }
    } catch {
        Write-Host "✗ $endpoint failed: $_" -ForegroundColor Red
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Analytics Dashboard Test Complete!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "`nYou can now access the admin dashboard at:" -ForegroundColor Green
Write-Host "http://localhost:8002/admin-dashboard/index.html" -ForegroundColor Yellow
Write-Host "`nLogin credentials:" -ForegroundColor Green
Write-Host "Email: admin@ajo.test" -ForegroundColor Yellow
Write-Host "Password: password" -ForegroundColor Yellow
