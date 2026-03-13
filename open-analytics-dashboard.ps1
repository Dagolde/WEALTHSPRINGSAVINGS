# Open Admin Analytics Dashboard
Write-Host "Opening Admin Analytics Dashboard..." -ForegroundColor Cyan

# Check if backend is running
Write-Host "`nChecking if backend is running..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8002/api/v1/health" -Method GET -UseBasicParsing -TimeoutSec 5 -ErrorAction Stop
    Write-Host "✓ Backend is running" -ForegroundColor Green
} catch {
    Write-Host "✗ Backend is NOT running or health endpoint not available" -ForegroundColor Yellow
    Write-Host "Continuing anyway..." -ForegroundColor Gray
}

# Check if admin dashboard is accessible
Write-Host "Checking if admin dashboard is accessible..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8002/admin-dashboard/index.html" -Method HEAD -UseBasicParsing -TimeoutSec 5
    Write-Host "✓ Admin dashboard is accessible" -ForegroundColor Green
} catch {
    Write-Host "✗ Admin dashboard is NOT accessible" -ForegroundColor Red
    Write-Host "Please check nginx configuration" -ForegroundColor Yellow
    exit 1
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Opening Admin Dashboard in Browser..." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan

# Open in default browser
Start-Process "http://localhost:8002/admin-dashboard/index.html"

Write-Host "`nAdmin Dashboard opened!" -ForegroundColor Green
Write-Host "`nLogin credentials:" -ForegroundColor Yellow
Write-Host "Email: admin@ajo.test" -ForegroundColor White
Write-Host "Password: password" -ForegroundColor White
Write-Host "`nTo view analytics:" -ForegroundColor Yellow
Write-Host "1. Login with the credentials above" -ForegroundColor White
Write-Host "2. Click 'Analytics' in the sidebar" -ForegroundColor White
Write-Host "3. View comprehensive analytics dashboard with charts" -ForegroundColor White
