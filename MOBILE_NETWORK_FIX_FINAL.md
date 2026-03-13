# Mobile Network Connection - Final Fix

## Problem Identified

Your mobile app needs to connect to the backend API, but there are several configuration issues:

1. **Port Configuration**: Mobile app should use port 8002 (nginx) instead of port 8000 (Laravel directly)
2. **Firewall**: Windows Firewall may be blocking ports 8000 and 8002
3. **Network Security**: Android requires cleartext traffic configuration (already configured)
4. **Docker Binding**: Services must bind to all interfaces (0.0.0.0) - already configured

## Why Use Port 8002 (Nginx)?

- **Better Performance**: Nginx provides caching, compression, and load balancing
- **Static File Serving**: Admin dashboard and other static assets served efficiently
- **Rate Limiting**: Built-in protection against API abuse
- **Production-Ready**: Same configuration works in production

## Complete Solution

### Step 1: Run the Automated Fix Script (RECOMMENDED)

**Run as Administrator:**
```powershell
.\fix-mobile-network-final.ps1
```

This script will:
- Detect your IP address automatically
- Configure Windows Firewall rules for ports 8000, 8001, and 8002
- Verify Docker services are running
- Test backend connectivity on both ports
- Update mobile app configuration files automatically
- Provide clear next steps

### Step 2: Manual Configuration (If Needed)

If you prefer manual setup or the script fails:

#### 2.1 Configure Firewall (Run PowerShell as Administrator)

```powershell
# Port 8002 (nginx - primary)
New-NetFirewallRule -DisplayName "Ajo Platform - Nginx (8002)" `
    -Direction Inbound `
    -Protocol TCP `
    -LocalPort 8002 `
    -Action Allow `
    -Profile Any

# Port 8000 (Laravel - backup)
New-NetFirewallRule -DisplayName "Ajo Platform - Laravel (8000)" `
    -Direction Inbound `
    -Protocol TCP `
    -LocalPort 8000 `
    -Action Allow `
    -Profile Any
```

#### 2.2 Verify Docker Services

```powershell
docker-compose ps
```

Expected output:
- `rotational_nginx` on port 8002:80
- `rotational_laravel` on port 8000:8000

If not running:
```powershell
docker-compose up -d
```

#### 2.3 Update Mobile App Configuration

Find your IP address:
```powershell
ipconfig
```

Update `mobile/.env`:
```env
API_BASE_URL=http://YOUR_IP_ADDRESS:8002/api/v1
```

Update `mobile/lib/core/config/app_config.dart`:
```dart
apiBaseUrl = dotenv.env['API_BASE_URL'] ?? 'http://YOUR_IP_ADDRESS:8002/api/v1';
```

### Step 3: Test Backend Accessibility

#### From Your Computer:

Test nginx (port 8002 - preferred):
```powershell
curl http://YOUR_IP_ADDRESS:8002/api/v1/health
```

Test Laravel directly (port 8000 - backup):
```powershell
curl http://YOUR_IP_ADDRESS:8000/api/v1/health
```

#### From Your Phone's Browser:

Open browser and navigate to:
```
http://YOUR_IP_ADDRESS:8002/api/v1/health
```

You should see a JSON response like:
```json
{"status":"healthy","timestamp":"2026-03-12T..."}
```

### Step 4: Rebuild Mobile App

```bash
cd mobile
flutter clean
flutter pub get
flutter run
```

## Quick Test Scripts

### Test Connection (No Admin Required)
```powershell
.\test-mobile-connection.ps1
```

This will:
- Detect your IP address
- Test both nginx (8002) and Laravel (8000)
- Verify mobile app configuration
- Check firewall rules
- Provide detailed diagnostics

### Fix All Issues (Requires Administrator)
```powershell
.\fix-mobile-network-final.ps1
```

This will:
- Configure firewall rules automatically
- Start Docker services if needed
- Update mobile app configuration
- Test connectivity
- Provide step-by-step guidance

## Alternative Solutions

### Option 1: Use ADB Reverse (USB Connection)

If WiFi doesn't work, connect via USB:

```powershell
# Connect phone via USB with USB debugging enabled
adb reverse tcp:8002 tcp:8002
adb reverse tcp:8000 tcp:8000
```

Then update `mobile/.env`:
```env
API_BASE_URL=http://localhost:8002/api/v1
```

