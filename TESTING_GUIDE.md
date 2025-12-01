Decision Support — Client Side

Unavailable-Time Alternatives: When a client selects an unavailable slot, the client asks the server for suggested alternatives (dates/times) and displays them in a message UI.
Function: guide client to nearest available slots and show available slot counts.
Time-Window Filtering: The client time picker limits choices to business hours (08:00–11:30, 13:00–16:30) with 30-minute increments.
Function: reduce invalid booking attempts; matches server rules.
Slot Availability Preview: On the booking UI, show per-slot availability (available slots / capacity).
Function: surface contention so clients can pick less-crowded times.
Interactive Suggestions: Alternatives in the Unavailability message are clickable to auto-fill booking fields.
Function: reduce friction in choosing an alternative.
Automatic Refresh on Admin Changes: The client listens for unavailableDatesChanged events (poller or real-time) to refresh unavailable dates.
Function: keeps clients in sync with admin-created blackouts.
Decision Support — Admin Side

Dashboard Summary (new AdminDecisionSupport): Displays an overview from /api/decision-support/dashboard (summary KPI).
Function: quick snapshot of capacity, load, and recommendation health.
Time-Slot Recommendations: Query /api/decision-support/time-slot-recommendations?date=YYYY-MM-DD to get suggested times and available slot counts for a chosen date.
Function: helps admins select alternative dates/times when editing or managing bookings.
Staff Recommendations: Query /api/decision-support/staff-recommendations?date=YYYY-MM-DD to get staff allocation suggestions and workload balancing.
Function: helps admins assign staff to improve throughput and reduce bottlenecks.
Quick Actions (UI-level, best-effort): "Reserve" a suggested slot or "Assign" a recommended staff member (these buttons call best-effort admin endpoints if present; otherwise they provide a local confirmation).
Function: speed up administrative responses (could be wired into real APIs where available).
Affected Appointments / Rescheduling Workflow (recommended next step): Show appointments impacted by a new blackout and present suggested reschedules (not implemented yet).
Function: let admins quickly reassign or message clients when blackouts are added.


A — I can run npm audit fix and rebuild the frontend now so we can test in-browser (requires permission to run terminal commands). This will also show if any runtime errors occur.
B — I can add a Laravel Echo client (realtime) to main.jsx so admin broadcasts immediately notify open clients (requires Pusher/socket config or stubbing; I can add the client-side code and document where to put credentials).
C — I can implement the "Affected Appointments" modal/workflow in the admin UI to let admins preview and apply reschedules when they add unavailable dates.