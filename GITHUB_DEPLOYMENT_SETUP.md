# GitHub Deployment Setup Guide

This guide will help you set up GitHub integration for automatic deployment to your CloudPanel server.

---

## Overview

With this setup, you can:
1. Push code changes from your local machine to GitHub
2. Automatically deploy to your live server when you push to `main` branch
3. Manually trigger deployments from GitHub Actions
4. Keep your development and production environments in sync

---

## Part 1: Initial GitHub Setup

### Step 1: Create GitHub Repository

1. Go to https://github.com/new
2. Create a new repository:
   - **Name**: `rotational-contribution-app` (or your preferred name)
   - **Visibility**: Private (recommended) or Public
   - **DO NOT** initialize with README, .gitignore, or license (we already have these)

### Step 2: Initialize Git in Your Local Project

```powershell
# Navigate to your project root
cd path\to\your\project

# Initialize git (if not already initialized)
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial commit: Rotational Contribution App ready for deployment"

# Add GitHub remote (replace with your repository URL)
git remote add origin https://github.com/yourusername/rotational-contribution-app.git

# Push to GitHub
git branch -M main
git push -u origin main
```

---

## Part 2: Server SSH Key Setup

### Step 1: Generate SSH Key Pair (on your local machine)

```powershell
# Generate SSH key for deployment
ssh-keygen -t ed25519 -C "deployment@rotational-app" -f cloudpanel-deploy-key

# This creates two files:
# - cloudpanel-deploy-key (private key - keep secret!)
# - cloudpanel-deploy-key.pub (public key - add to server)
```

### Step 2: Add Public Key to Server

```bash
# SSH into your CloudPanel server
ssh your-user@your-server-ip

# Add the public key to authorized_keys
mkdir -p ~/.ssh
chmod 700 ~/.ssh
nano ~/.ssh/authorized_keys

# Paste the contents of cloudpanel-deploy-key.pub
# Save and exit (Ctrl+X, Y, Enter)

# Set correct permissions
chmod 600 ~/.ssh/authorized_keys
```

### Step 3: Test SSH Connection

```powershell
# Test the connection (from your local machine)
ssh -i cloudpanel-deploy-key your-user@your-server-ip

# If successful, you should be logged into your server
```

---

## Part 3: GitHub Secrets Configuration

### Step 1: Add Secrets to GitHub Repository

1. Go to your GitHub repository
2. Click **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Add the following secrets:

#### Required Secrets:

**SERVER_HOST**
- Value: Your server IP address or domain
- Example: `123.45.67.89` or `server.yourdomain.com`

**SERVER_USER**
- Value: Your CloudPanel site user
- Example: `clpuser-abc123`

**SSH_PRIVATE_KEY**
- Value: Contents of `cloudpanel-deploy-key` file (private key)
- Copy the entire file including `-----BEGIN OPENSSH PRIVATE KEY-----` and `-----END OPENSSH PRIVATE KEY-----`

**DEPLOY_PATH**
- Value: Full path to your site directory
- Example: `/home/clpuser-abc123/htdocs/yourdomain.com`

**SERVER_PORT** (Optional)
- Value: SSH port (default is 22)
- Only add if your server uses a different port

---

## Part 4: Server Git Setup

### Step 1: Clone Repository on Server

```bash
# SSH into your server
ssh your-user@your-server-ip

# Navigate to your site directory
cd /home/{site-user}/htdocs/{yourdomain.com}

# Backup existing files (if any)
mkdir -p ~/backups
tar -czf ~/backups/site-backup-$(date +%Y%m%d).tar.gz .

# Remove existing files
rm -rf *
rm -rf .[!.]*

# Clone your repository
git clone https://github.com/yourusername/rotational-contribution-app.git .

# Copy environment file
cp .env.cloudpanel .env

# Edit .env with your production values
nano .env

# Install dependencies
composer install --optimize-autoloader --no-dev

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed admin user
php artisan db:seed --class=AdminUserSeeder --force

# Create storage link
php artisan storage:link

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chmod -R 755 storage bootstrap/cache
chown -R {site-user}:clp storage bootstrap/cache
```

---

## Part 5: Deployment Workflow

### Automatic Deployment (Recommended)

Every time you push to the `main` branch, GitHub Actions will automatically deploy to your server.

```powershell
# Make changes to your code
# ...

# Stage changes
git add .

# Commit changes
git commit -m "Description of changes"

# Push to GitHub (triggers automatic deployment)
git push origin main
```

### Manual Deployment

You can also trigger deployment manually from GitHub:

1. Go to your repository on GitHub
2. Click **Actions** tab
3. Click **Deploy to CloudPanel** workflow
4. Click **Run workflow** button
5. Select branch and click **Run workflow**

---

## Part 6: Local Development Workflow

### Daily Development Workflow

```powershell
# 1. Pull latest changes from GitHub
git pull origin main

# 2. Make your changes
# Edit files, add features, fix bugs...

# 3. Test locally
docker-compose up -d
# Test your changes...

# 4. Stage and commit changes
git add .
git commit -m "Descriptive commit message"

# 5. Push to GitHub (auto-deploys to production)
git push origin main
```

### Branch-Based Development (Recommended for Teams)

```powershell
# Create a feature branch
git checkout -b feature/new-feature

# Make changes and commit
git add .
git commit -m "Add new feature"

# Push feature branch to GitHub
git push origin feature/new-feature

# Create Pull Request on GitHub
# After review and approval, merge to main
# This will trigger automatic deployment
```

---

## Part 7: Deployment Script (Alternative to GitHub Actions)

If you prefer manual deployment or GitHub Actions doesn't work, use this script:

### Create `deploy-from-local.ps1`

