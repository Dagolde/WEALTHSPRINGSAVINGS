#!/bin/bash
# Fix Gradle Build Issues
# This script cleans Gradle cache and rebuilds the project

set -e

echo "=========================================="
echo "Fixing Gradle Build Issues"
echo "=========================================="
echo ""

# Step 1: Clean Flutter build
echo "Step 1: Cleaning Flutter build..."
flutter clean
echo "✓ Flutter build cleaned"
echo ""

# Step 2: Clean Gradle cache
echo "Step 2: Cleaning Gradle cache..."
GRADLE_CACHE="$HOME/.gradle"
if [ -d "$GRADLE_CACHE" ]; then
    echo "Removing Gradle cache at: $GRADLE_CACHE"
    
    # Stop any running Gradle daemons
    echo "Stopping Gradle daemons..."
    gradle --stop 2>/dev/null || true
    
    # Wait for processes to release files
    sleep 2
    
    # Remove wrapper dists
    if [ -d "$GRADLE_CACHE/wrapper/dists" ]; then
        rm -rf "$GRADLE_CACHE/wrapper/dists"
        echo "✓ Gradle wrapper cache cleared"
    fi
    
    # Remove caches
    if [ -d "$GRADLE_CACHE/caches" ]; then
        rm -rf "$GRADLE_CACHE/caches"
        echo "✓ Gradle caches cleared"
    fi
else
    echo "✓ No Gradle cache found"
fi
echo ""

# Step 3: Clean Android build directory
echo "Step 3: Cleaning Android build directory..."
if [ -d "android/build" ]; then
    rm -rf android/build
    echo "✓ Android build directory cleaned"
else
    echo "✓ No Android build directory found"
fi
echo ""

# Step 4: Clean app build directory
echo "Step 4: Cleaning app build directory..."
if [ -d "android/app/build" ]; then
    rm -rf android/app/build
    echo "✓ App build directory cleaned"
else
    echo "✓ No app build directory found"
fi
echo ""

# Step 5: Get Flutter dependencies
echo "Step 5: Getting Flutter dependencies..."
flutter pub get
echo "✓ Dependencies fetched"
echo ""

# Step 6: Verify Gradle versions
echo "Step 6: Verifying Gradle configuration..."
if grep -q "gradle-8.7" android/gradle/wrapper/gradle-wrapper.properties; then
    echo "✓ Gradle version: 8.7"
else
    echo "⚠ Gradle version may need update"
fi

if grep -q "gradle:8.3.0" android/build.gradle; then
    echo "✓ Android Gradle Plugin: 8.3.0"
else
    echo "⚠ Android Gradle Plugin may need update"
fi
echo ""

echo "=========================================="
echo "Cleanup Complete!"
echo "=========================================="
echo ""
echo "Next Steps:"
echo "1. Try building again:"
echo "   flutter run"
echo ""
echo "2. If you still get the timeout error:"
echo "   - Close Android Studio if it's open"
echo "   - Run this script again"
echo "   - Restart your computer if needed"
echo ""
echo "3. Alternative: Build without validation:"
echo "   flutter run --android-skip-build-dependency-validation"
echo ""
echo "=========================================="
echo ""
