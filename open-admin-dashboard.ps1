# Open Admin Dashboard
# This script opens the admin dashboard in your default browser

Write-Host "=== Opening Admin Dashboard ===" -ForegroundColor Cyan
Write-Host ""

# Get the full path to the HTML file
$dashboardPath = Join-Path $PSScriptRoot "admin-dashboard\index.html"

if (Test-Path $dashboardPath) {
    Write-Host "Opening dashboard in your default browser..." -ForegroundColor Green
    Start-Process $dashboardPath
    
    Write-Host ""
    Write-Host "Admin Dashboard Opened!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Default Login Credentials:" -ForegroundColor Yellow
    Write-Host "  Email: admin@ajo.test" -ForegroundColor White
    Write-Host "  Password: password" -ForegroundColor White
    Write-Host ""
    Write-Host "Note: Make sure your Docker backend is running!" -ForegroundColor Yellow
    Write-Host "  Check with: docker-compose ps" -ForegroundColor Gray
} else {
    Write-Host "Error: Dashboard file not found at $dashboardPath" -ForegroundColor Red
}
