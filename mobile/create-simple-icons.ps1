#!/usr/bin/env pwsh
# Create minimal valid PNG launcher icons for Android
# This creates simple solid color PNG files without external dependencies

Write-Host "Creating Android launcher icons..." -ForegroundColor Cyan

$iconSizes = @{
    "mipmap-mdpi" = 48
    "mipmap-hdpi" = 72
    "mipmap-xhdpi" = 96
    "mipmap-xxhdpi" = 144
    "mipmap-xxxhdpi" = 192
}

$resPath = "android/app/src/main/res"

# Base64 encoded 1x1 blue PNG pixel
# This is a minimal valid PNG file (67 bytes) - we'll use it as a template
$bluePngBase64 = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="

# Decode base64 to bytes
$pngBytes = [System.Convert]::FromBase64String($bluePngBase64)

Write-Host ""
foreach ($density in $iconSizes.Keys) {
    $size = $iconSizes[$density]
    $dirPath = Join-Path $resPath $density
    $outputPath = Join-Path $dirPath "ic_launcher.png"
    
    # Create directory if it doesn't exist
    if (!(Test-Path $dirPath)) {
        New-Item -ItemType Directory -Force -Path $dirPath | Out-Null
    }
    
    # Write the PNG file
    [System.IO.File]::WriteAllBytes($outputPath, $pngBytes)
    
    Write-Host "  Created $density/ic_launcher.png" -ForegroundColor Gray
}

Write-Host ""
Write-Host "Launcher icons created successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "Note: These are minimal placeholder icons (1x1 pixel)." -ForegroundColor Yellow
Write-Host "The Android build system will scale them as needed." -ForegroundColor Yellow
Write-Host "For production, replace with proper app icons." -ForegroundColor Yellow
Write-Host ""
Write-Host "To create proper icons, you can:" -ForegroundColor Cyan
Write-Host "  1. Use https://icon.kitchen to generate icons from an image" -ForegroundColor Gray
Write-Host "  2. Use flutter_launcher_icons package" -ForegroundColor Gray
Write-Host "  3. Use Android Studio's Image Asset Studio" -ForegroundColor Gray
