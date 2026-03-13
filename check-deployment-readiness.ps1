# Check Deployment Readiness Script
# This script helps you verify you have all the information needed for GitHub Secrets

Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "  GitHub Deployment Readiness Check" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ""

$allReady = $true

# Check 1: SSH Key Files
Write-Host "1. Checking SSH Key Files..." -ForegroundColor Yellow
if (Test-Path "cloudpanel-deploy-key") {
    Write-Host "   ✓ Private key found: cloudpanel-deploy-key" -ForegroundColor Green
    Write-Host "   → This will be used for SSH_PRIVATE_KEY secret" -ForegroundColor Gray
} else {
    Write-Host "   ✗ Private key NOT found: cloudpanel-deploy-key" -ForegroundColor Red
    Write-Host "   → Run: ssh-keygen -t ed25519 -C 'deployment@wealthspring' -f cloudpanel-deploy-key" -ForegroundColor Yellow
    $allReady = $false
}

if (Test-Path "cloudpanel-deploy-key.pub") {
    Write-Host "   ✓ Public key found: cloudpanel-deploy-key.pub" -ForegroundColor Green
    Write-Host "   → This needs to be added to your server's ~/.ssh/authorized_keys" -ForegroundColor Gray
} else {
    Write-Host "   ✗ Public key NOT found: cloudpanel-deploy-key.pub" -ForegroundColor Red
    $allReady = $false
}
Write-Host ""

# Check 2: Display Public Key
if (Test-Path "cloudpanel-deploy-key.pub") {
    Write-Host "2. Your Public Key (add this to server):" -ForegroundColor Yellow
    Write-Host "   ----------------------------------------" -ForegroundColor Gray
    Get-Content "cloudpanel-deploy-key.pub" | ForEach-Object { Write-Host "   $_" -ForegroundColor White }
    Write-Host "   ----------------------------------------" -ForegroundColor Gray
    Write-Host ""
}

# Check 3: Server Information Needed
Write-Host "3. Required Information for GitHub Secrets:" -ForegroundColor Yellow
Write-Host ""

Write-Host "   SECRET 1: SERVER_HOST" -ForegroundColor Cyan
Write-Host "   → Your CloudPanel server IP or domain" -ForegroundColor Gray
Write-Host "   → Example: 123.45.67.89 or server.yourdomain.com" -ForegroundColor Gray
$serverHost = Read-Host "   Enter your SERVER_HOST"
Write-Host ""

Write-Host "   SECRET 2: SERVER_USER" -ForegroundColor Cyan
Write-Host "   → Your CloudPanel site user" -ForegroundColor Gray
Write-Host "   → Example: clpuser-abc123" -ForegroundColor Gray
Write-Host "   → Find this in CloudPanel → Sites → Your Site → Site User" -ForegroundColor Gray
$serverUser = Read-Host "   Enter your SERVER_USER"
Write-Host ""

Write-Host "   SECRET 3: SSH_PRIVATE_KEY" -ForegroundColor Cyan
Write-Host "   → Contents of cloudpanel-deploy-key file" -ForegroundColor Gray
Write-Host "   → Copy the ENTIRE file including BEGIN and END lines" -ForegroundColor Gray
if (Test-Path "cloudpanel-deploy-key") {
    Write-Host "   → File is ready to copy (see below)" -ForegroundColor Green
} else {
    Write-Host "   → File NOT found - generate SSH key first" -ForegroundColor Red
}
Write-Host ""

Write-Host "   SECRET 4: DEPLOY_PATH" -ForegroundColor Cyan
Write-Host "   → Full path to your site directory on server" -ForegroundColor Gray
Write-Host "   → Example: /home/clpuser-abc123/htdocs/yourdomain.com" -ForegroundColor Gray
$deployPath = Read-Host "   Enter your DEPLOY_PATH"
Write-Host ""

# Check 4: Test SSH Connection
Write-Host "4. Testing SSH Connection..." -ForegroundColor Yellow
if ($serverHost -and $serverUser -and (Test-Path "cloudpanel-deploy-key")) {
    Write-Host "   Testing connection to $serverUser@$serverHost..." -ForegroundColor Gray
    
    $testCommand = "ssh -i cloudpanel-deploy-key -o ConnectTimeout=5 -o StrictHostKeyChecking=no $serverUser@$serverHost 'echo Connection successful'"
    
    try {
        $result = Invoke-Expression $testCommand 2>&1
        if ($result -match "Connection successful") {
            Write-Host "   ✓ SSH connection successful!" -ForegroundColor Green
        } else {
            Write-Host "   ✗ SSH connection failed" -ForegroundColor Red
            Write-Host "   → Make sure public key is added to server's ~/.ssh/authorized_keys" -ForegroundColor Yellow
            $allReady = $false
        }
    } catch {
        Write-Host "   ✗ SSH connection failed: $_" -ForegroundColor Red
        Write-Host "   → Make sure public key is added to server's ~/.ssh/authorized_keys" -ForegroundColor Yellow
        $allReady = $false
    }
} else {
    Write-Host "   ⚠ Skipping SSH test - missing required information" -ForegroundColor Yellow
    $allReady = $false
}
Write-Host ""

# Summary
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "  Summary" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ""

if ($allReady) {
    Write-Host "✓ All checks passed! You're ready to configure GitHub Secrets." -ForegroundColor Green
} else {
    Write-Host "⚠ Some checks failed. Please review the issues above." -ForegroundColor Yellow
}
Write-Host ""

# Display Private Key for GitHub Secret
if (Test-Path "cloudpanel-deploy-key") {
    Write-Host "==================================================" -ForegroundColor Cyan
    Write-Host "  Private Key for SSH_PRIVATE_KEY Secret" -ForegroundColor Cyan
    Write-Host "==================================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Copy everything below (including BEGIN and END lines):" -ForegroundColor Yellow
    Write-Host "---------------------------------------------------" -ForegroundColor Gray
    Get-Content "cloudpanel-deploy-key" | ForEach-Object { Write-Host $_ -ForegroundColor White }
    Write-Host "---------------------------------------------------" -ForegroundColor Gray
    Write-Host ""
}

# Next Steps
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host "  Next Steps" -ForegroundColor Cyan
Write-Host "==================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Go to: https://github.com/Dagolde/WEALTHSPRINGSAVINGS/settings/secrets/actions" -ForegroundColor White
Write-Host "2. Click 'New repository secret'" -ForegroundColor White
Write-Host "3. Add these 4 secrets:" -ForegroundColor White
Write-Host ""
Write-Host "   - SERVER_HOST: $serverHost" -ForegroundColor Cyan
Write-Host "   - SERVER_USER: $serverUser" -ForegroundColor Cyan
Write-Host "   - SSH_PRIVATE_KEY: (copy from above)" -ForegroundColor Cyan
Write-Host "   - DEPLOY_PATH: $deployPath" -ForegroundColor Cyan
Write-Host ""
Write-Host "4. After adding secrets, push to GitHub to trigger deployment:" -ForegroundColor White
Write-Host "   git commit --allow-empty -m 'Test deployment'" -ForegroundColor Gray
Write-Host "   git push origin main" -ForegroundColor Gray
Write-Host ""
Write-Host "5. Monitor deployment: https://github.com/Dagolde/WEALTHSPRINGSAVINGS/actions" -ForegroundColor White
Write-Host ""

Write-Host "For detailed instructions, see: GITHUB_SECRETS_SETUP_GUIDE.md" -ForegroundColor Yellow
Write-Host ""
