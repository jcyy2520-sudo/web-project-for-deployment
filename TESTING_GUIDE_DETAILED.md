# Testing Guide - Slot Time Limit Fixes

## Quick Start Testing

### Scenario 1: Basic Limit Enforcement

1. **Setup Admin Settings**
   - Log in as Admin
   - Go to Admin Dashboard → Appointment Settings
   - Set "Daily Booking Limit Per User" to 2
   - Click "Save Changes"
   - Verify success message appears

2. **Test User Booking**
   - Log out and log in as a regular user
   - Go to "Book Appointment" page
   - Verify message shows: "You have 2 of 2 daily appointment slots available"

3. **Book First Appointment**
   - Select any service (e.g., "Legal Consultation")
   - Select a date at least 1 day in the future
   - Select a time (e.g., "09:00 AM")
   - Click "Schedule Appointment"
   - Verify success message appears

4. **Refresh and Check Limit**
   - Refresh the booking page
   - Verify message now shows: "You have 1 of 2 daily appointment slots available"
   - All form fields should still be enabled

5. **Book Second Appointment**
   - Select different time (e.g., "2:00 PM") for same date
   - Click "Schedule Appointment"
   - Verify appointment is created

6. **Refresh and Verify Limit Reached**
   - Refresh the booking page
   - Verify message shows: "You have reached your daily booking limit of 2 appointments"
   - Message should also show when they can book again
   - All form fields should be DISABLED (grayed out, cannot click)

7. **Verify Cannot Book Third**
   - Try to interact with form fields - they should not respond
   - Try clicking "Schedule Appointment" button - it should show "Daily Limit Reached" and be disabled
   - Try selecting a different date
   - Wait for date to load and verify limit status for that date

---

## Testing "Apply to All Hours"

### Setup
1. Go to Admin Dashboard → Calendar Settings → Time Slot Capacity Management
2. Switch to "Apply to All Hours" tab

### Test Steps
1. Enter capacity value: `5`
2. Click "Apply to All {16} slots" button
3. Verify success message: "All time slots updated to 5 max appointments!"

4. Switch to "Customize Hours" tab
5. Verify ALL hours show capacity: `5`
   - 8 AM: 5
   - 9 AM: 5
   - 10 AM: 5
   - 11 AM: 5
   - 1 PM: 5
   - 2 PM: 5
   - 3 PM: 5
   - 4 PM: 5

6. Verify booking works with this capacity (can book same time with up to 5 different users)

---

## Testing Customize Hours Feature

### Setup
1. In "Customize Hours" tab, ensure you can see all 8 hours

### Test Steps
1. Change 9 AM capacity from 5 to 2
2. Wait for auto-save (700ms) - verify success message appears
3. Change 2 PM capacity from 5 to 1
4. Wait for auto-save - verify success message appears

5. Apply to All Hours with value 5
6. Go back to Customize Hours
7. Verify all hours show 5 again

8. Book appointments and verify capacity limits are respected:
   - At 9 AM: Can book 2 appointments maximum
   - At 2 PM: Can book 1 appointment maximum
   - At other times: Can book 5 appointments maximum

---

## Testing Real-Time Updates

### Setup
- Two devices/windows: one as Admin, one as User
- Or use incognito window for user

### Test Steps
1. **User Window**: Go to "Book Appointment" page
2. **User Window**: Select a date, verify limit shows "You have 3 of 3 daily appointment slots available"
3. **Admin Window**: Go to Appointment Settings
4. **Admin Window**: Change limit to 2
5. **Admin Window**: Click "Save Changes"
6. **User Window**: OBSERVE - Without refreshing, verify message updates to "You have 2 of 2 daily appointment slots available"

If using same window:
- Note the limit value before change
- Admin makes change
- You'll see the update appear automatically without refresh

---

## Testing Limit Reached Display

### Prerequisites
- Set daily limit to 1
- Book 1 appointment for today
- Go back to Book Appointment page

### Verify Display
- Title: "Daily Booking Limit Reached" (in red banner)
- Message includes: "You have reached your daily booking limit of 1 appointments for today"
- Message includes: "You can book again tomorrow (Dec 03)" (or actual date)
- Lists today's appointments:
  - "09:00 - Legal Consultation" (or actual booked appointment)
- The message is a permanent banner (NOT a modal that can be closed)
- Banner stays visible as you interact with the page

### Verify UI Disabling
- Service Type: Cannot click dropdown
- Date: Cannot click calendar picker
- Time: Cannot click time picker
- Notes: Cannot type in textarea
- Submit Button: Says "Daily Limit Reached" and is greyed out
- All fields show 50% opacity

### Verify Disabling Per Date
1. Book 1 appointment for today (limit = 1)
2. Try to book for tomorrow - should NOT be disabled for tomorrow
3. Tomorrow should show "1 of 1 daily appointment slots available"
4. Book 1 for tomorrow
5. For today: disabled
6. For tomorrow: disabled
7. For day after tomorrow: enabled

---

## Testing Multi-User Scenarios

### Setup
- Create 2 test users
- Set daily limit to 2

### Test Steps
1. **User A**: Book 2 appointments for today - should succeed
2. **User A**: Try to book 3rd - should fail (limit reached)
3. **User B**: Book appointment for same time as User A - should succeed
4. **User B**: Book 2nd appointment - should succeed (User B has 2/2)
5. **User B**: Try to book 3rd - should fail (User B limit reached)
6. **User A**: Can still see limit reached message (shows their 2 bookings)
7. **User B**: Can see limit reached message (shows their 2 bookings)

