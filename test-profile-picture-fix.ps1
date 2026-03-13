# Test Profile Picture Upload Fix
Write-Host "Testing Profile Picture Upload Fix..." -ForegroundColor Cyan

# Get user token (assuming user ID 18 from the logs)
$loginResponse = Invoke-RestMethod -Uri "http://192.168.1.106:8002/api/v1/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body '{"phone":"+2348012345678","password":"password123"}'

if ($loginResponse.success) {
    $token = $loginResponse.data.token
    Write-Host "Login successful" -ForegroundColor Green
    
    # Check storage link
    Write-Host "`nChecking storage link..." -ForegroundColor Cyan
    docker exec rotational_laravel ls -la public/storage
    
    # Check profile_pictures directory
    Write-Host "`nChecking profile_pictures directory..." -ForegroundColor Cyan
    docker exec rotational_laravel ls -la storage/app/public/profile_pictures
    
    Write-Host "`nNow upload a profile picture from the mobile app and check:" -ForegroundColor Yellow
    Write-Host "1. File should be saved to storage/app/public/profile_pictures/" -ForegroundColor Yellow
    Write-Host "2. File should be accessible at http://192.168.1.106:8002/storage/profile_pictures/filename.jpg" -ForegroundColor Yellow
    Write-Host "3. The app should display the profile picture without 404 errors" -ForegroundColor Yellow
} else {
    Write-Host "Login failed" -ForegroundColor Red
}
