#!/bin/bash
# Generate placeholder launcher icons for Android

echo "Generating Android launcher icons..."

RES_PATH="android/app/src/main/res"

# Check if Python with PIL is available
if command -v python3 &> /dev/null && python3 -c "import PIL" 2>/dev/null; then
    echo "Using Python script to generate icons..."
    python3 create-icons.py
    exit $?
fi

# Check if ImageMagick is available
if command -v convert &> /dev/null; then
    echo "Using ImageMagick to generate icons..."
    
    declare -A SIZES=(
        ["mipmap-mdpi"]=48
        ["mipmap-hdpi"]=72
        ["mipmap-xhdpi"]=96
        ["mipmap-xxhdpi"]=144
        ["mipmap-xxxhdpi"]=192
    )
    
    for density in "${!SIZES[@]}"; do
        size=${SIZES[$density]}
        output_path="$RES_PATH/$density/ic_launcher.png"
        font_size=$((size * 35 / 100))
        
        convert -size "${size}x${size}" xc:"#2196F3" \
            -gravity center \
            -pointsize $font_size \
            -fill white \
            -font Arial-Bold \
            -annotate +0+0 "AJO" \
            "$output_path"
        
        echo "  ✓ Created $density/ic_launcher.png (${size}x${size})"
    done
    
    echo ""
    echo "✓ Launcher icons generated successfully!"
    echo ""
    echo "Note: These are placeholder icons. Replace with proper app icons later."
    exit 0
fi

# Fallback: Try to copy Flutter's default icons
echo "Looking for Flutter's default launcher icons..."

FLUTTER_BIN=$(which flutter)
if [ -n "$FLUTTER_BIN" ]; then
    FLUTTER_ROOT=$(dirname $(dirname "$FLUTTER_BIN"))
    DEFAULT_ICON_PATH="$FLUTTER_ROOT/packages/flutter_tools/templates/app_shared/android.tmpl/app/src/main/res"
    
    if [ -d "$DEFAULT_ICON_PATH" ]; then
        echo "Copying Flutter's default launcher icons..."
        
        for density in mipmap-mdpi mipmap-hdpi mipmap-xhdpi mipmap-xxhdpi mipmap-xxxhdpi; do
            source_path="$DEFAULT_ICON_PATH/$density/ic_launcher.png"
            dest_path="$RES_PATH/$density/ic_launcher.png"
            
            if [ -f "$source_path" ]; then
                cp "$source_path" "$dest_path"
                echo "  ✓ Copied $density/ic_launcher.png"
            fi
        done
        
        echo ""
        echo "✓ Launcher icons copied successfully!"
        exit 0
    fi
fi

echo ""
echo "ERROR: Could not generate launcher icons!"
echo ""
echo "Please install one of the following:"
echo "  1. Python 3 with Pillow: pip install pillow"
echo "  2. ImageMagick: apt-get install imagemagick (Linux) or brew install imagemagick (Mac)"
echo ""
echo "Or manually add launcher icon files to:"
echo "  $RES_PATH/mipmap-*/ic_launcher.png"
exit 1
