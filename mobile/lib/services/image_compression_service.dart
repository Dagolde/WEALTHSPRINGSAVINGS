import 'dart:io';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:path_provider/path_provider.dart';
import 'package:path/path.dart' as path;

class ImageCompressionService {
  /// Compress image file to reduce size for faster uploads
  /// Target size: ~500KB for profile pictures, ~1MB for KYC documents
  static Future<File?> compressImage(
    File file, {
    int quality = 85,
    int maxWidth = 1920,
    int maxHeight = 1920,
  }) async {
    try {
      // Get temporary directory
      final tempDir = await getTemporaryDirectory();
      final targetPath = path.join(
        tempDir.path,
        '${DateTime.now().millisecondsSinceEpoch}_compressed${path.extension(file.path)}',
      );

      // Compress the image
      final result = await FlutterImageCompress.compressAndGetFile(
        file.absolute.path,
        targetPath,
        quality: quality,
        minWidth: maxWidth,
        minHeight: maxHeight,
        format: CompressFormat.jpeg,
      );

      if (result == null) {
        return file; // Return original if compression fails
      }

      // Check if compressed file is smaller
      final originalSize = await file.length();
      final compressedSize = await result.length();

      if (compressedSize < originalSize) {
        return File(result.path);
      } else {
        // If compressed is larger, return original
        return file;
      }
    } catch (e) {
      // If compression fails, return original file
      return file;
    }
  }

  /// Compress profile picture (smaller size, higher compression)
  static Future<File?> compressProfilePicture(File file) async {
    return compressImage(
      file,
      quality: 80,
      maxWidth: 800,
      maxHeight: 800,
    );
  }

  /// Compress KYC document (maintain quality for readability)
  static Future<File?> compressKycDocument(File file) async {
    return compressImage(
      file,
      quality: 85,
      maxWidth: 1920,
      maxHeight: 1920,
    );
  }
}
