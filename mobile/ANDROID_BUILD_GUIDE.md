# Android Build and Installation Guide

This guide will help you build and install the Ajo Platform mobile app on a real Android device.

## Prerequisites

### 1. Install Flutter SDK
```bash
# Download Flutter SDK from https://flutter.dev/docs/get-started/install
# Or use these commands:

# Windows (PowerShell)
git clone https://github.com/flutter/flutter.git -b stable
# Add Flutter to PATH

# Linux/Mac
git clone https://github.com/flutter/flutter.git -b stable
export PATH="$PATH:`pwd`/flutter/bin"
```

### 2. Install Android Studio
- Download from: https://developer.android.com/studio
- Install Android SDK (API 21 or higher)
- Install Android SDK Command-line Tools
- Install Android SDK Build-Tools
- Install Android Emulator (optional)

### 3. Accept Android Licenses
```bash
flutter doctor --android-licenses
```

### 4. Verify Flutter Installation
```bash
flutter doctor
```

You should see checkmarks for:
- ✓ Flutter
- ✓ Android toolchain
- ✓ Connected device (if device is connected)

## Project Setup

### 1. Navigate to Mobile Directory
```bash
cd mobile
```

### 2. Install Dependencies
```bash
flutter pub get
```

### 3. Configure Environment
Edit `.env` file with your backend API URL:
```env
API_BASE_URL=http://YOUR_BACKEND_IP:8000/api/v1
# For local testing, use your computer's IP address
# Example: API_BASE_URL=http://192.168.1.100:8000/api/v1
```

**Important:** Don't use `localhost` or `127.0.0.1` as these refer to the device itself, not your computer.

## Building the App

### Option 1: Debug Build (Recommended for Testing)

#### Build APK
```bash
flutter build apk --debug
```

The APK will be located at:
`build/app/outputs/flutter-apk/app-debug.apk`

#### Build App Bundle (AAB)
```bash
flutter build appbundle --debug
```

### Option 2: Release Build (For Production)

#### Build APK
```bash
flutter build apk --release
```

The APK will be located at:
`build/app/outputs/flutter-apk/app-release.apk`

#### Build App Bundle (AAB)
```bash
flutter build appbundle --release
```

### Option 3: Build for Specific Architecture

```bash
# For ARM64 devices (most modern phones)
flutter build apk --target-platform android-arm64

# For ARM devices (older phones)
flutter build apk --target-platform android-arm

# For x86 devices (emulators)
flutter build apk --target-platform android-x64
```

## Installing on Device

### Method 1: Direct Install via USB

#### 1. Enable Developer Options on Your Android Device
- Go to Settings → About Phone
- Tap "Build Number" 7 times
- Developer Options will be enabled

#### 2. Enable USB Debugging
- Go to Settings → Developer Options
- Enable "USB Debugging"

#### 3. Connect Device via USB
```bash
# Verify device is connected
flutter devices
# or
adb devices
```

#### 4. Install and Run
```bash
# Install and run directly
flutter run

# Or install the built APK
flutter install
# or
adb install build/app/outputs/flutter-apk/app-debug.apk
```

### Method 2: Install via APK File

#### 1. Transfer APK to Device
- Copy `build/app/outputs/flutter-apk/app-debug.apk` to your device
- Use USB, email, cloud storage, or any file transfer method

#### 2. Install on Device
- Open the APK file on your device
- Tap "Install"
- If prompted, enable "Install from Unknown Sources"

### Method 3: Wireless Debugging (Android 11+)

#### 1. Enable Wireless Debugging
- Go to Settings → Developer Options
- Enable "Wireless Debugging"
- Tap "Wireless Debugging" to see pairing code

#### 2. Pair Device
```bash
adb pair <IP_ADDRESS>:<PORT>
# Enter pairing code when prompted
```

#### 3. Connect
```bash
adb connect <IP_ADDRESS>:<PORT>
```

#### 4. Install
```bash
flutter run
```

## Running the App

### Run in Debug Mode
```bash
flutter run
```

### Run in Release Mode
```bash
flutter run --release
```

