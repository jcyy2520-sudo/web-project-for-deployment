# Calendar System - Data Flow & Architecture

## Complete Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      CASHIER DASHBOARD                          │
│                                                                  │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
│  │ Calendar Section │  │ Appointments     │  │ Other Sections   │
│  │ (NEW ENHANCED)   │  │ Section          │  │ Dashboard, etc.  │
│  └────────┬─────────┘  └────────┬─────────┘  └──────────────────┘
│           │                     │
│           └─────────┬───────────┘
│                     │
└──────────────────────┼──────────────────────────────────────────┘
                       │
                       │ (HTTP Requests)
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                    BACKEND (Laravel)                            │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │         CashierController                               │   │
│  │                                                         │   │
│  │  getCalendarAppointments()  ◄─── /api/cashier/calendar/│   │
│  │  ├─ Query by month/year                                │   │
│  │  ├─ Filter by status (default: approved)              │   │
│  │  ├─ Load relations (user, service)                    │   │
│  │  └─ Map to complete DTO with all fields               │   │
│  │                                                         │   │
│  └──────────────────┬──────────────────────────────────────┘   │
│                     │                                           │
│                     ▼                                           │
│  ┌──────────────────────────────────────────────────────┐      │
│  │     Appointment Model + Database                     │      │
│  │                                                      │      │
│  │  Columns (after migrations):                        │      │
│  │  ├─ id, user_id, service_id                        │      │
│  │  ├─ appointment_date (Y-m-d)                        │      │
│  │  ├─ appointment_time (H:i)                          │      │
│  │  ├─ start_time (H:i:s) ◄─── NEW                     │      │
│  │  ├─ end_time (H:i:s) ◄─── NEW                       │      │
│  │  ├─ status                                          │      │
│  │  ├─ payment_status ◄─── BACKFILLED                  │      │
│  │  ├─ payment_amount                                  │      │
│  │  ├─ identification_type ◄─── NEW                    │      │
│  │  ├─ discount_amount                                 │      │
│  │  └─ ... other fields                                │      │
│  │                                                      │      │
│  └──────────────────────────────────────────────────────┘      │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
                       │
                       │ JSON Response
                       │ [Array of Appointments]
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                    FRONTEND (React)                             │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ CashierDashboard.jsx                                    │   │
│  │                                                         │   │
│  │  State:                                                 │   │
│  │  ├─ currentMonth (Date)                                │   │
│  │  ├─ selectedDate (1-31)                                │   │
│  │  ├─ calendarAppointments (Array)                       │   │
│  │  ├─ calendarFilters (Object)                           │   │
│  │  ├─ calendarLoading (Boolean)                          │   │
│  │  └─ monthSummary (Object)                              │   │
│  │                                                         │   │
│  │  Computed:                                              │   │
│  │  ├─ filteredCalendarAppts (useMemo)                    │   │
│  │  │  └─ Applies active filters                          │   │
│  │  └─ renderCalendar() returns:                          │   │
│  │                                                         │   │
│  └────────────────┬──────────────────────────────┬─────────┘   │
│                   │                              │              │
│         ┌─────────▼─────────┐        ┌──────────▼────────────┐ │
│         │InteractiveCalendar│        │CalendarDetailPanel   │ │
│         │                   │        │                      │ │
│         │ Props:            │        │ Props:               │ │
│         │├─appointments     │        │├─selectedDate        │ │
│         │├─selectedDate     │        │├─appointments        │ │
│         │├─currentMonth     │        │├─monthNames          │ │
│         │├─filters          │        │└─onAppointmentClick  │ │
│         │├─monthSummary     │        │                      │ │
│         │└─onDateSelect()   │        │ Shows:               │ │
│         │                   │        │├─Detail cards        │ │
│         │ Renders:          │        │├─Payment status      │ │
│         │├─Month grid       │        │├─Client info         │ │
│         │├─Date cells       │        │├─Service/time        │ │
│         │├─Color badges     │        │├─Price & ID type     │ │
│         │├─Hover tooltips   │        │└─Quick action hints  │ │
│         │├─Filter controls  │        │                      │ │
│         │└─Summary panel    │        └────────┬─────────────┘ │
│         └─────────────────────────────────────┼────────────────┘
│                   │                           │                 │
│                   └──────────────┬────────────┘                 │
│                                  │                              │
│                                  ▼                              │
│                    ┌──────────────────────────┐                │
│                    │  AppointmentModal        │                │
│                    │  (Reused from section)   │                │
│                    │                          │                │
│                    │ Features:                │                │
│                    │├─Full payment form       │                │
│                    │├─Discount selection      │                │
│                    │├─Receipt generation      │                │
│                    │└─Status updates          │                │
│                    └──────────┬───────────────┘                │
│                               │                                 │
│                    ┌──────────▼────────────┐                   │
│                    │  Updated calendarAppts│                   │
│                    │  + Summary Recalc     │                   │
│                    └───────────────────────┘                   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Request/Response Flow

