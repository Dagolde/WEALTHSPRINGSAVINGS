#!/usr/bin/env python3
"""
Generate simple placeholder launcher icons for Android
Requires: pip install pillow
"""

from PIL import Image, ImageDraw, ImageFont
import os

# Define icon sizes for each density
ICON_SIZES = {
    "mipmap-mdpi": 48,
    "mipmap-hdpi": 72,
    "mipmap-xhdpi": 96,
    "mipmap-xxhdpi": 144,
    "mipmap-xxxhdpi": 192,
}

RES_PATH = "android/app/src/main/res"
BACKGROUND_COLOR = (33, 150, 243)  # Material Blue #2196F3
TEXT_COLOR = (255, 255, 255)  # White

def create_icon(size, output_path):
    """Create a simple icon with 'AJO' text"""
    # Create image with blue background
    img = Image.new('RGB', (size, size), BACKGROUND_COLOR)
    draw = ImageDraw.Draw(img)
    
    # Add text
    text = "AJO"
    font_size = int(size * 0.35)
    
    try:
        # Try to use a nice font
        font = ImageFont.truetype("arial.ttf", font_size)
    except:
        try:
            font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf", font_size)
        except:
            # Fallback to default font
            font = ImageFont.load_default()
    
    # Get text bounding box for centering
    bbox = draw.textbbox((0, 0), text, font=font)
    text_width = bbox[2] - bbox[0]
    text_height = bbox[3] - bbox[1]
    
    # Calculate position to center text
    x = (size - text_width) // 2
    y = (size - text_height) // 2
    
    # Draw text
    draw.text((x, y), text, fill=TEXT_COLOR, font=font)
    
    # Save image
    img.save(output_path, 'PNG')
    print(f"  ✓ Created {os.path.basename(os.path.dirname(output_path))}/ic_launcher.png ({size}x{size})")

def main():
    print("Generating Android launcher icons...\n")
    
    # Check if PIL is available
    try:
        from PIL import Image
    except ImportError:
        print("ERROR: Pillow library not found!")
        print("Please install it with: pip install pillow")
        return 1
    
    # Generate icons for each density
    for density, size in ICON_SIZES.items():
        output_dir = os.path.join(RES_PATH, density)
        os.makedirs(output_dir, exist_ok=True)
        
        output_path = os.path.join(output_dir, "ic_launcher.png")
        create_icon(size, output_path)
    
    print("\n✓ Launcher icons generated successfully!")
    print("\nNote: These are placeholder icons with 'AJO' text.")
    print("Replace them with proper app icons for production.")
    
    return 0

if __name__ == "__main__":
    exit(main())
