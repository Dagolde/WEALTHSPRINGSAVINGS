# GitHub Secrets Setup Guide

## Issue
Your GitHub Actions deployment is failing with error: `missing server host`

This means the required GitHub Secrets haven't been configured yet.

---

## Required GitHub Secrets

Your workflow needs these 4 secrets to deploy automatically:

1. **SERVER_HOST** - Your CloudPanel server IP or domain
2. **SERVER_USER** - Your CloudPanel site user
3. **SSH_PRIVATE_KEY** - SSH private key for authentication
4. **DEPLOY_PATH** - Full path to your site directory

---

## Step-by-Step Setup

### Step 1: Generate SSH Key (if not done already)

On your local Windows machine, open PowerShell:

```powershell
# Generate SSH key pair
ssh-keygen -t ed25519 -C "deployment@wealthspring" -f cloudpanel-deploy-key

# This creates two files:
# - cloudpanel-deploy-key (PRIVATE KEY - for GitHub Secret)
# - cloudpanel-deploy-key.pub (PUBLIC KEY - for server)
```

### Step 2: Add Public Key to Your Server

SSH into your CloudPanel server:

```bash
# SSH into your server
ssh your-user@your-server-ip

# Add public key to authorized_keys
mkdir -p ~/.ssh
chmod 700 ~/.ssh
nano ~/.ssh/authorized_keys

# Paste the contents of cloudpanel-deploy-key.pub
# Save and exit (Ctrl+X, Y, Enter)

# Set correct permissions
chmod 600 ~/.ssh/authorized_keys
```

To get the public key content on Windows:

```powershell
# Display public key
Get-Content cloudpanel-deploy-key.pub
```

### Step 3: Configure GitHub Secrets

1. Go to your repository: https://github.com/Dagolde/WEALTHSPRINGSAVINGS

2. Click **Settings** (top menu)

3. In left sidebar, click **Secrets and variables** → **Actions**

4. Click **New repository secret** button

5. Add each secret one by one:

---

#### Secret 1: SERVER_HOST

- **Name**: `SERVER_HOST`
- **Value**: Your server IP address or domain
- **Example**: `123.45.67.89` or `server.yourdomain.com`

Click **Add secret**

---

#### Secret 2: SERVER_USER

- **Name**: `SERVER_USER`
- **Value**: Your CloudPanel site user
- **Example**: `clpuser-abc123` or your actual site user

To find your site user on CloudPanel:
- Log into CloudPanel
- Go to Sites
- Click on your site
- Look for "Site User" field

Click **Add secret**

---

#### Secret 3: SSH_PRIVATE_KEY

- **Name**: `SSH_PRIVATE_KEY`
- **Value**: Contents of `cloudpanel-deploy-key` file (ENTIRE FILE)

To get the private key content on Windows:

```powershell
# Display private key
Get-Content cloudpanel-deploy-key
```

Copy the ENTIRE output including:
```
-----BEGIN OPENSSH PRIVATE KEY-----
... (all the content) ...
-----END OPENSSH PRIVATE KEY-----
```

**IMPORTANT**: 
- Copy the ENTIRE key including the BEGIN and END lines
- Do NOT share this key with anyone
- Do NOT commit this key to git

Click **Add secret**

---

#### Secret 4: DEPLOY_PATH

- **Name**: `DEPLOY_PATH`
- **Value**: Full path to your site directory
- **Example**: `/home/clpuser-abc123/htdocs/yourdomain.com`

To find your deploy path:
- It's usually: `/home/{site-user}/htdocs/{yourdomain.com}`
- Replace `{site-user}` with your actual site user
- Replace `{yourdomain.com}` with your actual domain

Click **Add secret**

---

### Step 4: Verify Secrets Are Set

After adding all 4 secrets, you should see them listed:

- ✅ SERVER_HOST
- ✅ SERVER_USER  
- ✅ SSH_PRIVATE_KEY
- ✅ DEPLOY_PATH

