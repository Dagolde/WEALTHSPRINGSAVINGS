# Test Mobile Registration Endpoint
Write-Host "Testing Mobile Registration..." -ForegroundColor Cyan

Write-Host "`nStep 1: Testing health endpoint..." -ForegroundColor Yellow
try {
    $health = Invoke-WebRequest -Uri "http://192.168.1.106:8002/api/v1/health" -UseBasicParsing
    Write-Host "✓ Backend is accessible" -ForegroundColor Green
    Write-Host "  Response: $($health.Content)" -ForegroundColor White
} catch {
    Write-Host "✗ Backend is not accessible" -ForegroundColor Red
    Write-Host "  Error: $_" -ForegroundColor Red
    exit 1
}

Write-Host "`nStep 2: Testing registration endpoint..." -ForegroundColor Yellow
try {
    $body = @{
        name = "Test User"
        email = "test@example.com"
        phone = "08012345678"
        password = "Test123456"
    } | ConvertTo-Json

    $headers = @{
        "Content-Type" = "application/json"
        "Accept" = "application/json"
    }

    $response = Invoke-WebRequest -Uri "http://192.168.1.106:8002/api/v1/auth/register" `
        -Method POST `
        -Headers $headers `
        -Body $body `
        -UseBasicParsing

    Write-Host "✓ Registration endpoint is working" -ForegroundColor Green
    Write-Host "  Status: $($response.StatusCode)" -ForegroundColor White
    Write-Host "  Response: $($response.Content)" -ForegroundColor White
} catch {
    Write-Host "✗ Registration failed" -ForegroundColor Red
    Write-Host "  Error: $_" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "  Response Body: $responseBody" -ForegroundColor Red
    }
}

Write-Host "`nMobile Connection Checklist:" -ForegroundColor Cyan
Write-Host "1. Ensure mobile device is connected to the same WiFi network" -ForegroundColor White
Write-Host "2. Ensure Windows Firewall allows port 8002 (run allow-port-8002.ps1 as admin)" -ForegroundColor White
Write-Host "3. Ensure mobile .env file has API_BASE_URL=http://192.168.1.106:8002/api/v1" -ForegroundColor White
Write-Host "4. Restart the mobile app after changing .env" -ForegroundColor White