Rebuild the app after changing configuration.

### Option 2: Use ngrok (Internet Tunnel)

```powershell
# Install ngrok from https://ngrok.com
ngrok http 8002
```

Copy the HTTPS URL and update `mobile/.env`:
```env
API_BASE_URL=https://your-ngrok-url.ngrok.io/api/v1
```

Note: ngrok provides HTTPS, so no cleartext traffic issues.

### Option 3: Use Android Emulator

```bash
cd mobile
flutter run
```

For emulator, use special IP:
```env
API_BASE_URL=http://10.0.2.2:8002/api/v1
```

## Troubleshooting

### Issue: "Connection refused" or "Connection timeout"

**Possible Causes:**
1. Firewall blocking the ports
2. Docker services not running
3. Wrong IP address
4. Devices on different networks

**Solutions:**
```powershell
# Check firewall rules
Get-NetFirewallRule | Where-Object {$_.DisplayName -like "*Ajo*"}

# Verify Docker services
docker-compose ps

# Check your current IP
ipconfig

# Restart Docker services
docker-compose restart nginx laravel
```

### Issue: "Network unreachable" from phone

**Possible Causes:**
1. Phone on mobile data instead of WiFi
2. Different WiFi networks
3. Router has AP isolation enabled
4. VPN active on either device

**Solutions:**
1. Ensure both devices on same WiFi network
2. Disable VPN on both devices
3. Check router settings for "AP Isolation" or "Client Isolation" (disable it)
4. Try connecting phone to computer's hotspot

### Issue: Phone browser can access URL but app can't

**Possible Causes:**
1. App not rebuilt after configuration change
2. Cached configuration in app
3. Network security config issue

**Solutions:**
```bash
cd mobile
flutter clean
flutter pub get
flutter run
```

### Issue: IP address keeps changing

**Solution:** Set a static IP on your computer
1. Open Network Settings
2. Select your WiFi adapter
3. Set static IP (e.g., 192.168.1.106)
4. Update mobile app configuration with static IP

## Verification Checklist

Before testing the mobile app, verify:

- [ ] Firewall rules created for ports 8000 and 8002 (run fix script as Administrator)
- [ ] Docker services running (`docker-compose ps` shows nginx and laravel)
- [ ] Backend accessible from computer browser (`http://YOUR_IP:8002/api/v1/health`)
- [ ] Backend accessible from phone browser (same URL)
- [ ] Both devices on same WiFi network (check network name on both)
- [ ] Mobile app configuration updated with correct IP and port
- [ ] Mobile app rebuilt after configuration changes (`flutter clean && flutter pub get`)
- [ ] No VPN active on either device
- [ ] Network security config exists in Android app (already configured)
- [ ] AndroidManifest has internet permissions (already configured)

## Success Indicators

When everything works correctly:

1. ✓ Phone browser shows health check response at `http://YOUR_IP:8002/api/v1/health`
2. ✓ Mobile app shows login screen (not connection error)
3. ✓ Registration/login works in mobile app
4. ✓ API calls complete successfully with data displayed
5. ✓ No "Network Error" or "Connection Refused" messages

## Performance Expectations

With nginx (port 8002):
- Health check: < 50ms
- Login/Register: < 500ms
- Data fetching: < 300ms
- Admin dashboard: < 200ms (with caching)

If using Laravel directly (port 8000):
- Slightly slower (no caching)
- Still functional but not optimal

## Need Help?

If still not working after following all steps:

1. Run the test script:
   ```powershell
   .\test-mobile-connection.ps1
   ```

2. Check Docker logs:
   ```powershell
   docker-compose logs nginx laravel
   ```

3. Verify network configuration:
   ```powershell
   ipconfig /all
   ```

4. Try alternative solutions (USB/ADB, ngrok, or emulator)

## Architecture Overview

```
Mobile App (Flutter)
    ↓
WiFi Network (192.168.x.x)
    ↓
Computer (192.168.1.106)
    ↓
Port 8002 → Nginx (Reverse Proxy)
    ↓
Port 8000 → Laravel (API Backend)
    ↓
Port 5432 → PostgreSQL (Database)
```

Nginx provides:
- Load balancing
- Caching (static files)
- Compression (gzip)
- Rate limiting
- Security headers
- Admin dashboard serving