### Request Flow: Loading Calendar

```
User: Switches to Calendar section
  │
  └──▶ activeSection = 'calendar'
       │
       └──▶ useEffect(activeSection)
            │
            └──▶ loadCalendarAppointments(month, year)
                 │
                 └──▶ GET /api/cashier/calendar/appointments
                      ?month=12&year=2025
                      │
                      ├──▶ Request Query
                      │     ├─ Start date: 2025-12-01
                      │     └─ End date: 2025-12-31
                      │
                      ├──▶ Database Query
                      │     ├─ WHERE appointment_date BETWEEN...
                      │     ├─ WHERE status = 'approved'
                      │     └─ WITH relationships (user, service)
                      │
                      └──▶ Map to Response (Complete object)
                           ├─ All appointment fields
                           ├─ Complete user data
                           ├─ Service with price
                           └─ Default values for NULL fields
```

### Response Format (After Fix)

```
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "user_id": 1,
      "staff_id": null,
      "appointment_date": "2025-12-15",
      "start_time": "09:00",                        ◄─── NOW POPULATED
      "end_time": "09:30",                          ◄─── NOW POPULATED
      "status": "approved",
      "payment_status": "unpaid",                   ◄─── NEVER NULL
      "payment_amount": 50.00,
      "amount_paid": 0.00,
      "discount_amount": 0,
      "discount_type": null,
      "identification_type": "Passport",            ◄─── NEW FIELD
      "form_of_id": "Passport",
      "type": "in-person",
      "service_type": "consultation",
      "purpose": "Regular appointment",
      "notes": "Appointment for John Doe",
      "payment_notes": null,
      "user": {                                      ◄─── COMPLETE
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com"
      },
      "service": {                                   ◄─── INCLUDES PRICE
        "id": 1,
        "name": "Consultation",
        "price": 50.00
      },
      "completed_at": null,
      "completed_by": null,
      "payment_date": null,
      "processed_by": null,
      "outcome_status": null
    },
    { ... more appointments ... }
  ],
  "appointments": [ ... ],                          ◄─── BACKWARD COMPAT
  "count": 25,
  "month": 12,
  "year": 2025
}
```

## Frontend Processing

```
Response arrives
  │
  ├──▶ Is Array? ✓
  │     └─ Convert to flat array
  │
  ├──▶ setCalendarAppointments(flatAppts)
  │
  ├──▶ Calculate monthSummary
  │     ├─ totalApproved: filter(status='approved').length
  │     ├─ totalCompleted: filter(payment_status='paid').length
  │     ├─ expectedRevenue: sum(service.price) for approved
  │     └─ actualRevenue: sum(amount_paid) for paid
  │
  ├──▶ useMemo: filteredCalendarAppts
  │     └─ Apply active filters
  │          ├─ approved: status='approved'
  │          ├─ completed: payment_status='paid'
  │          ├─ unpaid: payment_status='unpaid'
  │          ├─ partiallyPaid: payment_status='partial'
  │          └─ pending: status='pending'
  │
  └──▶ render()
       ├─ <InteractiveCalendar
       │  ├─ Group appointments by date
       │  ├─ Render month grid
       │  ├─ Color code dates
       │  ├─ Show badges
       │  ├─ Hover tooltips
       │  └─ Filter controls
       │
       └─ <CalendarDetailPanel
          ├─ Show selected date
          ├─ List appointments for date
          ├─ Full details cards
          └─ Click to open modal
```

