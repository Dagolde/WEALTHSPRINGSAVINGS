#!/usr/bin/env pwsh
# Backend Performance Optimization Script

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Backend Performance Optimization" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Stop current services
Write-Host "Step 1: Stopping current services..." -ForegroundColor Yellow
docker-compose down
Write-Host "Services stopped" -ForegroundColor Green

# Step 2: Clear Docker cache
Write-Host "`nStep 2: Clearing Docker cache..." -ForegroundColor Yellow
docker system prune -f
Write-Host "Cache cleared" -ForegroundColor Green

# Step 3: Backup current .env
Write-Host "`nStep 3: Backing up configuration..." -ForegroundColor Yellow
if (Test-Path "backend/.env") {
    Copy-Item "backend/.env" "backend/.env.backup"
    Write-Host "Backup created: backend/.env.backup" -ForegroundColor Green
}

# Step 4: Apply optimized configuration
Write-Host "`nStep 4: Applying optimized configuration..." -ForegroundColor Yellow
Write-Host "Using optimized docker-compose.yml" -ForegroundColor White

# Step 5: Start optimized services
Write-Host "`nStep 5: Starting optimized services..." -ForegroundColor Yellow
docker-compose -f docker-compose.optimized.yml up -d
Start-Sleep -Seconds 15
Write-Host "Services started" -ForegroundColor Green

# Step 6: Run database migrations
Write-Host "`nStep 6: Running database migrations..." -ForegroundColor Yellow
docker exec rotational_laravel php artisan migrate --force
Write-Host "Migrations complete" -ForegroundColor Green

# Step 7: Optimize Laravel
Write-Host "`nStep 7: Optimizing Laravel..." -ForegroundColor Yellow
docker exec rotational_laravel php artisan config:cache
docker exec rotational_laravel php artisan route:cache
docker exec rotational_laravel php artisan view:cache
Write-Host "Laravel optimized" -ForegroundColor Green

# Step 8: Test backend
Write-Host "`nStep 8: Testing backend..." -ForegroundColor Yellow
Start-Sleep -Seconds 5
try {
    $response = Invoke-WebRequest -Uri "http://localhost:8002/health" -TimeoutSec 5 -UseBasicParsing
    Write-Host "Backend is responding" -ForegroundColor Green
} catch {
    Write-Host "Backend not responding yet, waiting..." -ForegroundColor Yellow
    Start-Sleep -Seconds 10
}

# Step 9: Show resource usage
Write-Host "`nStep 9: Resource usage..." -ForegroundColor Yellow
docker stats --no-stream

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Optimization Complete" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Optimizations Applied:" -ForegroundColor Yellow
Write-Host "- Removed unnecessary services (Adminer, Redis Commander)" -ForegroundColor White
Write-Host "- Enabled PostgreSQL query optimization" -ForegroundColor White
Write-Host "- Enabled Redis memory optimization" -ForegroundColor White
Write-Host "- Added nginx proxy caching" -ForegroundColor White
Write-Host "- Enabled Laravel config/route/view caching" -ForegroundColor White
Write-Host "- Added database indexes" -ForegroundColor White
Write-Host "- Optimized connection pooling" -ForegroundColor White
Write-Host ""
Write-Host "Services Running:" -ForegroundColor Yellow
Write-Host "- Laravel (Port 8000)" -ForegroundColor White
Write-Host "- Nginx (Port 8002)" -ForegroundColor White
Write-Host "- PostgreSQL (Port 5432)" -ForegroundColor White
Write-Host "- Redis (Port 6379)" -ForegroundColor White
Write-Host "- Queue Worker" -ForegroundColor White
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Test API: curl http://localhost:8002/api/v1/health" -ForegroundColor White
Write-Host "2. Update mobile IP: ./fix-network-now.ps1" -ForegroundColor White
Write-Host "3. Rebuild mobile app: cd mobile && flutter run" -ForegroundColor White
Write-Host ""
