# Mobile Home Screen Pagination Fix

## Issue
Home screen showed "unexpected error" even though groups were loading successfully (200 response).

## Root Cause
Backend returns paginated group data with nested structure:
```json
{
  "success": true,
  "data": {
    "data": [...groups...],
    "current_page": 1,
    "per_page": 15,
    "total": 1
  }
}
```

Mobile app was trying to parse `response.data['data']` as an array, but it's actually a pagination object. The actual groups array is at `response.data['data']['data']`.

## Solution
Updated `mobile/lib/repositories/group_repository.dart` to correctly extract groups from paginated response:

```dart
Future<List<Group>> listGroups({String? status}) async {
  try {
    final queryParams = status != null ? {'status': status} : null;
    final response = await _apiClient.dio.get(
      '/groups',
      queryParameters: queryParams,
    );
    // Backend returns paginated data: {data: {data: [...], current_page: 1, ...}}
    final paginatedData = response.data['data'];
    final List<dynamic> groupsJson = paginatedData['data'];
    return groupsJson.map((json) => Group.fromJson(json)).toList();
  } catch (e) {
    throw _handleError(e);
  }
}
```

## Files Modified
1. `mobile/lib/repositories/group_repository.dart` - Fixed pagination parsing

## Testing
Hot reload the mobile app - the home screen should now load without errors and display groups correctly.

## Related Fixes
This session also fixed:
1. Group join endpoint (POST /groups/join) - added missing route for joining by group code
2. Type casting errors - fixed model classes to handle numeric values from backend