This confirms limit is PER-USER, not global.

---

## Testing Error Cases

### Case 1: Admin Disables Limit
1. Set daily limit to 3
2. Book 3 appointments
3. Try to book 4th - should fail
4. Admin goes to Appointment Settings
5. Admin unchecks "Enable Daily Booking Limit"
6. Admin saves
7. User tries to book 4th - should SUCCEED

### Case 2: Admin Increases Limit
1. Limit is 1, user has 1 booking
2. Limit shows reached
3. Admin changes limit to 3
4. User sees limit updated: "1 of 3 slots available"
5. User can now book 2 more

### Case 3: Cancelled Appointments
1. Limit is 2, user has 2 bookings
2. Limit shows reached
3. User cancels 1 booking
4. Refresh page
5. Limit should show: "1 of 2 slots available"
6. User can book 1 more

---

## Testing Edge Cases

### Case 1: Booking at Day Boundary
1. Set limit to 2
2. Book 2 for today (Dec 2)
3. Select tomorrow (Dec 3) as appointment date
4. Verify message changes: "You have 2 of 2 daily appointment slots available"
5. Can book 2 for tomorrow even though today is full

### Case 2: Weekend/Unavailable Dates
1. Set limit to 1
2. Try to select Saturday or Sunday
3. Dates should be greyed out/disabled (regardless of limit)
4. This is separate from the limit feature

### Case 3: Fast Consecutive Bookings
1. Book appointment 1 - succeeds
2. Immediately (without refresh) book appointment 2
3. Both should create successfully
4. Refresh and verify both exist

---

## API Testing (Optional)

### Test getUserLimit Endpoint
```bash
# Get limit for user 5 for today
curl "http://localhost:8000/api/appointment-settings/user-limit/5"

# Expected Response:
{
  "success": true,
  "data": {
    "limit": 3,
    "used": 1,
    "remaining": 2,
    "has_reached_limit": false,
    "bookings_today": [
      {
        "id": 10,
        "time": "09:00",
        "status": "pending",
        "service": "Legal Consultation"
      }
    ],
    "date": "2024-12-02",
    "next_available_time": null,
    "message": null
  }
}
```

### Test getUserLimit When Limit Reached
```bash
# Get limit for user 5 for a day they've reached limit
curl "http://localhost:8000/api/appointment-settings/user-limit/5/2024-12-02"

# Expected Response:
{
  "success": true,
  "data": {
    "limit": 2,
    "used": 2,
    "remaining": 0,
    "has_reached_limit": true,
    "bookings_today": [
      {
        "id": 10,
        "time": "09:00",
        "status": "pending",
        "service": "Legal Consultation"
      },
      {
        "id": 11,
        "time": "14:00",
        "status": "pending",
        "service": "Document Review"
      }
    ],
    "date": "2024-12-02",
    "next_available_time": "tomorrow (Dec 03)",
    "message": "You have reached your daily booking limit of 2 appointments for today. You can book again tomorrow (Dec 03)."
  }
}
```

### Test Appointment Creation When Limited
```bash
# Try to create appointment when limit reached
curl -X POST "http://localhost:8000/api/appointments" \
  -H "Authorization: Bearer {TOKEN}" \
  -d '{
    "type": "consultation",
    "appointment_date": "2024-12-02",
    "appointment_time": "15:00",
    "service_type": "Legal Consultation"
  }'

# Expected Response (422 error):
{
  "message": "You have reached your daily booking limit of 2 appointments for this day"
}
```

---

## Troubleshooting

### Issue: Limit not updating when date changes
**Solution**: 
- Check browser console for errors
- Verify `checkDailyLimit` is being called
- Verify API endpoint is returning data
- Clear browser cache and try again

### Issue: UI not disabling when limit reached
**Solution**:
- Refresh the page
- Verify `dailyLimitInfo.hasReachedLimit` is true
- Check browser dev tools to see if `disabled` prop is being passed
- Verify no JavaScript errors in console

### Issue: "Apply to All Hours" not updating customize hours
**Solution**:
- Check browser network tab for failed requests
- Verify user is logged in as admin
- Check server logs for errors
- Try manual refresh of page

### Issue: Real-time update not working
**Solution**:
- Verify `appointmentSettingsChanged` event is being dispatched (check console)
- Verify event listener is registered in Dashboard component
- Check if using different browser/window (events don't cross windows)
- Try using same browser window with admin panel and booking page

---

## Performance Notes

- Limit checks happen only when user selects a date (not on every keystroke)
- Event-based updates mean no polling or constant API calls
- Customize hours saves are debounced (700ms) to prevent API spam
- All operations complete in <100ms typically

---

## Summary of Key Files Changed

| File | Changes |
|------|---------|
| `Dashboard.jsx` | Added date param to checkDailyLimit, disable props to form elements, event listeners |
| `TimePicker.jsx` | Added disabled prop |
| `TimeSlotCapacityManagement.jsx` | Fixed handleApplyToAll and loadCapacities |
| `AppointmentSettingsManagement.jsx` | Added event dispatch on settings change |
| `AppointmentSettingsController.php` | Added next_available_time calculation, improved message |
| `AppointmentController.php` | No changes (validation already existed) |

