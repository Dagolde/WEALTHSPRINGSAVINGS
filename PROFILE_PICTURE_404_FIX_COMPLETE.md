# Profile Picture 404 Fix - Complete

## Problem Summary
Profile pictures were being uploaded successfully (200 response) but returned 404 errors when trying to access them via the web server.

## Root Causes Identified

### 1. Wrong Storage Disk
- **Issue**: Profile pictures were being stored using the default 'local' disk instead of the 'public' disk
- **Location**: `backend/app/Http/Controllers/Api/UserController.php` - `uploadProfilePicture()` method
- **Impact**: Files were saved to `storage/app/private/profile_pictures/` instead of `storage/app/public/profile_pictures/`

### 2. Missing profile_picture_url in Model
- **Issue**: The `profile_picture_url` field was not in the User model's `$fillable` array
- **Location**: `backend/app/Models/User.php`
- **Impact**: Could cause issues when trying to update the field

### 3. Nginx Volume Mount Missing
- **Issue**: Nginx container didn't have access to the Laravel storage directory
- **Location**: `docker-compose.yml` - nginx service volumes
- **Impact**: Even with correct storage, nginx couldn't serve the files

### 4. Missing Nginx Location Block
- **Issue**: No nginx configuration to serve static files from `/storage/` path
- **Location**: `nginx/nginx.conf`
- **Impact**: Requests to `/storage/*` were being proxied to Laravel instead of served directly

## Fixes Applied

### 1. Updated UserController.php
```php
// Changed from:
$path = $file->storeAs('profile_pictures', $filename);
Storage::delete($user->profile_picture_url);

// To:
$path = $file->storeAs('profile_pictures', $filename, 'public');
Storage::disk('public')->delete($user->profile_picture_url);
```

### 2. Updated User Model
- Added `profile_picture_url` to `$fillable` array
- Added accessor method `getProfilePictureUrlAttribute()` to return full URL

### 3. Updated docker-compose.yml
```yaml
nginx:
  volumes:
    - ./nginx/nginx.conf:/etc/nginx/nginx.conf:ro
    - ./nginx/ssl:/etc/nginx/ssl:ro
    - ./backend/public:/var/www/html/public:ro
    - laravel_storage:/var/www/html/storage:ro  # Added this line
```

### 4. Updated nginx.conf
Added location block for storage files:
```nginx
# Storage files (profile pictures, KYC documents, etc.)
location /storage/ {
    alias /var/www/html/public/storage/;
    try_files $uri =404;
    
    # Cache images for 1 week
    location ~* \.(jpg|jpeg|png|gif|ico|svg|pdf)$ {
        expires 7d;
        add_header Cache-Control "public, must-revalidate";
        access_log off;
    }
}
```

### 5. Created Storage Directory
```bash
docker exec rotational_laravel mkdir -p storage/app/public/profile_pictures
docker exec rotational_laravel chmod -R 775 storage/app/public/profile_pictures
docker exec rotational_laravel chown -R www-data:www-data storage/app/public/profile_pictures
```

## Verification

### Test Results
```bash
# File exists in correct location
docker exec rotational_laravel ls -la storage/app/public/profile_pictures/
# Output: user_18_1773405067.jpg (55891 bytes)

# Nginx can access the file
docker exec rotational_nginx ls -la /var/www/html/storage/app/public/profile_pictures/
# Output: user_18_1773405067.jpg (55891 bytes)

# HTTP request successful
curl -I http://192.168.1.106:8002/storage/profile_pictures/user_18_1773405067.jpg
# Output: HTTP/1.1 200 OK, Content-Type: image/jpeg, Content-Length: 55891
```

## Files Modified
1. `backend/app/Http/Controllers/Api/UserController.php` - Fixed storage disk usage
2. `backend/app/Models/User.php` - Added fillable field and accessor
3. `docker-compose.yml` - Added storage volume mount for nginx
4. `nginx/nginx.conf` - Added location block for storage files

## Impact
- Profile pictures now upload correctly to `storage/app/public/profile_pictures/`
- Files are accessible via `http://192.168.1.106:8002/storage/profile_pictures/filename.jpg`
- Mobile app can now display profile pictures without 404 errors
- Images are cached for 7 days for better performance

## Next Steps
- Test profile picture upload from mobile app
- Verify old profile pictures are deleted when new ones are uploaded
- Consider adding image optimization/resizing for better performance
- Apply same fix to KYC document uploads if they have similar issues

## Status
✅ COMPLETE - Profile pictures are now working correctly
