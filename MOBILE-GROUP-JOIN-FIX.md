# Mobile Group Join Endpoint Fix

## Issue
Mobile app was unable to join groups using group codes. The error was:
```
POST method is not supported for route api/v1/groups/join. Supported methods: GET, HEAD
```

## Root Cause
- Mobile app sends: `POST /groups/join` with body `{group_code: "BJVWPD21"}`
- Backend had: `POST /groups/{id}/join` (join by group ID)
- Backend was missing: `POST /groups/join` (join by group code)

## Solution
Added the missing route to `backend/routes/api.php`:

```php
Route::post('/join', [GroupController::class, 'joinByCode']); // Join by group code
```

The `joinByCode()` method already existed in `GroupController.php` and handles:
- Finding group by `group_code` from request body
- Validating group is in pending status
- Checking if group is full
- Checking if user is already a member
- Adding user as a group member
- Incrementing current_members count

## Files Modified
1. `backend/routes/api.php` - Added route for joining by group code

## Testing
Backend service restarted to apply route changes:
```bash
docker-compose restart laravel
```

## Mobile App Usage
The mobile app can now join groups using:
```dart
Future<Group> joinGroup(String groupCode) async {
  final response = await _apiClient.dio.post(
    '/groups/join',
    data: {'group_code': groupCode},
  );
  return Group.fromJson(response.data['data']);
}
```

## API Endpoint
- **URL**: `POST /api/v1/groups/join`
- **Auth**: Required (Bearer token)
- **Body**: `{"group_code": "ABC12345"}`
- **Response**: Group object with updated member count

## Next Steps
Test the mobile app to verify users can now join groups using group codes.
