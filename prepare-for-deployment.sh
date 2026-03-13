#!/bin/bash

# Preparation Script for CloudPanel Deployment
# Run this on your local machine before uploading to server

set -e

echo "========================================="
echo "Preparing for CloudPanel Deployment"
echo "========================================="
echo ""

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

# Step 1: Check if we're in the project root
if [ ! -d "backend" ] || [ ! -d "admin-dashboard" ]; then
    echo "Error: Run this script from the project root directory"
    exit 1
fi

print_success "Found project directories"

# Step 2: Create deployment package directory
DEPLOY_PKG="deployment-package"
rm -rf $DEPLOY_PKG
mkdir -p $DEPLOY_PKG

print_info "Creating deployment package..."

# Step 3: Copy backend files
print_info "Copying backend files..."
cp -r backend/* $DEPLOY_PKG/
cp backend/.env.cloudpanel $DEPLOY_PKG/.env.cloudpanel
cp backend/.gitignore $DEPLOY_PKG/.gitignore 2>/dev/null || true

# Step 4: Copy admin dashboard
print_info "Copying admin dashboard..."
mkdir -p $DEPLOY_PKG/admin-dashboard-files
cp -r admin-dashboard/* $DEPLOY_PKG/admin-dashboard-files/

# Step 5: Copy deployment scripts
print_info "Copying deployment scripts..."
cp deploy-to-cloudpanel.sh $DEPLOY_PKG/
cp CLOUDPANEL_DEPLOYMENT_GUIDE.md $DEPLOY_PKG/
cp CLOUDPANEL_QUICK_START.md $DEPLOY_PKG/

# Step 6: Remove development files
print_info "Removing development files..."
rm -rf $DEPLOY_PKG/node_modules
rm -rf $DEPLOY_PKG/tests
rm -rf $DEPLOY_PKG/.git
rm -f $DEPLOY_PKG/.env
rm -f $DEPLOY_PKG/.env.example

# Step 7: Create README for deployment package
cat > $DEPLOY_PKG/README.md << 'EOF'
# Rotational Contribution App - Deployment Package

This package contains everything needed to deploy to CloudPanel.

## Quick Start

1. Read `CLOUDPANEL_QUICK_START.md` for step-by-step instructions
2. Upload all files (except admin-dashboard-files folder) to your CloudPanel site directory
3. Upload admin-dashboard-files contents to `public/admin/` directory
4. Follow the deployment guide

## Files Included

- **Backend Application**: All Laravel files
- **Admin Dashboard**: In `admin-dashboard-files/` folder
- **Deployment Scripts**: `deploy-to-cloudpanel.sh`
- **Configuration**: `.env.cloudpanel` (rename to `.env` and configure)
- **Documentation**: 
  - `CLOUDPANEL_QUICK_START.md` - Quick setup guide
  - `CLOUDPANEL_DEPLOYMENT_GUIDE.md` - Detailed deployment guide

## Important Notes

1. **DO NOT** upload the `admin-dashboard-files` folder to root
   - Upload its contents to `public/admin/` instead

2. **MUST** configure `.env` file with your:
   - Database credentials
   - Paystack API keys
   - Admin credentials
   - Domain name

3. **MUST** run these commands after upload:
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan key:generate
   php artisan migrate --force
   php artisan config:cache
   ```

## Support

Refer to the deployment guides for detailed instructions and troubleshooting.
EOF

print_success "Deployment package created"

# Step 8: Create archive
print_info "Creating archive..."
tar -czf rotational-app-deployment.tar.gz $DEPLOY_PKG/
print_success "Archive created: rotational-app-deployment.tar.gz"

# Step 9: Create upload instructions
cat > UPLOAD_INSTRUCTIONS.txt << 'EOF'
========================================
UPLOAD INSTRUCTIONS FOR CLOUDPANEL
========================================

Option 1: Upload Archive (Recommended)
---------------------------------------
1. Upload rotational-app-deployment.tar.gz to your server
2. SSH into server
3. Extract: tar -xzf rotational-app-deployment.tar.gz
4. Move files: mv deployment-package/* /home/{site-user}/htdocs/{yourdomain.com}/
5. Follow CLOUDPANEL_QUICK_START.md

Option 2: Upload via SFTP
--------------------------
1. Use FileZilla or WinSCP
2. Connect to your server
3. Navigate to /home/{site-user}/htdocs/{yourdomain.com}/
4. Upload all files from deployment-package/ folder (except admin-dashboard-files)
5. Create directory: public/admin/
6. Upload contents of admin-dashboard-files/ to public/admin/
7. Follow CLOUDPANEL_QUICK_START.md

Option 3: Git Push
------------------
1. Initialize git in deployment-package/
2. Push to your repository
3. SSH into server
4. Clone repository to site directory
5. Follow CLOUDPANEL_QUICK_START.md

========================================
NEXT STEPS AFTER UPLOAD
========================================

1. SSH into server as site user
2. Navigate to site directory
3. Run: cp .env.cloudpanel .env
4. Edit .env with your configuration
5. Run: composer install --optimize-autoloader --no-dev
6. Run: php artisan key:generate
7. Run: php artisan migrate --force
8. Follow remaining steps in CLOUDPANEL_QUICK_START.md

========================================
EOF

print_success "Upload instructions created: UPLOAD_INSTRUCTIONS.txt"

echo ""
echo "========================================="
echo "Preparation Complete! 🎉"
echo "========================================="
echo ""
print_info "Files created:"
echo "  - deployment-package/ (folder with all files)"
echo "  - rotational-app-deployment.tar.gz (compressed archive)"
echo "  - UPLOAD_INSTRUCTIONS.txt (upload guide)"
echo ""
print_info "Next steps:"
echo "  1. Read UPLOAD_INSTRUCTIONS.txt"
echo "  2. Upload to your CloudPanel server"
echo "  3. Follow CLOUDPANEL_QUICK_START.md"
echo ""
