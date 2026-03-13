# Mobile App Local Connection Setup

Your mobile app is now configured to connect to your local Docker backend over WiFi.

## Configuration Summary

- **Your Machine IP**: `192.168.1.106`
- **API Base URL**: `http://192.168.1.106:8002/api/v1`
- **Backend Port**: `8002` (Laravel)
- **Network**: Both devices must be on the same WiFi

## Files Updated

1. **mobile/.env** - Updated API_BASE_URL to use your machine's IP
2. **mobile/android/app/src/main/AndroidManifest.xml** - Added network security config
3. **mobile/android/app/src/main/res/xml/network_security_config.xml** - Allows cleartext HTTP traffic

## Setup Steps

### 1. Configure Windows Firewall (Required)

Run this command as Administrator to allow incoming connections:

```powershell
# Right-click PowerShell and select "Run as Administrator"
.\allow-mobile-connection.ps1
```

This will create firewall rules to allow port 8002 (Laravel backend).

### 2. Verify Backend is Accessible

Test the connection from your computer:

```powershell
.\test-mobile-backend-connection.ps1
```

You should see a 422 error (this is good - it means the API is accessible).

### 3. Rebuild the Mobile App

```bash
cd mobile
flutter clean
flutter pub get
flutter run
```

Or use the build script:

```powershell
cd mobile
.\build-android.ps1
```

### 4. Connect Your Mobile Device

- Ensure your mobile device is on the **same WiFi network** as your computer
- The app will automatically connect to `http://192.168.1.106:8002/api/v1`

## Troubleshooting

### Connection Refused or Timeout

1. **Check Windows Firewall**:
   ```powershell
   # Run as Administrator
   .\allow-mobile-connection.ps1
   ```

2. **Verify Docker containers are running**:
   ```powershell
   docker-compose ps
   ```
   
   You should see `ajo_laravel` container running on port 8002.

3. **Test from your computer**:
   ```powershell
   curl http://192.168.1.106:8002/api/v1/auth/login -Method POST
   ```
   
   Expected: 422 error (validation error - this is good!)

### Different WiFi Network

If your IP address changes (different network), update `mobile/.env`:

```env
API_BASE_URL=http://YOUR_NEW_IP:8002/api/v1
```

Find your IP:
```powershell
Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.IPAddress -like "192.168.*"}
```

### Android Network Security Error

If you see "Cleartext HTTP traffic not permitted", the network security config should handle this. If issues persist:

1. Check `mobile/android/app/src/main/AndroidManifest.xml` has:
   ```xml
   android:usesCleartextTraffic="true"
   android:networkSecurityConfig="@xml/network_security_config"
   ```

2. Verify `mobile/android/app/src/main/res/xml/network_security_config.xml` exists

3. Rebuild the app completely:
   ```bash
   flutter clean
   flutter pub get
   flutter run
   ```

## Testing the Connection

Once the app is running on your device:

1. Open the app
2. Try to login or register
3. Check the app logs for connection errors
4. Verify the backend logs:
   ```powershell
   docker logs ajo_laravel -f
   ```

## Port Reference

Your Docker setup uses these ports:

- **8002**: Laravel Backend (API)
- **8003**: FastAPI Microservices
- **5433**: PostgreSQL Database
- **6380**: Redis
- **8082**: Adminer (Database UI)
- **8083**: Redis Commander

The mobile app only needs access to port **8002**.

## Security Note

This setup uses HTTP (not HTTPS) for local development. This is fine for development but should never be used in production. The network security config explicitly allows cleartext traffic only for your local IP address.
