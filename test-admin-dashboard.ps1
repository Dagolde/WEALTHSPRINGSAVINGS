# Test Admin Dashboard Data Loading
Write-Host "Testing Admin Dashboard..." -ForegroundColor Cyan

# Check database has data
Write-Host "`nStep 1: Checking database..." -ForegroundColor Yellow
docker exec rotational_laravel php artisan tinker --execute="echo 'Users: ' . App\Models\User::count() . PHP_EOL; echo 'Groups: ' . App\Models\Group::count() . PHP_EOL;"

Write-Host "`nStep 2: Open admin dashboard at http://localhost:8002/admin-dashboard/index.html" -ForegroundColor Yellow
Write-Host "Login with:" -ForegroundColor White
Write-Host "  Email: admin@ajo.test" -ForegroundColor Cyan
Write-Host "  Password: password" -ForegroundColor Cyan

Write-Host "`nTest complete!" -ForegroundColor Green
