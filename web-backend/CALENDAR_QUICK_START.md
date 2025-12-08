# Calendar & Appointments System - Quick Start & Testing Guide

## What Was Fixed

### 1. **Backend API Endpoint** (`/api/cashier/calendar/appointments`)
- ✅ Now returns flat array instead of grouped object
- ✅ Includes all required fields with proper defaults
- ✅ Handles NULL payment_status gracefully
- ✅ Includes complete service and user data
- ✅ Supports optional status filtering
- ✅ Returns service price information

### 2. **Database Migrations**
- ✅ New migration: `2025_12_07_add_identification_type_to_appointments.php`
- ✅ New migration: `2025_12_07_backfill_payment_status_defaults.php`
- ✅ Backfills NULL payment_status with defaults
- ✅ Adds identification_type column to model

### 3. **Data Seeder**
- ✅ Updated `TestAppointmentsSeeder.php` to populate:
  - Payment status and amounts
  - Start/end times (calculated from service duration)
  - Identification types
  - Payment dates
  - Service data

### 4. **Frontend Components**
- ✅ Updated `InteractiveCalendar.jsx` to handle 'partial' payment status
- ✅ Updated `CalendarDetailPanel.jsx` with null-safe accessors
- ✅ Better error handling for missing fields
- ✅ Support for multiple payment status formats

### 5. **Data Mapping**
- ✅ `CashierDashboard.jsx` properly converts API responses
- ✅ Handles both array and object responses for backward compatibility
- ✅ Includes all filter checks for partial/partially_paid statuses

## Quick Setup Steps

### Step 1: Run New Migrations
```bash
cd web-backend
php artisan migrate
```

This will:
- Add `identification_type` column to appointments table
- Backfill payment_status defaults for existing appointments

### Step 2: Refresh Seeders (Optional - to reset test data)
```bash
# If you want fresh test data
php artisan db:seed --class=TestAppointmentsSeeder

# Or do a full refresh
php artisan migrate:refresh --seed
```

### Step 3: Verify Data
Check in your database that:
- All appointments have `payment_status` (not NULL)
- All appointments have `start_time` and `end_time`
- All appointments have `identification_type`

### Step 4: Start Services
```bash
# Terminal 1 - Backend
cd web-backend
php artisan serve

# Terminal 2 - Frontend
cd web-frontend
npm run dev
```

### Step 5: Test Calendar
1. Log in as cashier
2. Go to Calendar section
3. You should see appointments displayed on calendar dates
4. Hover over dates to see tooltip details
5. Click dates to see appointment details
6. Click appointments to open payment modal

## Data Structure Verification

### What You Should See in Calendar

**For Approved Appointments:**
- Date highlighted in **amber** with white badge
- Shows appointment count
- Tooltip shows services and times
- Detail panel shows all appointment info
- Payment status shows as "Unpaid"

**For Partially Paid:**
- Date highlighted in **yellow** with white badge
- Same details but payment status shows "Partially Paid"
- Amount paid is displayed

**For Completed (Paid):**
- Date highlighted in **green** with white badge
- Payment status shows "Paid"
- Shows full amount paid

### API Response Format

When you call `/api/cashier/calendar/appointments?month=12&year=2025`, you should get:

```json
{
  "success": true,
  "data": [
    {
      "id": "uuid",
      "appointment_date": "2025-12-15",
      "start_time": "09:00",
      "end_time": "09:30",
      "status": "approved",
      "payment_status": "unpaid",
      "payment_amount": 50.00,
      "amount_paid": 0.00,
      "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com"
      },
      "service": {
        "id": 1,
        "name": "Consultation",
        "price": 50.00
      },
      "identification_type": "Passport",
      "...": "...other fields..."
    }
  ],
  "appointments": [...],
  "count": 25,
  "month": 12,
  "year": 2025
}
```

## Troubleshooting