## Data Transformation Pipeline

```
Raw Appointment (from database)
  ↓
  ├─ Check: Is payment_status NULL?
  │  └─ Convert to 'unpaid'
  ├─ Check: Is start_time NULL?
  │  └─ Copy from appointment_time
  ├─ Check: Is end_time NULL?
  │  └─ Calculate from start_time + service.duration
  ├─ Check: Is amount_paid missing?
  │  └─ Set from payment_amount
  └─ Check: Relations loaded?
     ├─ Include complete user object
     └─ Include complete service object
  ↓
Complete Appointment DTO
  ↓
Frontend receives
  ↓
Apply Filters
  │
  ├─ If no filters → show approved only
  ├─ If filters → apply combined OR logic
  ├─ Check all status variants (partial, partially_paid)
  └─ Return filtered array
  ↓
Render Calendar
  │
  ├─ Group by appointment_date (YYYY-MM-DD format)
  ├─ Color code by payment_status
  ├─ Show appointment counts
  ├─ Generate tooltips
  └─ Display summary
  ↓
User interacts
  │
  ├─ Hover → Tooltip appears
  ├─ Click date → Detail panel updates
  ├─ Click appointment → Modal opens
  ├─ Submit payment → API call
  │  ├─ POST /api/cashier/appointments/{id}/process-payment
  │  └─ Response includes receipt data
  ├─ Receipt → Print/Download
  └─ Sync → Calendar updates immediately
```

## Error Handling Flow

```
Try to load calendar
  │
  ├─ setCalendarLoading(true)
  │
  ├─ GET /api/cashier/calendar/appointments
  │  │
  │  ├─ Success? ✓
  │  │  ├─ Data is array? ✓
  │  │  │  └─ setCalendarAppointments(data)
  │  │  └─ Data is object?
  │  │     └─ Convert to array first
  │  │
  │  └─ Error ✗
  │     ├─ console.error(err)
  │     └─ setCalendarLoading(false)
  │
  ├─ Finally: setCalendarLoading(false)
  │
  └─ Render
     ├─ isLoading? Show spinner
     ├─ No appointments? Show empty message
     └─ Has data? Show calendar
```

## Component Hierarchy

```
CashierDashboard (Main Component)
│
├─ State Management (6 state vars + 1 useMemo computed)
│  ├─ currentMonth, selectedDate
│  ├─ calendarAppointments, calendarFilters
│  ├─ calendarLoading, monthSummary
│  └─ filteredCalendarAppts (useMemo)
│
├─ Hooks
│  ├─ useEffect(activeSection) - Load on section change
│  ├─ useEffect(currentMonth) - Load on month change
│  └─ useCallback(loadCalendarAppointments) - API call
│
├─ Render Methods
│  └─ renderCalendar() returns:
│     │
│     ├─ InteractiveCalendar
│     │  ├─ Props: appointments, filters, onDateSelect, etc.
│     │  │
│     │  └─ Internal:
│     │     ├─ CalendarFilters (sub-component)
│     │     ├─ CalendarSummary (sub-component)
│     │     └─ Month grid rendering
│     │
│     └─ CalendarDetailPanel
│        ├─ Props: selectedDate, appointments, etc.
│        │
│        └─ Shows appointment cards with:
│           ├─ Client info
│           ├─ Service & time
│           ├─ Payment status
│           └─ Click handler → setViewModalAppointment()
│
└─ Modals
   └─ AppointmentModal (reused from appointments section)
      ├─ Opens when appointment clicked
      ├─ Shows payment form
      ├─ Generates receipt
      └─ Updates calendar on success
```

---

**This complete data flow ensures:**
✅ No data loss from old appointments  
✅ All new fields populated  
✅ Graceful handling of missing data  
✅ Proper filtering and display  
✅ Real-time sync between sections  
✅ Robust error handling
