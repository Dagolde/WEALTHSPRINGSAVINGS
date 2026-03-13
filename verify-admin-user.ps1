# Verify Admin User
# This script checks if the admin user exists in the database

Write-Host "=== Verifying Admin User ===" -ForegroundColor Cyan
Write-Host ""

Write-Host "Checking if admin user exists..." -ForegroundColor Yellow

$result = docker exec ajo_laravel php artisan tinker --execute="echo json_encode(App\Models\User::where('email', 'admin@ajo.test')->first());"

if ($result) {
    Write-Host ""
    Write-Host "Admin User Found!" -ForegroundColor Green
    Write-Host ""
    Write-Host "User Details:" -ForegroundColor Cyan
    $result | ConvertFrom-Json | Format-List
    Write-Host ""
    Write-Host "You can now login to the admin dashboard with:" -ForegroundColor Yellow
    Write-Host "  Email: admin@ajo.test" -ForegroundColor White
    Write-Host "  Password: password" -ForegroundColor White
} else {
    Write-Host ""
    Write-Host "Admin user not found!" -ForegroundColor Red
    Write-Host "Run: docker exec ajo_laravel php artisan db:seed --class=AdminUserSeeder" -ForegroundColor Yellow
}

Write-Host ""
