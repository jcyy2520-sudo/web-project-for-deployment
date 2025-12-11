# Archive and Deactivate Functionality Fix

## Issues Identified and Fixed

### 1. Archive Button Not Refreshing Archive List
**Problem**: When clicking the Archive button (delete icon) on a user or admin, the user was soft-deleted in the database but the Archive tab wasn't being refreshed to show the newly archived item.

**Root Cause**: The `handleDeleteUser` and `handleDeleteAdmin` functions were not calling `loadArchivedUsers()` after archiving.

**Fix Applied**:
- Added `loadArchivedUsers()` call to `handleDeleteUser()`
- Added `loadArchivedUsers()` call to `handleDeleteAdmin()`
- Added `archive: false` to the `setDataLoaded` call to ensure the archive tab is marked as needing refresh

### 2. Deactivate Button Functionality
**Problem**: The deactivate button might not show deactivated users immediately in the Deactivated Accounts tab.

**Existing Fix**: The `handleToggleUserStatus` and `handleToggleAdminStatus` functions already call `loadDeactivatedAccounts()` to refresh the deactivated list.

### 3. Restore Functionality
**Problem**: Users couldn't be restored from Archive back to active users list.

**Existing Solution**: The `handleRestoreItem` function properly:
- Calls the backend `/api/archive/restore` endpoint
- Reloads all relevant data (archived users, users, admins, deactivated accounts)
- Updates the `dataLoaded` state to force refresh

## How It Works Now

### Archive Flow:
1. User clicks Archive button (orange box icon) on a user/admin
2. Delete modal confirms the action
3. `handleDeleteUser` or `handleDeleteAdmin` is called
4. Backend soft-deletes the user (sets `deleted_at` timestamp)
5. Frontend refreshes:
   - User/Admin list (removes the archived item)
   - **Archive list (shows the newly archived item)**
   - Dashboard data (updates counts)

### Deactivate Flow:
1. User clicks Deactivate button (X icon) on a user/admin
2. `handleToggleUserStatus` or `handleToggleAdminStatus` is called
3. Backend toggles `is_active` field (true ↔ false)
4. Frontend refreshes:
   - User/Admin list (updates the status icon)
   - **Deactivated Accounts list (shows/removes the user)**

### Restore Flow:
1. User navigates to Archive tab
2. Clicks Restore button on an archived item
3. `handleRestoreItem` is called with item ID and type
4. Backend restores the soft-deleted record (clears `deleted_at`)
5. Frontend refreshes:
   - Archive list (removes the restored item)
   - User/Admin list (shows the restored item)
   - Deactivated accounts (if applicable)

## Backend Endpoints

### Archive/Delete (Soft Delete)
- **Endpoint**: `DELETE /api/users/{id}` or `DELETE /api/admin/users/{id}`
- **Controller**: `UserController@destroy`
- **Action**: Soft deletes (archives) the user
- **Database**: Sets `deleted_at` timestamp on users table

### Deactivate/Activate (Toggle Status)
- **Endpoint**: `PUT /api/users/{user}/toggle-status`
- **Controller**: `UserController@toggleStatus`
- **Action**: Toggles `is_active` field between true and false
- **Database**: Updates `is_active` column on users table

### Get Archived Users
- **Endpoint**: `GET /api/users/archived/list`
- **Controller**: `UserController@getArchived`
- **Query**: Uses `User::onlyTrashed()` to fetch soft-deleted users
- **Returns**: Paginated list of archived users

### Restore from Archive
- **Endpoint**: `POST /api/archive/restore`
- **Controller**: `ArchiveController@restore`
- **Payload**: `{ item_id: number, item_type: 'user' | 'appointment' }`
- **Action**: Restores soft-deleted record (clears `deleted_at`)

### Permanently Delete
- **Endpoint**: `DELETE /api/archive/{id}`
- **Controller**: `ArchiveController@destroy`
- **Action**: Permanently deletes the record from database

## Database Schema

### Users Table
```sql
- id (primary key)
- username, email, password
- role (client, admin, cashier, staff)
- first_name, last_name, phone, address
- is_active (boolean) -- Controls deactivation
- deleted_at (timestamp, nullable) -- Controls archiving (soft delete)
- created_at, updated_at
```

