# Setup GitHub Deployment for Rotational Contribution App
# This script helps you set up GitHub integration and deployment

Write-Host ""
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "  GitHub Deployment Setup Wizard" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

function Write-Success { param($Message) Write-Host "✓ $Message" -ForegroundColor Green }
function Write-Error { param($Message) Write-Host "✗ $Message" -ForegroundColor Red }
function Write-Info { param($Message) Write-Host "ℹ $Message" -ForegroundColor Yellow }
function Write-Step { param($Message) Write-Host "`n▶ $Message" -ForegroundColor Cyan }

# Step 1: Check if git is initialized
Write-Step "Step 1: Checking Git status..."
if (Test-Path ".git") {
    Write-Success "Git repository already initialized"
} else {
    Write-Info "Initializing Git repository..."
    git init
    Write-Success "Git repository initialized"
}

# Step 2: Check for .gitignore
Write-Step "Step 2: Checking .gitignore..."
if (Test-Path ".gitignore") {
    Write-Success ".gitignore file exists"
} else {
    Write-Error ".gitignore file not found!"
}

# Step 3: Get GitHub repository URL
Write-Step "Step 3: GitHub Repository Setup"
Write-Host ""
Write-Info "Have you created a GitHub repository? (y/n)"
$hasRepo = Read-Host

if ($hasRepo -eq "n" -or $hasRepo -eq "N") {
    Write-Host ""
    Write-Info "Please create a GitHub repository first:"
    Write-Host "  1. Go to https://github.com/new" -ForegroundColor White
    Write-Host "  2. Create a new repository (private recommended)" -ForegroundColor White
    Write-Host "  3. DO NOT initialize with README, .gitignore, or license" -ForegroundColor White
    Write-Host "  4. Copy the repository URL" -ForegroundColor White
    Write-Host ""
    Write-Host "Press Enter when ready to continue..."
    Read-Host
}

Write-Host ""
Write-Info "Enter your GitHub repository URL:"
Write-Host "Example: https://github.com/yourusername/rotational-contribution-app.git" -ForegroundColor Gray
$repoUrl = Read-Host

if ($repoUrl) {
    # Check if remote already exists
    $remotes = git remote
    if ($remotes -contains "origin") {
        Write-Info "Remote 'origin' already exists. Updating..."
        git remote set-url origin $repoUrl
    } else {
        git remote add origin $repoUrl
    }
    Write-Success "GitHub remote configured"
}

# Step 4: SSH Key Generation
Write-Step "Step 4: SSH Key Setup"
Write-Host ""
Write-Info "Do you want to generate an SSH key for deployment? (y/n)"
$generateKey = Read-Host

if ($generateKey -eq "y" -or $generateKey -eq "Y") {
    $keyPath = "cloudpanel-deploy-key"
    
    if (Test-Path $keyPath) {
        Write-Info "SSH key already exists at $keyPath"
        Write-Info "Do you want to generate a new one? (y/n)"
        $regenerate = Read-Host
        
        if ($regenerate -eq "n" -or $regenerate -eq "N") {
            Write-Info "Using existing SSH key"
        } else {
            ssh-keygen -t ed25519 -C "deployment@rotational-app" -f $keyPath
            Write-Success "SSH key generated"
        }
    } else {
        ssh-keygen -t ed25519 -C "deployment@rotational-app" -f $keyPath
        Write-Success "SSH key generated"
    }
    
    Write-Host ""
    Write-Info "SSH Key Setup Instructions:"
    Write-Host "  1. Copy the PUBLIC key to your server:" -ForegroundColor White
    Write-Host "     cat $keyPath.pub" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  2. On your server, add it to authorized_keys:" -ForegroundColor White
    Write-Host "     mkdir -p ~/.ssh" -ForegroundColor Gray
    Write-Host "     nano ~/.ssh/authorized_keys" -ForegroundColor Gray
    Write-Host "     (paste the public key)" -ForegroundColor Gray
    Write-Host "     chmod 600 ~/.ssh/authorized_keys" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  3. Add the PRIVATE key to GitHub Secrets:" -ForegroundColor White
    Write-Host "     - Go to your repo → Settings → Secrets → Actions" -ForegroundColor Gray
    Write-Host "     - Add secret: SSH_PRIVATE_KEY" -ForegroundColor Gray
    Write-Host "     - Copy entire contents of: $keyPath" -ForegroundColor Gray
    Write-Host ""
}

