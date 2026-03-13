# Test Wallet Funding Fix
Write-Host "Testing Wallet Funding Fix..." -ForegroundColor Cyan

# Test with user token (from logs: Bearer 3|ZlGhzgOLuxCmoVxfeeElUyMF1oOmn2xGqgXiWIXd31629b73)
$token = "3|ZlGhzgOLuxCmoVxfeeElUyMF1oOmn2xGqgXiWIXd31629b73"

Write-Host "`nTest 1: Fund wallet with card payment method" -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "http://192.168.1.106:8002/api/v1/wallet/fund" `
        -Method POST `
        -Headers @{
            "Authorization" = "Bearer $token"
            "Content-Type" = "application/json"
            "Accept" = "application/json"
        } `
        -Body '{"amount": 5000, "payment_method": "card"}' `
        -ErrorAction Stop
    
    Write-Host "Success: $($response.success)" -ForegroundColor Green
    Write-Host "Message: $($response.message)" -ForegroundColor Green
    Write-Host "Data: $($response.data | ConvertTo-Json -Depth 3)" -ForegroundColor Green
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.ErrorDetails) {
        Write-Host "Details: $($_.ErrorDetails.Message)" -ForegroundColor Red
    }
}

Write-Host "`nTest 2: Fund wallet with bank_transfer payment method" -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "http://192.168.1.106:8002/api/v1/wallet/fund" `
        -Method POST `
        -Headers @{
            "Authorization" = "Bearer $token"
            "Content-Type" = "application/json"
            "Accept" = "application/json"
        } `
        -Body '{"amount": 10000, "payment_method": "bank_transfer"}' `
        -ErrorAction Stop
    
    Write-Host "Success: $($response.success)" -ForegroundColor Green
    Write-Host "Message: $($response.message)" -ForegroundColor Green
    Write-Host "Data: $($response.data | ConvertTo-Json -Depth 3)" -ForegroundColor Green
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.ErrorDetails) {
        Write-Host "Details: $($_.ErrorDetails.Message)" -ForegroundColor Red
    }
}

Write-Host "`nTest 3: Check wallet balance" -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "http://192.168.1.106:8002/api/v1/wallet/balance" `
        -Method GET `
        -Headers @{
            "Authorization" = "Bearer $token"
            "Accept" = "application/json"
        } `
        -ErrorAction Stop
    
    Write-Host "Balance: $($response.data.balance)" -ForegroundColor Green
} catch {
    Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}
