# Check Admin User in Database
Write-Host "=== Checking Admin User ===" -ForegroundColor Cyan
Write-Host ""

# Check if admin user exists
Write-Host "Querying database for admin user..." -ForegroundColor Yellow
docker exec ajo_laravel php artisan tinker --execute="echo User::where('email', 'admin@ajo.test')->first();"

Write-Host ""
Write-Host "If no admin user found, run the seeder:" -ForegroundColor Yellow
Write-Host "  docker exec ajo_laravel php artisan db:seed --class=AdminUserSeeder" -ForegroundColor White
Write-Host ""
Write-Host "Admin Credentials:" -ForegroundColor Green
Write-Host "  Email: admin@ajo.test" -ForegroundColor White
Write-Host "  Password: password" -ForegroundColor White