```powershell
# Deploy from Local Machine to CloudPanel Server
# Usage: .\deploy-from-local.ps1

param(
    [string]$ServerHost = "your-server-ip",
    [string]$ServerUser = "your-site-user",
    [string]$DeployPath = "/home/your-site-user/htdocs/yourdomain.com"
)

Write-Host "Deploying to CloudPanel Server..." -ForegroundColor Green

# Push to GitHub first
Write-Host "Pushing to GitHub..." -ForegroundColor Yellow
git push origin main

# SSH and deploy
Write-Host "Deploying to server..." -ForegroundColor Yellow
ssh "$ServerUser@$ServerHost" @"
cd $DeployPath
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart rotational-worker:* || echo 'Queue workers not configured'
chmod -R 755 storage bootstrap/cache
"@

Write-Host "Deployment completed!" -ForegroundColor Green
```

### Usage

```powershell
# Edit the script with your server details
# Then run:
.\deploy-from-local.ps1
```

---

## Part 8: Monitoring Deployments

### View Deployment Status

1. Go to your GitHub repository
2. Click **Actions** tab
3. See all deployment runs and their status
4. Click on any run to see detailed logs

### Check Deployment on Server

```bash
# SSH into server
ssh your-user@your-server-ip

# Check git status
cd /home/{site-user}/htdocs/{yourdomain.com}
git log -1

# Check application logs
tail -f storage/logs/laravel.log

# Check queue workers
sudo supervisorctl status rotational-worker:*
```

---

## Part 9: Rollback Procedure

If a deployment causes issues, you can rollback:

### Option 1: Rollback via Git

```bash
# SSH into server
ssh your-user@your-server-ip
cd /home/{site-user}/htdocs/{yourdomain.com}

# View recent commits
git log --oneline -10

# Rollback to previous commit
git reset --hard <commit-hash>

# Clear cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Restart workers
sudo supervisorctl restart rotational-worker:*
```

### Option 2: Rollback via GitHub

1. Go to your repository on GitHub
2. Find the commit you want to rollback to
3. Click **Revert** button
4. Push the revert commit
5. Automatic deployment will restore previous version

---

## Part 10: Best Practices

### Commit Messages

Use clear, descriptive commit messages:

```bash
# Good examples:
git commit -m "Fix: Wallet balance cache invalidation issue"
git commit -m "Feature: Add bank account verification"
git commit -m "Update: Improve admin dashboard performance"

# Bad examples:
git commit -m "fix"
git commit -m "updates"
git commit -m "changes"
```

### Testing Before Deployment

Always test locally before pushing:

```powershell
# Run tests
docker exec rotational_laravel php artisan test

# Check for errors
docker-compose logs backend

# Test mobile app connection
# Test admin dashboard
```

### Environment Variables

Never commit sensitive data:
- `.env` files are in `.gitignore`
- Use GitHub Secrets for sensitive values
- Keep production credentials secure

### Database Migrations

Always test migrations locally first:

```powershell
# Test migration locally
docker exec rotational_laravel php artisan migrate

# If successful, commit and push
git add database/migrations/
git commit -m "Add: New migration for feature X"
git push origin main
```

---

## Part 11: Troubleshooting

### Issue: GitHub Actions Fails

**Check:**
1. Verify all GitHub Secrets are set correctly
2. Check SSH key is added to server
3. Verify server path is correct
4. Check GitHub Actions logs for specific error

### Issue: Permission Denied on Server

```bash
# Fix permissions
cd /home/{site-user}/htdocs/{yourdomain.com}
chmod -R 755 storage bootstrap/cache
chown -R {site-user}:clp storage bootstrap/cache
```

### Issue: Composer Install Fails

```bash
# Clear composer cache
composer clear-cache

# Try again
composer install --optimize-autoloader --no-dev
```

### Issue: Git Pull Fails (Merge Conflicts)

```bash
# Stash local changes
git stash

# Pull latest
git pull origin main

# If you need local changes back
git stash pop
```

---

## Part 12: Security Checklist

- [ ] Private key is kept secure (never commit to git)
- [ ] GitHub repository is private (or public if intended)
- [ ] `.env` files are in `.gitignore`
- [ ] GitHub Secrets are properly configured
- [ ] SSH key has passphrase (optional but recommended)
- [ ] Server firewall allows SSH from GitHub Actions IPs
- [ ] Regular backups are configured
- [ ] Deployment logs are monitored

---

## Part 13: Quick Reference

### Common Commands

```powershell
# Push changes and deploy
git add .
git commit -m "Your message"
git push origin main

# Check deployment status
# Go to GitHub → Actions tab

# View server logs
ssh your-user@your-server-ip
tail -f /home/{site-user}/htdocs/{yourdomain.com}/storage/logs/laravel.log

# Restart queue workers
ssh your-user@your-server-ip
sudo supervisorctl restart rotational-worker:*
```

### GitHub Actions Status

- ✅ Green checkmark = Deployment successful
- ❌ Red X = Deployment failed (check logs)
- 🟡 Yellow dot = Deployment in progress

---

## Summary

You now have a complete GitHub-based deployment workflow:

1. ✅ Code is version controlled on GitHub
2. ✅ Automatic deployment on push to main
3. ✅ Manual deployment option available
4. ✅ Rollback capability
5. ✅ Deployment monitoring
6. ✅ Secure SSH-based deployment

**Workflow:**
Local Changes → Git Commit → Git Push → GitHub → Auto Deploy → Live Server

**Next Steps:**
1. Create GitHub repository
2. Set up SSH keys
3. Configure GitHub Secrets
4. Clone repository on server
5. Test deployment workflow
6. Start developing!

---

**Last Updated**: March 13, 2026
**Status**: ✅ READY FOR GITHUB DEPLOYMENT
