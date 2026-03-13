# 🚀 GitHub Deployment Ready!

Your Rotational Contribution App is now configured for GitHub-based deployment with automatic CI/CD to CloudPanel.

---

## ✅ What's Been Set Up

### 1. Git Configuration
- ✅ `.gitignore` - Excludes sensitive files and build artifacts
- ✅ `.github/workflows/deploy-to-cloudpanel.yml` - GitHub Actions workflow
- ✅ Git hooks for code quality

### 2. Deployment Scripts
- ✅ `deploy-from-local.ps1` - Manual deployment from Windows
- ✅ `setup-github-deployment.ps1` - Interactive setup wizard
- ✅ `deploy-to-cloudpanel.sh` - Server-side deployment script

### 3. Documentation
- ✅ `README.md` - Project overview and quick start
- ✅ `GITHUB_DEPLOYMENT_SETUP.md` - Complete GitHub setup guide
- ✅ `CLOUDPANEL_DEPLOYMENT_GUIDE.md` - CloudPanel deployment guide
- ✅ `CLOUDPANEL_QUICK_START.md` - Quick deployment checklist

---

## 🎯 Quick Start Guide

### Option 1: Automated Setup (Recommended)

Run the setup wizard:

```powershell
.\setup-github-deployment.ps1
```

This will guide you through:
1. Git initialization
2. GitHub repository setup
3. SSH key generation
4. GitHub Secrets configuration
5. Initial commit and push

### Option 2: Manual Setup

#### Step 1: Create GitHub Repository

1. Go to https://github.com/new
2. Create repository: `rotational-contribution-app`
3. Keep it private (recommended)
4. Don't initialize with README

#### Step 2: Initialize Git and Push

```powershell
# Initialize git
git init

# Add all files
git add .

# Create initial commit
git commit -m "Initial commit: Rotational Contribution App"

# Add GitHub remote (replace with your URL)
git remote add origin https://github.com/yourusername/rotational-contribution-app.git

# Push to GitHub
git branch -M main
git push -u origin main
```

#### Step 3: Generate SSH Key

```powershell
# Generate deployment key
ssh-keygen -t ed25519 -C "deployment@rotational-app" -f cloudpanel-deploy-key

# This creates:
# - cloudpanel-deploy-key (private key)
# - cloudpanel-deploy-key.pub (public key)
```

#### Step 4: Configure Server

```bash
# SSH into your CloudPanel server
ssh your-user@your-server-ip

# Add public key to authorized_keys
mkdir -p ~/.ssh
nano ~/.ssh/authorized_keys
# Paste contents of cloudpanel-deploy-key.pub
chmod 600 ~/.ssh/authorized_keys

# Clone repository
cd /home/{site-user}/htdocs/{yourdomain.com}
rm -rf *
git clone https://github.com/yourusername/rotational-contribution-app.git .

# Setup application
cp .env.cloudpanel .env
nano .env  # Update with your values
composer install --optimize-autoloader --no-dev
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 755 storage bootstrap/cache
```

#### Step 5: Configure GitHub Secrets

Go to: `https://github.com/yourusername/yourrepo/settings/secrets/actions`

Add these secrets:

| Secret Name | Value | Example |
|-------------|-------|---------|
| `SERVER_HOST` | Your server IP or domain | `123.45.67.89` |
| `SERVER_USER` | CloudPanel site user | `clpuser-abc123` |
| `SSH_PRIVATE_KEY` | Contents of `cloudpanel-deploy-key` | (entire file) |
| `DEPLOY_PATH` | Full path to site directory | `/home/clpuser-abc123/htdocs/yourdomain.com` |

---

## 🔄 Daily Workflow

### Making Changes and Deploying

```powershell
# 1. Make your changes
# Edit files, add features, fix bugs...

# 2. Test locally
docker-compose up -d
# Test your changes...

# 3. Commit changes
git add .
git commit -m "Description of changes"

# 4. Push to GitHub (triggers automatic deployment)
git push origin main
```

### Monitoring Deployment

