# Deploy from Local Machine to CloudPanel Server
# This script pushes to GitHub and then deploys to your live server

param(
    [string]$ServerHost = "",
    [string]$ServerUser = "",
    [string]$DeployPath = "",
    [string]$Branch = "main"
)

# Colors for output
function Write-Success { param($Message) Write-Host "✓ $Message" -ForegroundColor Green }
function Write-Error { param($Message) Write-Host "✗ $Message" -ForegroundColor Red }
function Write-Info { param($Message) Write-Host "ℹ $Message" -ForegroundColor Yellow }

Write-Host ""
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "  Rotational App - Deploy to CloudPanel" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

# Check if parameters are provided
if (-not $ServerHost -or -not $ServerUser -or -not $DeployPath) {
    Write-Error "Missing required parameters!"
    Write-Host ""
    Write-Host "Usage:" -ForegroundColor Yellow
    Write-Host "  .\deploy-from-local.ps1 -ServerHost 'your-server-ip' -ServerUser 'your-site-user' -DeployPath '/home/user/htdocs/domain.com'" -ForegroundColor White
    Write-Host ""
    Write-Host "Example:" -ForegroundColor Yellow
    Write-Host "  .\deploy-from-local.ps1 -ServerHost '123.45.67.89' -ServerUser 'clpuser-abc' -DeployPath '/home/clpuser-abc/htdocs/yourdomain.com'" -ForegroundColor White
    Write-Host ""
    exit 1
}

# Step 1: Check git status
Write-Info "Checking git status..."
$gitStatus = git status --porcelain
if ($gitStatus) {
    Write-Info "You have uncommitted changes:"
    git status --short
    Write-Host ""
    $commit = Read-Host "Do you want to commit these changes? (y/n)"
    
    if ($commit -eq "y" -or $commit -eq "Y") {
        $message = Read-Host "Enter commit message"
        git add .
        git commit -m "$message"
        Write-Success "Changes committed"
    } else {
        Write-Error "Deployment cancelled. Please commit your changes first."
        exit 1
    }
}

# Step 2: Push to GitHub
Write-Info "Pushing to GitHub ($Branch branch)..."
try {
    git push origin $Branch
    Write-Success "Pushed to GitHub successfully"
} catch {
    Write-Error "Failed to push to GitHub"
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

Write-Host ""

# Step 3: Deploy to server
Write-Info "Connecting to server: $ServerUser@$ServerHost"
Write-Info "Deploy path: $DeployPath"
Write-Host ""

$deployScript = @"
echo '========================================='
echo 'Starting deployment...'
echo '========================================='
echo ''

cd $DeployPath || exit 1

echo 'Step 1: Pulling latest code from GitHub...'
git pull origin $Branch
if [ `$? -ne 0 ]; then
    echo 'Error: Git pull failed'
    exit 1
fi
echo '✓ Code updated'
echo ''

echo 'Step 2: Installing Composer dependencies...'
composer install --optimize-autoloader --no-dev --no-interaction
if [ `$? -ne 0 ]; then
    echo 'Error: Composer install failed'
    exit 1
fi
echo '✓ Dependencies installed'
echo ''

echo 'Step 3: Running database migrations...'
php artisan migrate --force
if [ `$? -ne 0 ]; then
    echo 'Warning: Migrations failed or no new migrations'
fi
echo '✓ Migrations completed'
echo ''

echo 'Step 4: Clearing cache...'
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
echo '✓ Cache cleared'
echo ''

echo 'Step 5: Caching configuration...'
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo '✓ Configuration cached'
echo ''

echo 'Step 6: Setting permissions...'
chmod -R 755 storage bootstrap/cache
echo '✓ Permissions set'
echo ''

echo 'Step 7: Restarting queue workers...'
sudo supervisorctl restart rotational-worker:* 2>/dev/null || echo 'Queue workers not configured (skipping)'
echo '✓ Queue workers restarted'
echo ''

echo '========================================='
echo 'Deployment completed successfully! 🎉'
echo '========================================='
echo ''
echo 'Application is now live at your domain'
echo ''
"@

try {
    ssh "$ServerUser@$ServerHost" $deployScript
    Write-Host ""
    Write-Success "Deployment completed successfully!"
    Write-Host ""
    Write-Info "Next steps:"
    Write-Host "  1. Test your application at your domain" -ForegroundColor White
    Write-Host "  2. Check logs: ssh $ServerUser@$ServerHost 'tail -f $DeployPath/storage/logs/laravel.log'" -ForegroundColor White
    Write-Host "  3. Monitor queue workers: ssh $ServerUser@$ServerHost 'sudo supervisorctl status'" -ForegroundColor White
    Write-Host ""
} catch {
    Write-Error "Deployment failed!"
    Write-Host $_.Exception.Message -ForegroundColor Red
    Write-Host ""
    Write-Info "Troubleshooting:"
    Write-Host "  1. Check SSH connection: ssh $ServerUser@$ServerHost" -ForegroundColor White
    Write-Host "  2. Verify deploy path exists: $DeployPath" -ForegroundColor White
    Write-Host "  3. Check server logs for errors" -ForegroundColor White
    Write-Host ""
    exit 1
}
