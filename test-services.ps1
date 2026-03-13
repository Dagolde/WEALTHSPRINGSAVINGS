# Test all services are running and responding

Write-Host "🧪 Testing Services..." -ForegroundColor Cyan
Write-Host ""

$services = @(
    @{Name="Laravel API"; URL="http://localhost:8000"; Expected="Laravel"},
    @{Name="FastAPI"; URL="http://localhost:8001"; Expected="FastAPI"},
    @{Name="Database Admin"; URL="http://localhost:8080"; Expected="Adminer"},
    @{Name="Redis Commander"; URL="http://localhost:8081"; Expected="Redis"}
)

$allPassed = $true

foreach ($service in $services) {
    Write-Host "Testing $($service.Name)..." -NoNewline
    try {
        $response = Invoke-WebRequest -Uri $service.URL -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
        if ($response.StatusCode -eq 200) {
            Write-Host " ✓ OK" -ForegroundColor Green
        } else {
            Write-Host " ✗ Failed (Status: $($response.StatusCode))" -ForegroundColor Red
            $allPassed = $false
        }
    } catch {
        Write-Host " ✗ Not responding" -ForegroundColor Red
        $allPassed = $false
    }
}

Write-Host ""
if ($allPassed) {
    Write-Host "✓ All services are running!" -ForegroundColor Green
} else {
    Write-Host "⚠ Some services are not responding. Check logs with:" -ForegroundColor Yellow
    Write-Host "  docker-compose -f docker-compose.dev.yml logs" -ForegroundColor White
}

Write-Host ""
Write-Host "Container Status:" -ForegroundColor Cyan
docker-compose -f docker-compose.dev.yml ps
