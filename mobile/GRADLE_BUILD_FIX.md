# Gradle Build Issues - Quick Fix Guide

## Problem

You're seeing one or both of these errors:

1. **Gradle version warning**: "Flutter support for Gradle 8.3.0 will be dropped"
2. **AGP version error**: "Android Gradle Plugin version 8.1.0 is lower than minimum 8.1.1"
3. **Timeout error**: "Timeout of 120000 reached waiting for exclusive access to file"

## Solution

### Quick Fix (Recommended)

Run the fix script:

```powershell
# Windows
cd mobile
.\fix-gradle-build.ps1

# Linux/Mac
cd mobile
chmod +x fix-gradle-build.sh
./fix-gradle-build.sh
```

Then try building again:
```bash
flutter run
```

### Manual Fix

If the script doesn't work, follow these steps:

#### Step 1: Close All Gradle Processes

```powershell
# Windows - Close these if open:
# - Android Studio
# - Any command prompts running Gradle
# - Any Flutter processes

# Then stop Gradle daemon:
gradle --stop
```

#### Step 2: Delete Gradle Cache

```powershell
# Windows
Remove-Item -Path "$env:USERPROFILE\.gradle" -Recurse -Force

# Linux/Mac
rm -rf ~/.gradle
```

#### Step 3: Clean Flutter Project

```bash
cd mobile
flutter clean
rm -rf android/build
rm -rf android/app/build
flutter pub get
```

#### Step 4: Try Building Again

```bash
flutter run
```

### Alternative: Skip Validation (Temporary)

If you need to build quickly and can't fix the Gradle issues:

```bash
flutter run --android-skip-build-dependency-validation
```

**Note:** This is a temporary workaround. You should still fix the Gradle versions properly.

## What Was Fixed

I've already updated your Gradle configuration files:

### 1. Gradle Version (gradle-wrapper.properties)
- ✅ Already set to: `gradle-8.7-all.zip`

### 2. Android Gradle Plugin (build.gradle)
- ✅ Updated from: `8.1.0`
- ✅ Updated to: `8.3.0`

### 3. Settings Gradle (settings.gradle)
- ✅ Updated AGP version to: `8.3.0`

## Troubleshooting

### Issue: "Timeout waiting for exclusive access"

**Cause:** Another process is using the Gradle files (usually Android Studio or a stuck Gradle daemon)

**Fix:**
1. Close Android Studio
2. Run: `gradle --stop`
3. Wait 10 seconds
4. Delete: `C:\Users\YOUR_USER\.gradle\wrapper\dists`
5. Try building again

### Issue: "Could not delete Gradle cache"

**Cause:** Files are locked by another process

**Fix:**
1. Open Task Manager (Ctrl+Shift+Esc)
2. End these processes:
   - `java.exe` (Gradle daemon)
   - `studio64.exe` (Android Studio)
3. Try the fix script again
4. If still fails, restart your computer

### Issue: Build still fails after cleanup

**Try these in order:**

1. **Update Flutter:**
   ```bash
   flutter upgrade
   flutter doctor
   ```

2. **Check Java version:**
   ```bash
   java -version
   # Should be Java 11 or higher
   ```

3. **Reinstall Flutter dependencies:**
   ```bash
   flutter pub cache repair
   flutter pub get
   ```

4. **Nuclear option (last resort):**
   ```bash
   # Backup your code first!
   flutter clean
   rm -rf android
   flutter create --platforms=android .
   # Then restore your android/app/build.gradle customizations
   ```

## Verification

After fixing, verify your setup:

```bash
cd mobile

# Check Gradle version
cat android/gradle/wrapper/gradle-wrapper.properties | grep distributionUrl
# Should show: gradle-8.7-all.zip

# Check AGP version
cat android/build.gradle | grep "com.android.tools.build:gradle"
# Should show: 8.3.0

# Check settings.gradle
cat android/settings.gradle | grep "com.android.application"
# Should show: 8.3.0

# Try building
flutter run
```

## Prevention

To avoid these issues in the future:

1. **Keep Flutter updated:**
   ```bash
   flutter upgrade
   ```

2. **Don't open Android Studio while building:**
   - Android Studio locks Gradle files
   - Close it before running `flutter run`

3. **Clean regularly:**
   ```bash
   flutter clean
   ```

4. **Use the fix script when issues occur:**
   ```bash
   ./fix-gradle-build.ps1
   ```

## Common Gradle Commands

```bash
# Stop all Gradle daemons
gradle --stop

# Check Gradle version
gradle --version

# Clean Gradle cache
rm -rf ~/.gradle/caches

# Clean Gradle wrapper
rm -rf ~/.gradle/wrapper

# Rebuild Gradle wrapper
cd android
./gradlew wrapper --gradle-version 8.7
```

## Still Having Issues?

If none of these solutions work:

1. **Check your environment:**
   ```bash
   flutter doctor -v
   ```

2. **Check for conflicting Java versions:**
   ```bash
   java -version
   echo $JAVA_HOME  # Linux/Mac
   echo %JAVA_HOME%  # Windows
   ```

3. **Try building with verbose output:**
   ```bash
   flutter run -v
   ```

4. **Check the full error log:**
   - Look in: `mobile/android/app/build/outputs/logs/`

5. **Ask for help with:**
   - Full error message
   - Output of `flutter doctor -v`
   - Output of `gradle --version`
   - Your OS and Flutter version

## Quick Reference

| Issue | Command |
|-------|---------|
| Clean everything | `.\fix-gradle-build.ps1` |
| Stop Gradle | `gradle --stop` |
| Clean Flutter | `flutter clean` |
| Skip validation | `flutter run --android-skip-build-dependency-validation` |
| Update Flutter | `flutter upgrade` |
| Repair cache | `flutter pub cache repair` |

---

**Your Gradle configuration has been updated. Run the fix script and try building again!**