# Step 5: GitHub Secrets Configuration
Write-Step "Step 5: GitHub Secrets Configuration"
Write-Host ""
Write-Info "You need to add these secrets to your GitHub repository:"
Write-Host ""
Write-Host "  1. SERVER_HOST" -ForegroundColor Yellow
Write-Host "     Your server IP or domain" -ForegroundColor Gray
Write-Host ""
Write-Host "  2. SERVER_USER" -ForegroundColor Yellow
Write-Host "     Your CloudPanel site user" -ForegroundColor Gray
Write-Host ""
Write-Host "  3. SSH_PRIVATE_KEY" -ForegroundColor Yellow
Write-Host "     Contents of cloudpanel-deploy-key file" -ForegroundColor Gray
Write-Host ""
Write-Host "  4. DEPLOY_PATH" -ForegroundColor Yellow
Write-Host "     Full path to your site directory" -ForegroundColor Gray
Write-Host "     Example: /home/clpuser-abc/htdocs/yourdomain.com" -ForegroundColor Gray
Write-Host ""
Write-Info "Go to: https://github.com/yourusername/yourrepo/settings/secrets/actions"
Write-Host ""
Write-Host "Press Enter when you've added the secrets..."
Read-Host

# Step 6: Initial Commit
Write-Step "Step 6: Initial Commit"
Write-Host ""
Write-Info "Do you want to commit and push all files now? (y/n)"
$commitNow = Read-Host

if ($commitNow -eq "y" -or $commitNow -eq "Y") {
    Write-Info "Staging all files..."
    git add .
    
    Write-Info "Creating initial commit..."
    git commit -m "Initial commit: Rotational Contribution App ready for deployment"
    
    Write-Info "Setting main branch..."
    git branch -M main
    
    Write-Info "Pushing to GitHub..."
    try {
        git push -u origin main
        Write-Success "Code pushed to GitHub successfully!"
    } catch {
        Write-Error "Failed to push to GitHub"
        Write-Host $_.Exception.Message -ForegroundColor Red
        Write-Info "You may need to authenticate with GitHub"
    }
}

# Step 7: Summary
Write-Host ""
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "  Setup Complete!" -ForegroundColor Cyan
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

Write-Success "GitHub deployment is configured!"
Write-Host ""
Write-Info "Next Steps:"
Write-Host ""
Write-Host "1. Server Setup:" -ForegroundColor Yellow
Write-Host "   - SSH into your CloudPanel server" -ForegroundColor White
Write-Host "   - Clone the repository to your site directory" -ForegroundColor White
Write-Host "   - Run: git clone $repoUrl ." -ForegroundColor Gray
Write-Host ""
Write-Host "2. Configure Environment:" -ForegroundColor Yellow
Write-Host "   - Copy .env.cloudpanel to .env" -ForegroundColor White
Write-Host "   - Update with your production values" -ForegroundColor White
Write-Host ""
Write-Host "3. Install Dependencies:" -ForegroundColor Yellow
Write-Host "   - Run: composer install --optimize-autoloader --no-dev" -ForegroundColor White
Write-Host "   - Run: php artisan key:generate" -ForegroundColor White
Write-Host "   - Run: php artisan migrate --force" -ForegroundColor White
Write-Host ""
Write-Host "4. Test Deployment:" -ForegroundColor Yellow
Write-Host "   - Make a small change" -ForegroundColor White
Write-Host "   - Commit and push: git add . && git commit -m 'Test' && git push" -ForegroundColor White
Write-Host "   - Check GitHub Actions for deployment status" -ForegroundColor White
Write-Host ""
Write-Host "Documentation:" -ForegroundColor Yellow
Write-Host "   - Full guide: GITHUB_DEPLOYMENT_SETUP.md" -ForegroundColor White
Write-Host "   - CloudPanel setup: CLOUDPANEL_DEPLOYMENT_GUIDE.md" -ForegroundColor White
Write-Host ""
Write-Host "Manual Deployment:" -ForegroundColor Yellow
Write-Host "   .\deploy-from-local.ps1 -ServerHost 'IP' -ServerUser 'user' -DeployPath '/path'" -ForegroundColor White
Write-Host ""
