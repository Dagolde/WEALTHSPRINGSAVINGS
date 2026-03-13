# Reset Migration on CloudPanel Server
# This script resets the failed migration so it can run again with the MySQL fix

Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "Reset Migration on CloudPanel Server" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Get server details from GitHub secrets or prompt user
$serverHost = $env:SERVER_HOST
$serverUser = $env:SERVER_USER
$deployPath = $env:DEPLOY_PATH

if (-not $serverHost) {
    $serverHost = Read-Host "Enter server host (IP or domain)"
}

if (-not $serverUser) {
    $serverUser = Read-Host "Enter server user"
}

if (-not $deployPath) {
    $deployPath = Read-Host "Enter deployment path"
}

Write-Host "Server: $serverHost" -ForegroundColor Yellow
Write-Host "User: $serverUser" -ForegroundColor Yellow
Write-Host "Path: $deployPath" -ForegroundColor Yellow
Write-Host ""

# Create the SSH command
$sshCommand = @"
cd $deployPath

echo 'Removing failed migration from migrations table...'
php artisan migrate:rollback --step=1 --force

echo ''
echo 'Running migration again with MySQL fix...'
php artisan migrate --force

echo ''
echo 'Migration reset complete!'
"@

Write-Host "Connecting to server..." -ForegroundColor Yellow
ssh "${serverUser}@${serverHost}" $sshCommand

Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "Done!" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