### Run with Hot Reload
```bash
flutter run
# Press 'r' to hot reload
# Press 'R' to hot restart
# Press 'q' to quit
```

## Troubleshooting

### Issue: "flutter: command not found"
**Solution:** Add Flutter to your PATH
```bash
# Linux/Mac
export PATH="$PATH:/path/to/flutter/bin"

# Windows
# Add to System Environment Variables
```

### Issue: "No connected devices"
**Solution:**
1. Check USB cable connection
2. Enable USB Debugging on device
3. Accept USB debugging prompt on device
4. Run `adb devices` to verify

### Issue: "Gradle build failed"
**Solution:**
```bash
cd android
./gradlew clean
cd ..
flutter clean
flutter pub get
flutter build apk
```

### Issue: "SDK location not found"
**Solution:** Create `android/local.properties`:
```properties
sdk.dir=/path/to/Android/sdk
flutter.sdk=/path/to/flutter
```

### Issue: "App crashes on startup"
**Solution:**
1. Check backend API is running
2. Verify API_BASE_URL in `.env` file
3. Check device logs:
```bash
flutter logs
# or
adb logcat
```

### Issue: "Cannot connect to backend"
**Solution:**
1. Use your computer's IP address, not localhost
2. Ensure backend is accessible from device network
3. Check firewall settings
4. Test API endpoint in browser: `http://YOUR_IP:8000/api/v1/health`

## Testing Backend Connection

### 1. Start Backend Services
```bash
# In project root
docker-compose up -d

# Or start Laravel backend
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

### 2. Find Your Computer's IP Address
```bash
# Windows
ipconfig

# Linux/Mac
ifconfig
# or
ip addr show
```

### 3. Test API from Device Browser
Open browser on your device and navigate to:
```
http://YOUR_IP:8000/api/v1/health
```

You should see a JSON response.

### 4. Update .env File
```env
API_BASE_URL=http://YOUR_IP:8000/api/v1
```

### 5. Rebuild and Install
```bash
flutter clean
flutter pub get
flutter build apk --debug
flutter install
```

## Building for Production

### 1. Generate Signing Key
```bash
keytool -genkey -v -keystore ~/upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```

### 2. Create key.properties
Create `android/key.properties`:
```properties
storePassword=<password>
keyPassword=<password>
keyAlias=upload
storeFile=<path-to-keystore>
```

### 3. Update app/build.gradle
Add before `android` block:
```gradle
def keystoreProperties = new Properties()
def keystorePropertiesFile = rootProject.file('key.properties')
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(new FileInputStream(keystorePropertiesFile))
}
```

Update `buildTypes`:
```gradle
signingConfigs {
    release {
        keyAlias keystoreProperties['keyAlias']
        keyPassword keystoreProperties['keyPassword']
        storeFile keystoreProperties['storeFile'] ? file(keystoreProperties['storeFile']) : null
        storePassword keystoreProperties['storePassword']
    }
}
buildTypes {
    release {
        signingConfig signingConfigs.release
    }
}
```

### 4. Build Release APK
```bash
flutter build apk --release
```

## App Information

- **Package Name:** com.ajoplatform.rotational_contribution
- **App Name:** Ajo Platform
- **Min SDK:** 21 (Android 5.0)
- **Target SDK:** Latest

## Useful Commands

```bash
# Check Flutter version
flutter --version

# Check connected devices
flutter devices

# Clean build
flutter clean

# Get dependencies
flutter pub get

# Run tests
flutter test

# Analyze code
flutter analyze

# Check for updates
flutter upgrade

# View device logs
flutter logs

# Install APK
adb install path/to/app.apk

# Uninstall app
adb uninstall com.ajoplatform.rotational_contribution

# Clear app data
adb shell pm clear com.ajoplatform.rotational_contribution
```

## Next Steps

1. Build the debug APK
2. Install on your device
3. Start the backend services
4. Configure the API URL
5. Test the app functionality
6. Report any issues

## Support

For issues or questions:
- Check Flutter documentation: https://flutter.dev/docs
- Check Android documentation: https://developer.android.com
- Review app logs: `flutter logs`
- Check backend logs: `docker-compose logs`

---

**Happy Testing! 🚀**