**Note**: You won't be able to view the secret values after saving (for security), but you can update them if needed.

---

### Step 5: Test SSH Connection (Optional but Recommended)

Before triggering deployment, test SSH connection from your local machine:

```powershell
# Test SSH connection with the private key
ssh -i cloudpanel-deploy-key your-user@your-server-ip

# If successful, you should be logged into your server
# Type 'exit' to disconnect
```

If this fails, the GitHub Actions deployment will also fail.

---

### Step 6: Trigger Deployment

Once all secrets are configured, you can trigger deployment in two ways:

#### Option A: Push to GitHub (Automatic)

```powershell
# Make any small change (or just trigger workflow)
git commit --allow-empty -m "Test deployment with GitHub Actions"
git push origin main
```

#### Option B: Manual Trigger

1. Go to your repository: https://github.com/Dagolde/WEALTHSPRINGSAVINGS
2. Click **Actions** tab
3. Click **Deploy to CloudPanel** workflow (left sidebar)
4. Click **Run workflow** button (right side)
5. Select branch: `main`
6. Click **Run workflow**

---

### Step 7: Monitor Deployment

1. Go to **Actions** tab on GitHub
2. Click on the running workflow
3. Watch the deployment progress in real-time
4. Check for any errors

**Successful deployment** will show:
- ✅ Green checkmark
- "Deployment completed successfully!" message

**Failed deployment** will show:
- ❌ Red X
- Error details in the logs

---

## Troubleshooting

### Error: "Permission denied (publickey)"

**Cause**: Public key not added to server or wrong user

**Fix**:
1. Verify public key is in `~/.ssh/authorized_keys` on server
2. Check permissions: `chmod 600 ~/.ssh/authorized_keys`
3. Verify you're using the correct SERVER_USER

### Error: "Host key verification failed"

**Cause**: Server not in known_hosts

**Fix**: Add this to your workflow (already included in our workflow):
```yaml
script_stop: false
```

### Error: "cd: no such file or directory"

**Cause**: DEPLOY_PATH is incorrect

**Fix**:
1. SSH into your server
2. Run: `pwd` in your site directory
3. Update DEPLOY_PATH secret with correct path

### Error: "composer: command not found"

**Cause**: Composer not installed or not in PATH

**Fix**: SSH into server and install composer:
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Error: "php artisan: command not found"

**Cause**: PHP not in PATH or wrong directory

**Fix**: 
1. Verify DEPLOY_PATH is correct
2. Check PHP is installed: `php -v`

---

## Quick Reference

### GitHub Secrets URL
https://github.com/Dagolde/WEALTHSPRINGSAVINGS/settings/secrets/actions

### GitHub Actions URL
https://github.com/Dagolde/WEALTHSPRINGSAVINGS/actions

### Required Secrets Checklist

- [ ] SERVER_HOST - Server IP or domain
- [ ] SERVER_USER - CloudPanel site user
- [ ] SSH_PRIVATE_KEY - Full private key content
- [ ] DEPLOY_PATH - Full path to site directory

---

## Next Steps After Setup

1. ✅ Configure all 4 GitHub Secrets
2. ✅ Test SSH connection manually
3. ✅ Trigger deployment (push or manual)
4. ✅ Monitor deployment in Actions tab
5. ✅ Verify site is updated on server
6. ✅ Check application logs for errors

---

## Security Best Practices

- ✅ Keep private key secure (never commit to git)
- ✅ Use private GitHub repository (recommended)
- ✅ Rotate SSH keys periodically
- ✅ Use strong passwords for server access
- ✅ Enable 2FA on GitHub account
- ✅ Limit SSH access to specific IPs (optional)
- ✅ Monitor deployment logs regularly

---

**Last Updated**: March 13, 2026  
**Status**: Ready for configuration

**Need help?** Check the troubleshooting section above or review the full deployment guide in `GITHUB_DEPLOYMENT_SETUP.md`
