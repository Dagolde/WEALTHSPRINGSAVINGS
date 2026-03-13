#!/bin/bash

# Android Build Script for Ajo Platform Mobile App
# This script helps you build and install the Android app

set -e

echo "======================================"
echo "Ajo Platform - Android Build Script"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Flutter is installed
if ! command -v flutter &> /dev/null; then
    echo -e "${RED}Error: Flutter is not installed or not in PATH${NC}"
    echo "Please install Flutter from https://flutter.dev/docs/get-started/install"
    exit 1
fi

echo -e "${GREEN}✓ Flutter found${NC}"
flutter --version
echo ""

# Check Flutter doctor
echo "Checking Flutter environment..."
flutter doctor
echo ""

# Navigate to mobile directory
cd "$(dirname "$0")"

# Clean previous builds
echo "Cleaning previous builds..."
flutter clean
echo -e "${GREEN}✓ Clean complete${NC}"
echo ""

# Get dependencies
echo "Getting dependencies..."
flutter pub get
echo -e "${GREEN}✓ Dependencies installed${NC}"
echo ""

# Ask user what to build
echo "What would you like to build?"
echo "1) Debug APK (recommended for testing)"
echo "2) Release APK"
echo "3) Debug App Bundle (AAB)"
echo "4) Release App Bundle (AAB)"
echo "5) Install and run on connected device"
read -p "Enter your choice (1-5): " choice

case $choice in
    1)
        echo ""
        echo "Building debug APK..."
        flutter build apk --debug
        echo ""
        echo -e "${GREEN}✓ Build complete!${NC}"
        echo "APK location: build/app/outputs/flutter-apk/app-debug.apk"
        ;;
    2)
        echo ""
        echo "Building release APK..."
        flutter build apk --release
        echo ""
        echo -e "${GREEN}✓ Build complete!${NC}"
        echo "APK location: build/app/outputs/flutter-apk/app-release.apk"
        ;;
    3)
        echo ""
        echo "Building debug App Bundle..."
        flutter build appbundle --debug
        echo ""
        echo -e "${GREEN}✓ Build complete!${NC}"
        echo "AAB location: build/app/outputs/bundle/debug/app-debug.aab"
        ;;
    4)
        echo ""
        echo "Building release App Bundle..."
        flutter build appbundle --release
        echo ""
        echo -e "${GREEN}✓ Build complete!${NC}"
        echo "AAB location: build/app/outputs/bundle/release/app-release.aab"
        ;;
    5)
        echo ""
        echo "Checking for connected devices..."
        flutter devices
        echo ""
        read -p "Do you want to continue with installation? (y/n): " confirm
        if [ "$confirm" = "y" ] || [ "$confirm" = "Y" ]; then
            echo "Installing and running app..."
            flutter run
        else
            echo "Installation cancelled"
        fi
        ;;
    *)
        echo -e "${RED}Invalid choice${NC}"
        exit 1
        ;;
esac

echo ""
echo "======================================"
echo "Build process complete!"
echo "======================================"
echo ""
echo "Next steps:"
echo "1. Transfer the APK to your Android device"
echo "2. Enable 'Install from Unknown Sources' on your device"
echo "3. Install the APK"
echo "4. Make sure backend services are running"
echo "5. Configure API URL in app settings"
echo ""
echo "For more information, see ANDROID_BUILD_GUIDE.md"
