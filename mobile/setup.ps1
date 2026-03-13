# Flutter Mobile App Setup Script (PowerShell)

Write-Host "🚀 Setting up Ajo App Flutter Mobile Application..." -ForegroundColor Green

# Check if Flutter is installed
$flutterInstalled = Get-Command flutter -ErrorAction SilentlyContinue
if (-not $flutterInstalled) {
    Write-Host "❌ Flutter is not installed. Please install Flutter first." -ForegroundColor Red
    Write-Host "Visit: https://flutter.dev/docs/get-started/install" -ForegroundColor Yellow
    exit 1
}

Write-Host "✅ Flutter is installed" -ForegroundColor Green

# Check Flutter version
Write-Host "📱 Flutter version:" -ForegroundColor Cyan
flutter --version

# Get dependencies
Write-Host "📦 Installing dependencies..." -ForegroundColor Cyan
flutter pub get

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Dependencies installed successfully" -ForegroundColor Green
} else {
    Write-Host "❌ Failed to install dependencies" -ForegroundColor Red
    exit 1
}

# Run code generation for JSON serialization
Write-Host "🔧 Running code generation..." -ForegroundColor Cyan
flutter pub run build_runner build --delete-conflicting-outputs

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Code generation completed" -ForegroundColor Green
} else {
    Write-Host "⚠️  Code generation failed (this is okay if no models need generation yet)" -ForegroundColor Yellow
}

# Check for issues
Write-Host "🔍 Checking for issues..." -ForegroundColor Cyan
flutter analyze

Write-Host ""
Write-Host "✅ Setup complete!" -ForegroundColor Green
Write-Host ""
Write-Host "📝 Next steps:" -ForegroundColor Cyan
Write-Host "  1. Configure your .env file with API endpoints"
Write-Host "  2. Run the app: flutter run"
Write-Host "  3. For staging: flutter run --dart-define-from-file=.env.staging"
Write-Host "  4. For production: flutter run --dart-define-from-file=.env.production --release"
Write-Host ""
Write-Host "📚 See README.md for more information" -ForegroundColor Cyan
