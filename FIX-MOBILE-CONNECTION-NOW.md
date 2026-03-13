# Fix Mobile Connection - Quick Guide

## The Problem

Your mobile app shows "Connection timeout" because Windows Firewall is blocking the connection from your phone to your computer.

## The Solution (2 Steps)

### Step 1: Configure Firewall (REQUIRED)

1. **Close this PowerShell window**
2. **Right-click on PowerShell** and select **"Run as Administrator"**
3. **Navigate to the project folder:**
   ```powershell
   cd C:\Users\DEIDUNIT\Desktop\Ajo
   ```
4. **Run the firewall setup script:**
   ```powershell
   .\setup-firewall-admin.ps1
   ```

This will create firewall rules to allow your phone to connect.

### Step 2: Test on Your Phone

1. **Ensure your phone is on the SAME WiFi network** as your computer
2. **Open your phone's browser** (Chrome, Safari, etc.)
3. **Navigate to:**
   ```
   http://192.168.1.106:8002/admin-dashboard/
   ```
4. **You should see the admin dashboard**

If the browser test works, your mobile app will work too!

### Step 3: Rebuild Mobile App

```bash
cd mobile
flutter clean
flutter pub get
flutter run
```

## Why This Happens

- Your computer's IP: `192.168.1.106`
- Backend running on port: `8002` (nginx)
- Windows Firewall blocks external connections by default
- The firewall script creates rules to allow connections from your phone

## Troubleshooting

### If firewall script fails:
Run these commands manually in Administrator PowerShell:

```powershell
New-NetFirewallRule -DisplayName "Ajo Mobile - Nginx (8002)" -Direction Inbound -Protocol TCP -LocalPort 8002 -Action Allow -Profile Any

New-NetFirewallRule -DisplayName "Ajo Mobile - Laravel (8000)" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow -Profile Any
```

### If phone browser still can't connect:
1. Check both devices are on same WiFi (not mobile data)
2. Try disabling VPN on both devices
3. Check router settings for "AP Isolation" (disable it)
4. Try restarting your router

### Alternative: Use USB Connection
If WiFi doesn't work, connect phone via USB:

```powershell
adb reverse tcp:8002 tcp:8002
```

Then update `mobile/.env`:
```
API_BASE_URL=http://localhost:8002/api/v1
```

## Quick Test

After running the firewall script, test from your computer:

```powershell
curl http://192.168.1.106:8002/admin-dashboard/
```

If this works, your phone should be able to connect too!
