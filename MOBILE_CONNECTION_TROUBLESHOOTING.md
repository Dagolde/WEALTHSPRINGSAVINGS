# Mobile App Connection Troubleshooting

Your mobile app is showing "Connection timeout" when trying to register. This means the app cannot reach your backend server.

## Current Configuration

- **Backend URL**: `http://192.168.1.106:8002/api/v1`
- **Your Machine IP**: `192.168.1.106`
- **Backend Port**: `8002`

## Quick Fixes (Try These First)

### 1. Configure Windows Firewall

Run this as Administrator:

```powershell
# Right-click PowerShell and select "Run as Administrator"
.\allow-mobile-connection.ps1
```

Or manually add firewall rule:

```powershell
New-NetFirewallRule -DisplayName "Ajo Platform - Mobile Backend" `
    -Direction Inbound `
    -Protocol TCP `
    -LocalPort 8002 `
    -Action Allow `
    -Profile Private,Domain
```

### 2. Verify Backend is Running

```powershell
docker-compose ps
```

You should see `ajo_laravel` running on port 8002.

### 3. Test from Your Computer

```powershell
curl http://192.168.1.106:8002/api/v1/auth/login -Method POST
```

Expected: 422 error (this is good - means API is accessible)

### 4. Test from Your Phone's Browser

Open your phone's web browser and go to:

```
http://192.168.1.106:8002/api/v1/auth/login
```

You should see a JSON response (even if it's an error).

## Detailed Troubleshooting

### Check 1: Same WiFi Network

**Verify both devices are on the same network:**

On your computer:
```powershell
ipconfig
```

Look for "Wireless LAN adapter Wi-Fi" and note the network name.

On your phone:
- Go to Settings → WiFi
- Verify you're connected to the SAME network

### Check 2: IP Address Hasn't Changed

Your IP might have changed. Get current IP:

```powershell
Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -like "192.168.*"}
```

If different from `192.168.1.106`, update `mobile/.env`:

```env
API_BASE_URL=http://YOUR_NEW_IP:8002/api/v1
```

Then rebuild the app:
```bash
cd mobile
flutter clean
flutter pub get
flutter run
```

### Check 3: Windows Firewall Rules

List current firewall rules:

```powershell
Get-NetFirewallRule | Where-Object {$_.DisplayName -like "*Ajo*"}
```

If no rules exist, create them:

```powershell
# Run as Administrator
New-NetFirewallRule -DisplayName "Ajo Platform - Port 8002" `
    -Direction Inbound `
    -Protocol TCP `
    -LocalPort 8002 `
    -Action Allow `
    -Profile Any
```

### Check 4: Docker Port Binding

Verify Docker is binding to all interfaces (0.0.0.0):

```powershell
docker port ajo_laravel
```

Should show: `8000/tcp -> 0.0.0.0:8002`

If it shows `127.0.0.1:8002`, update `docker-compose.yml`:

```yaml
laravel:
  ports:
    - "0.0.0.0:8002:8000"  # Bind to all interfaces
```

Then restart:
```powershell
docker-compose restart laravel
```

### Check 5: Network Security Config

Verify `mobile/android/app/src/main/res/xml/network_security_config.xml` exists and contains:

```xml
<?xml version="1.0" encoding="utf-8"?>
<network-security-config>
    <domain-config cleartextTrafficPermitted="true">
        <domain includeSubdomains="true">192.168.1.106</domain>
        <domain includeSubdomains="true">10.0.2.2</domain>
        <domain includeSubdomains="true">localhost</domain>
    </domain-config>
</network-security-config>
```

### Check 6: AndroidManifest Permissions

Verify `mobile/android/app/src/main/AndroidManifest.xml` has:

```xml
<uses-permission android:name="android.permission.INTERNET"/>
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE"/>

<application
    android:usesCleartextTraffic="true"
    android:networkSecurityConfig="@xml/network_security_config">
```

## Alternative: Use USB Debugging with Port Forwarding

If WiFi connection doesn't work, use ADB port forwarding:

1. Connect phone via USB
2. Enable USB debugging on phone
3. Run:
   ```powershell
   adb reverse tcp:8002 tcp:8002
   ```
4. Update `mobile/.env`:
   ```env
   API_BASE_URL=http://localhost:8002/api/v1
   ```
5. Rebuild and run the app

## Test Connection from Phone

### Method 1: Use cURL (if available on phone)

Install Termux app on Android, then:

```bash
curl -v http://192.168.1.106:8002/api/v1/auth/login
```

### Method 2: Use Browser

Open Chrome on your phone and visit:

```
http://192.168.1.106:8002/api/v1/auth/login
```

You should see JSON response.

### Method 3: Use Network Analyzer App

Install "Fing" or "Network Analyzer" from Play Store:
1. Scan your network
2. Find your computer (192.168.1.106)
3. Try to ping it
4. Check if port 8002 is open

## Common Issues and Solutions

### Issue: "Connection refused"

**Cause**: Firewall is blocking the connection

**Solution**: 
```powershell
# Run as Administrator
New-NetFirewallRule -DisplayName "Ajo Platform" `
    -Direction Inbound -Protocol TCP -LocalPort 8002 -Action Allow -Profile Any
```

### Issue: "Network unreachable"

**Cause**: Devices on different networks

**Solution**: Connect both to the same WiFi network

### Issue: "Connection timeout"

**Cause**: Backend not responding or wrong IP

**Solution**:
1. Verify backend is running: `docker-compose ps`
2. Check IP hasn't changed: `ipconfig`
3. Test from computer: `curl http://192.168.1.106:8002/api/v1/auth/login`

### Issue: "SSL/TLS error" or "Certificate error"

**Cause**: Using HTTPS instead of HTTP

**Solution**: Verify `.env` uses `http://` not `https://`

## Still Not Working?

### Option 1: Use ngrok (Temporary Solution)

1. Install ngrok: https://ngrok.com/download
2. Run:
   ```powershell
   ngrok http 8002
   ```
3. Copy the HTTPS URL (e.g., `https://abc123.ngrok.io`)
4. Update `mobile/.env`:
   ```env
   API_BASE_URL=https://abc123.ngrok.io/api/v1
   ```
5. Rebuild the app

### Option 2: Use Emulator Instead

Run the app on Android Emulator:

```bash
cd mobile
flutter run
```

The emulator can access `http://10.0.2.2:8002` which maps to your localhost.

## Verification Checklist

- [ ] Backend is running (`docker-compose ps`)
- [ ] Firewall rule created (run `allow-mobile-connection.ps1` as Admin)
- [ ] Both devices on same WiFi
- [ ] IP address is correct in `mobile/.env`
- [ ] Can access backend from computer browser
- [ ] Can access backend from phone browser
- [ ] App has been rebuilt after `.env` changes
- [ ] Network security config exists
- [ ] AndroidManifest has correct permissions

## Get Help

If still not working, provide:
1. Output of `ipconfig`
2. Output of `docker-compose ps`
3. Output of `curl http://192.168.1.106:8002/api/v1/auth/login`
4. Screenshot of phone's WiFi settings
5. Full error message from Flutter app