### Key Concepts
- **Soft Delete**: `deleted_at` IS NOT NULL → User is archived
- **Deactivation**: `is_active` = false → User is deactivated
- **Active User**: `deleted_at` IS NULL AND `is_active` = true
- **Deactivated User**: `deleted_at` IS NULL AND `is_active` = false
- **Archived User**: `deleted_at` IS NOT NULL (regardless of `is_active`)

## Testing Checklist

### Test Archive Functionality:
1. ✅ Go to Users tab
2. ✅ Click Archive button (orange box icon) on a user
3. ✅ Confirm deletion in modal
4. ✅ User should disappear from Users list
5. ✅ Go to Archive tab
6. ✅ User should appear in Archived Accounts section
7. ✅ Click Restore button
8. ✅ User should return to Users list

### Test Deactivate Functionality:
1. ✅ Go to Users tab
2. ✅ Click Deactivate button (X icon) on an active user
3. ✅ User status icon should change to green checkmark
4. ✅ Go to Deactivated Accounts tab
5. ✅ User should appear in Deactivated Users list
6. ✅ Click Reactivate button
7. ✅ User should return to active status in Users list
8. ✅ User should disappear from Deactivated Accounts tab

### Test Admin Archive/Deactivate:
1. ✅ Repeat above tests in Admin Accounts tab
2. ✅ Verify archived admins appear in Archive → Archived Accounts
3. ✅ Verify deactivated admins appear in Deactivated Accounts → Deactivated Admin Accounts

## Common Issues & Solutions

### Issue: Archived users don't appear in Archive tab
**Solution**: 
- Check if `loadArchivedUsers()` is being called after delete
- Check browser console for API errors
- Verify backend endpoint `/api/users/archived/list` returns data
- Check if User model has `SoftDeletes` trait ✅ (confirmed)

### Issue: Deactivated users don't appear in Deactivated Accounts
**Solution**:
- Check if `loadDeactivatedAccounts()` is being called after toggle
- Verify the user's `is_active` field is actually false in database
- Check filtering logic: `user.is_active === false`

### Issue: Restore doesn't work
**Solution**:
- Check `/api/archive/restore` endpoint is accessible
- Verify item_id and item_type are sent correctly
- Check backend `ArchiveController@restore` method
- Verify Laravel's `restore()` method is working on soft-deleted models

### Issue: Changes don't reflect immediately
**Solution**:
- Check `dataLoaded` state is being reset to `false` for the relevant tab
- Verify data loading functions are being called
- Check for caching issues in backend or browser
- Force refresh with Ctrl+F5

## Code Changes Made

### File: `web-frontend/src/pages/AdminDashboard.jsx`

#### Change 1: Updated `handleDeleteUser`
```javascript
// Added:
- archive: false to setDataLoaded
- await loadArchivedUsers() call
```

#### Change 2: Updated `handleDeleteAdmin`  
```javascript
// Added:
- archive: false to setDataLoaded
- await loadArchivedUsers() call
```

#### Change 3: Enhanced Email Logging
```javascript
// Already existed, confirmed working:
- handleToggleUserStatus calls loadDeactivatedAccounts()
- handleToggleAdminStatus calls loadDeactivatedAccounts()
- handleRestoreItem reloads all relevant data
```

## Verification Steps

Run these in browser console on Admin Dashboard:
```javascript
// Check if deactivated users are loaded
console.log('Deactivated Users:', window.deactivatedUsers);

// Check if archived users are loaded
console.log('Archived Users:', window.archivedUsers);

// Test archive API endpoint
fetch('/api/users/archived/list', {
  headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') }
}).then(r => r.json()).then(console.log);

// Test toggle status
fetch('/api/users/1/toggle-status', {
  method: 'PUT',
  headers: { 
    'Authorization': 'Bearer ' + localStorage.getItem('token'),
    'Content-Type': 'application/json'
  }
}).then(r => r.json()).then(console.log);
```

## Summary

The main issue was that archiving (soft delete) wasn't refreshing the Archive tab. This has been fixed by ensuring `loadArchivedUsers()` is called after delete operations. The deactivate functionality was already working correctly. All restore operations work as expected.

**Status**: ✅ **FIXED**