1. Go to your GitHub repository
2. Click **Actions** tab
3. See deployment status:
   - ✅ Green = Success
   - ❌ Red = Failed (check logs)
   - 🟡 Yellow = In progress

### Manual Deployment (Alternative)

If you prefer manual deployment or GitHub Actions isn't working:

```powershell
.\deploy-from-local.ps1 `
  -ServerHost "your-server-ip" `
  -ServerUser "your-site-user" `
  -DeployPath "/home/your-site-user/htdocs/yourdomain.com"
```

---

## 📋 Deployment Checklist

### Before First Deployment

- [ ] GitHub repository created
- [ ] SSH key generated and added to server
- [ ] GitHub Secrets configured
- [ ] Repository cloned on server
- [ ] `.env` file configured on server
- [ ] Database created in CloudPanel
- [ ] Redis installed on server
- [ ] Supervisor configured for queue workers
- [ ] Cron job configured for scheduler

### After Each Deployment

- [ ] Check GitHub Actions status
- [ ] Test application at your domain
- [ ] Check error logs: `tail -f storage/logs/laravel.log`
- [ ] Verify queue workers: `sudo supervisorctl status`
- [ ] Test key features (login, wallet, groups)

---

## 🔍 Monitoring and Logs

### View Deployment Logs

**On GitHub:**
1. Go to repository → Actions
2. Click on latest workflow run
3. View detailed logs

**On Server:**
```bash
# Application logs
ssh your-user@your-server-ip
tail -f /home/{site-user}/htdocs/{yourdomain.com}/storage/logs/laravel.log

# Nginx logs
sudo tail -f /var/log/nginx/{yourdomain.com}_error.log

# Queue worker logs
tail -f /home/{site-user}/htdocs/{yourdomain.com}/storage/logs/worker.log
```

### Check Application Status

```bash
# SSH into server
ssh your-user@your-server-ip

# Check git status
cd /home/{site-user}/htdocs/{yourdomain.com}
git log -1  # View latest commit

# Check queue workers
sudo supervisorctl status rotational-worker:*

# Check Redis
redis-cli ping

# Check database
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit
```

---

## 🆘 Troubleshooting

### Issue: GitHub Actions Deployment Fails

**Check:**
1. Verify GitHub Secrets are correct
2. Test SSH connection manually:
   ```powershell
   ssh -i cloudpanel-deploy-key your-user@your-server-ip
   ```
3. Check GitHub Actions logs for specific error
4. Verify deploy path exists on server

**Fix:**
```bash
# On server, check permissions
cd /home/{site-user}/htdocs/{yourdomain.com}
ls -la
# Ensure site user owns the files
```

### Issue: Git Pull Fails on Server

**Error:** `error: Your local changes to the following files would be overwritten by merge`

**Fix:**
```bash
# SSH into server
cd /home/{site-user}/htdocs/{yourdomain.com}

# Stash local changes
git stash

# Pull latest
git pull origin main

# If needed, restore local changes
git stash pop
```

### Issue: Composer Install Fails

**Fix:**
```bash
# Clear composer cache
composer clear-cache

# Update composer
composer self-update

# Try again
composer install --optimize-autoloader --no-dev
```

### Issue: Permission Denied

**Fix:**
```bash
# Fix permissions
cd /home/{site-user}/htdocs/{yourdomain.com}
chmod -R 755 storage bootstrap/cache
chown -R {site-user}:clp storage bootstrap/cache
```

### Issue: Queue Workers Not Restarting

**Fix:**
```bash
# Check supervisor status
sudo supervisorctl status

# Restart workers manually
sudo supervisorctl restart rotational-worker:*

# If not configured, set up supervisor
sudo nano /etc/supervisor/conf.d/rotational-worker.conf
# (see CLOUDPANEL_DEPLOYMENT_GUIDE.md for config)
```

---

## 🔒 Security Best Practices

### Protect Sensitive Files

- ✅ `.env` files are in `.gitignore`
- ✅ Private keys never committed to git
- ✅ GitHub Secrets used for credentials
- ✅ Repository is private (recommended)

### SSH Key Security

```powershell
# Keep private key secure
# Never share cloudpanel-deploy-key file
# Only share cloudpanel-deploy-key.pub (public key)

