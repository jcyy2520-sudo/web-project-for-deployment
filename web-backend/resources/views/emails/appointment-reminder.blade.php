@component('mail::message')
@if(($urgencyLevel ?? 'low') === 'high')
# ⚠️ Your Appointment is in {{ $timeLabel ?? 'less than an hour' }}!
@elseif(($urgencyLevel ?? 'low') === 'medium')
# 🔔 Appointment Reminder — {{ $timeLabel ?? '1 hour' }} to go
@else
# 🔔 Appointment Reminder — {{ $timeLabel ?? '2 hours' }} to go
@endif

Hello {{ $appointment->user->first_name ?? 'there' }},

@if(($urgencyLevel ?? 'low') === 'high')
**Your appointment is starting very soon!** Please make sure you are prepared and ready to arrive.
@elseif(($urgencyLevel ?? 'low') === 'medium')
This is a reminder that your appointment is coming up in about **{{ $timeLabel ?? '1 hour' }}**.
@else
This is an early reminder that your appointment is in approximately **{{ $timeLabel ?? '2 hours' }}**. You still have time to prepare!
@endif

## Your Appointment Details

| Item | Details |
|------|---------|
| **Service** | {{ $appointment->service?->name ?? $appointment->service_type ?? 'N/A' }} |
| **Date** | {{ $appointment->appointment_date->format('l, F d, Y') }} |
| **Time** | {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') }} |
| **Status** | {{ ucfirst($appointment->status) }} |

@if($appointment->staff)
| **Assigned Staff** | {{ $appointment->staff->first_name }} {{ $appointment->staff->last_name }} |
@endif

## ⏰ Time Remaining: **{{ $timeLabel }}**

## Important Reminders

@if(($urgencyLevel ?? 'low') === 'high')
- 🚨 **Please head to the office now** if you haven't already
- 📋 Make sure you have all required documents ready
- 📱 If you cannot make it, contact us **immediately**
@else
- ✅ Please arrive **10 minutes early** to complete any necessary paperwork
- 📋 Bring any required documents or identification
- 📱 If you need to reschedule or cancel, please contact us as soon as possible
@endif

@if($appointment->service && !empty($appointment->service->public_requirements))
## Important Requirements for your Service

Please ensure you bring the following for your appointment:
@foreach($appointment->service->public_requirements as $req)
- {{ $req }}
@endforeach
@endif

@if($appointment->notes)
## Your Notes

{{ $appointment->notes }}
@endif

@if($appointment->staff_notes)
## Staff Notes

{{ $appointment->staff_notes }}
@endif

## Need to Make Changes?

If you need to reschedule or cancel your appointment, please log in to your account or contact our office immediately.

@component('mail::button', ['url' => config('app.frontend_url', config('app.url')) . '/appointments'])
View My Appointments
@endcomponent

---

**Location:**
Legal Ease Office

---

We look forward to seeing you soon!

Best regards,
**Legal Ease Team**

<small style="color: #666;">This is an automated reminder email. You will receive reminders at 2 hours, 1 hour, and 30 minutes before your appointment.</small>

@endcomponent
