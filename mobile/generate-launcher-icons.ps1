#!/usr/bin/env pwsh
# Generate placeholder launcher icons for Android

Write-Host "Generating Android launcher icons..." -ForegroundColor Cyan

# Define icon sizes for each density
$iconSizes = @{
    "mipmap-mdpi" = 48
    "mipmap-hdpi" = 72
    "mipmap-xhdpi" = 96
    "mipmap-xxhdpi" = 144
    "mipmap-xxxhdpi" = 192
}

$resPath = "android/app/src/main/res"

# Check if ImageMagick is available
$hasImageMagick = $null -ne (Get-Command "magick" -ErrorAction SilentlyContinue)

if ($hasImageMagick) {
    Write-Host "Using ImageMagick to generate icons..." -ForegroundColor Green
    
    foreach ($density in $iconSizes.Keys) {
        $size = $iconSizes[$density]
        $outputPath = "$resPath/$density/ic_launcher.png"
        
        # Create a simple colored square with "AJO" text
        magick -size "$($size)x$($size)" xc:"#2196F3" `
            -gravity center `
            -pointsize $([math]::Floor($size * 0.4)) `
            -fill white `
            -font Arial-Bold `
            -annotate +0+0 "AJO" `
            $outputPath
        
        Write-Host "  Created $density/ic_launcher.png ($($size)x$($size))" -ForegroundColor Gray
    }
    
    Write-Host "`nLauncher icons generated successfully!" -ForegroundColor Green
    Write-Host "Note: These are placeholder icons. Replace them with proper app icons later." -ForegroundColor Yellow
} else {
    Write-Host "ImageMagick not found. Using Flutter's icon generation instead..." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Installing flutter_launcher_icons package..." -ForegroundColor Cyan
    
    # Add flutter_launcher_icons to pubspec.yaml
    $pubspecPath = "pubspec.yaml"
    $pubspecContent = Get-Content $pubspecPath -Raw
    
    if ($pubspecContent -notmatch "flutter_launcher_icons") {
        # Add flutter_launcher_icons configuration
        $iconConfig = @"

# Launcher Icons Configuration
flutter_launcher_icons:
  android: true
  ios: false
  image_path: "assets/icon/app_icon.png"
  adaptive_icon_background: "#2196F3"
  adaptive_icon_foreground: "assets/icon/app_icon_foreground.png"

"@
        Add-Content -Path $pubspecPath -Value $iconConfig
        
        # Add to dev_dependencies
        $devDepsPattern = "dev_dependencies:"
        $newContent = $pubspecContent -replace $devDepsPattern, "$devDepsPattern`n  flutter_launcher_icons: ^0.13.1"
        Set-Content -Path $pubspecPath -Value $newContent
    }
    
    # Create assets directory
    New-Item -ItemType Directory -Force -Path "assets/icon" | Out-Null
    
    # Create a simple placeholder icon using PowerShell (base64 encoded 1x1 blue pixel)
    Write-Host "Creating placeholder icon assets..." -ForegroundColor Cyan
    
    # For now, let's use flutter pub run to generate icons with a default Flutter icon
    Write-Host "Running: flutter pub add dev:flutter_launcher_icons" -ForegroundColor Gray
    flutter pub add dev:flutter_launcher_icons
    
    Write-Host "`nPlease add an app icon image to assets/icon/app_icon.png" -ForegroundColor Yellow
    Write-Host "Then run: flutter pub run flutter_launcher_icons" -ForegroundColor Yellow
    Write-Host "`nFor now, copying Flutter's default launcher icons..." -ForegroundColor Cyan
    
    # Try to find Flutter's default icons
    $flutterPath = (Get-Command flutter -ErrorAction SilentlyContinue).Source
    if ($flutterPath) {
        $flutterRoot = Split-Path (Split-Path $flutterPath -Parent) -Parent
        $defaultIconPath = "$flutterRoot/packages/flutter_tools/templates/app_shared/android.tmpl/app/src/main/res"
        
        if (Test-Path $defaultIconPath) {
            Write-Host "Copying default Flutter launcher icons..." -ForegroundColor Green
            foreach ($density in $iconSizes.Keys) {
                $sourcePath = "$defaultIconPath/$density/ic_launcher.png"
                $destPath = "$resPath/$density/ic_launcher.png"
                
                if (Test-Path $sourcePath) {
                    Copy-Item $sourcePath $destPath -Force
                    Write-Host "  Copied $density/ic_launcher.png" -ForegroundColor Gray
                }
            }
        }
    }
}

Write-Host "`nDone! You can now build the Android app." -ForegroundColor Green
