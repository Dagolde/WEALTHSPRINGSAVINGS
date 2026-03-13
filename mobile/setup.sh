#!/bin/bash

# Flutter Mobile App Setup Script

echo "🚀 Setting up Ajo App Flutter Mobile Application..."

# Check if Flutter is installed
if ! command -v flutter &> /dev/null
then
    echo "❌ Flutter is not installed. Please install Flutter first."
    echo "Visit: https://flutter.dev/docs/get-started/install"
    exit 1
fi

echo "✅ Flutter is installed"

# Check Flutter version
echo "📱 Flutter version:"
flutter --version

# Get dependencies
echo "📦 Installing dependencies..."
flutter pub get

if [ $? -eq 0 ]; then
    echo "✅ Dependencies installed successfully"
else
    echo "❌ Failed to install dependencies"
    exit 1
fi

# Run code generation for JSON serialization
echo "🔧 Running code generation..."
flutter pub run build_runner build --delete-conflicting-outputs

if [ $? -eq 0 ]; then
    echo "✅ Code generation completed"
else
    echo "⚠️  Code generation failed (this is okay if no models need generation yet)"
fi

# Check for issues
echo "🔍 Checking for issues..."
flutter analyze

echo ""
echo "✅ Setup complete!"
echo ""
echo "📝 Next steps:"
echo "  1. Configure your .env file with API endpoints"
echo "  2. Run the app: flutter run"
echo "  3. For staging: flutter run --dart-define-from-file=.env.staging"
echo "  4. For production: flutter run --dart-define-from-file=.env.production --release"
echo ""
echo "📚 See README.md for more information"