### Calendar shows no appointments
1. ✅ Check that you ran migrations: `php artisan migrate --status`
2. ✅ Verify appointments exist: `php artisan tinker` then `Appointment::count()`
3. ✅ Check appointments have dates in current month: 
   ```php
   Appointment::where('status', 'approved')
     ->whereBetween('appointment_date', ['2025-12-01', '2025-12-31'])
     ->count()
   ```

### Calendar dates not highlighted
1. ✅ Check `payment_status` is NOT NULL: `Appointment::whereNull('payment_status')->count()`
2. ✅ Run backfill migration if needed: `php artisan migrate --path=database/migrations/2025_12_07_backfill_payment_status_defaults.php`
3. ✅ Verify appointments have status='approved'

### Hover tooltips not showing
1. ✅ Check browser console for errors
2. ✅ Verify appointments have proper `start_time` and `end_time`
3. ✅ Check if dates are within current month view

### Detail panel empty
1. ✅ Make sure you clicked a date with appointments
2. ✅ Check network tab for `/api/cashier/calendar/appointments` response
3. ✅ Verify appointments array is not empty

### Payment status shows incorrectly
1. ✅ Verify field exists: `Schema::hasColumn('appointments', 'payment_status')`
2. ✅ Check your seeder is creating proper payment_status values
3. ✅ Clear browser cache and reload

## Testing Scenarios

### Scenario 1: View Current Month Calendar
1. Open Calendar section
2. Current month appointments should be visible
3. Summary shows correct totals

### Scenario 2: Navigate Months
1. Click Previous button
2. Calendar loads appointments for that month
3. All dates update correctly

### Scenario 3: Filter Appointments
1. Click Filters button
2. Check "Unpaid Appointments"
3. Only unpaid dates should show
4. Uncheck and check "Paid" - different dates show

### Scenario 4: Process Payment
1. Click appointment in detail panel
2. Modal opens with payment form
3. Enter amount and select payment type
4. Click Complete Payment
5. Receipt displays
6. Calendar updates (date color changes to green)

### Scenario 5: View Payment History
1. Switch to month with paid appointments
2. See green highlighted dates
3. Hover shows "completed" status
4. Click appointment shows "Paid" status

## Browser Console Debugging

Open browser Developer Tools > Console and try:

```javascript
// Check if components loaded
window.InteractiveCalendar  // Should exist
window.CalendarDetailPanel  // Should exist

// Check calendar data in React
// (if using React Dev Tools)
// Search for CalendarDetailPanel component
// Props should show: appointments array with all required fields
```

## Database Queries for Verification

```php
// Check all appointments exist
php artisan tinker
> Appointment::count()

// Check payment_status distribution
> Appointment::groupBy('payment_status')->selectRaw('payment_status, count(*) as count')->get()

// Check appointments for current month
> Appointment::whereBetween('appointment_date', ['2025-12-01', '2025-12-31'])->count()

// Check which are approved
> Appointment::where('status', 'approved')->whereBetween('appointment_date', ['2025-12-01', '2025-12-31'])->count()

// Check identification_type is populated
> Appointment::whereNotNull('identification_type')->count()
```

## Next Steps

1. ✅ Run migrations
2. ✅ Restart backend and frontend
3. ✅ Test calendar loading
4. ✅ Verify data displays correctly
5. ✅ Test payment processing workflow
6. ✅ Check all filter types work
7. ✅ Test month navigation

If all tests pass, the calendar system is working correctly!

## Still Having Issues?

1. **Check logs:**
   ```bash
   tail -f web-backend/storage/logs/laravel.log
   tail -f web-frontend/vite.log
   ```

2. **Check network requests:**
   - Open DevTools > Network tab
   - Look for `/api/cashier/calendar/appointments`
   - Check response body and status code

3. **Verify data format:**
   - Appointments should be array (not object)
   - All appointments should have appointment_date in YYYY-MM-DD format
   - All required fields should be present

4. **Clear caches:**
   ```bash
   # Backend
   php artisan cache:clear
   php artisan config:clear
   
   # Frontend
   rm -rf node_modules/.vite
   npm run dev  # Fresh build
   ```

---

**System Version**: 1.0 - Enhanced Calendar System  
**Updated**: December 7, 2025