# Optional: Add passphrase to key
ssh-keygen -p -f cloudpanel-deploy-key
```

### Regular Security Updates

```bash
# Update server packages
sudo apt update
sudo apt upgrade -y

# Update composer dependencies
composer update

# Update Laravel
# (follow Laravel upgrade guide)
```

---

## 📊 Deployment Workflow Diagram

```
┌─────────────────┐
│  Local Machine  │
│  (Your PC)      │
└────────┬────────┘
         │
         │ git push
         ▼
┌─────────────────┐
│     GitHub      │
│  (Repository)   │
└────────┬────────┘
         │
         │ GitHub Actions
         │ (Automatic)
         ▼
┌─────────────────┐
│  CloudPanel     │
│  (Live Server)  │
└─────────────────┘
         │
         │ Users access
         ▼
┌─────────────────┐
│  Mobile App &   │
│  Admin Dashboard│
└─────────────────┘
```

---

## 🎯 Common Commands Reference

### Git Commands

```powershell
# Check status
git status

# Stage changes
git add .

# Commit changes
git commit -m "Your message"

# Push to GitHub (triggers deployment)
git push origin main

# Pull latest changes
git pull origin main

# View commit history
git log --oneline -10

# Create new branch
git checkout -b feature/new-feature

# Switch branches
git checkout main
```

### Deployment Commands

```powershell
# Automated deployment
git push origin main

# Manual deployment
.\deploy-from-local.ps1 -ServerHost "IP" -ServerUser "user" -DeployPath "/path"

# Check deployment status
# Go to GitHub → Actions tab
```

### Server Commands

```bash
# SSH into server
ssh your-user@your-server-ip

# Navigate to site
cd /home/{site-user}/htdocs/{yourdomain.com}

# Pull latest code
git pull origin main

# Update dependencies
composer install --optimize-autoloader --no-dev

# Run migrations
php artisan migrate --force

# Clear cache
php artisan cache:clear
php artisan config:clear

# Cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart workers
sudo supervisorctl restart rotational-worker:*

# View logs
tail -f storage/logs/laravel.log
```

---

## 📈 Next Steps

### Immediate Actions

1. ✅ Run setup wizard: `.\setup-github-deployment.ps1`
2. ✅ Push code to GitHub
3. ✅ Configure server with repository
4. ✅ Test deployment workflow
5. ✅ Update mobile app API URL
6. ✅ Test all features on live server

### Ongoing Tasks

- Monitor GitHub Actions for deployment status
- Check server logs regularly
- Keep dependencies updated
- Backup database regularly
- Monitor application performance
- Review security updates

### Future Enhancements

- Set up staging environment
- Add automated testing in CI/CD
- Configure monitoring and alerts
- Set up automated backups
- Add performance monitoring
- Implement blue-green deployment

---

## 📞 Support

### Documentation

- **GitHub Setup**: `GITHUB_DEPLOYMENT_SETUP.md`
- **CloudPanel Deployment**: `CLOUDPANEL_DEPLOYMENT_GUIDE.md`
- **Quick Start**: `CLOUDPANEL_QUICK_START.md`
- **Verification**: `CLOUDPANEL_DEPLOYMENT_VERIFICATION.md`

### Resources

- Laravel Documentation: https://laravel.com/docs
- GitHub Actions: https://docs.github.com/actions
- CloudPanel: https://www.cloudpanel.io/docs
- PostgreSQL: https://www.postgresql.org/docs

---

## ✅ Summary

You now have a complete GitHub-based deployment system:

- ✅ Version control with Git
- ✅ Code hosting on GitHub
- ✅ Automatic deployment via GitHub Actions
- ✅ Manual deployment option available
- ✅ Secure SSH-based deployment
- ✅ Comprehensive documentation
- ✅ Monitoring and logging
- ✅ Rollback capability

**Your workflow:**
```
Make Changes → Commit → Push → Auto Deploy → Live!
```

**Ready to deploy!** 🚀

---

**Last Updated**: March 13, 2026  
**Status**: ✅ READY FOR GITHUB DEPLOYMENT
