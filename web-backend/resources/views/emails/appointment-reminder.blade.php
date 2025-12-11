@component('mail::message')
# 🔔 Appointment Reminder

Hello {{ $appointment->user->first_name ?? 'there' }},

This is a friendly reminder that your appointment is coming up soon!

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

## ⏰ Time Remaining

Your appointment is **approximately {{ $timeUntilAppointment }} minutes** away.

## Important Reminders

- ✅ Please arrive **10 minutes early** to complete any necessary paperwork
- 📋 Bring any required documents or identification
- 📱 If you need to reschedule or cancel, please contact us as soon as possible

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

@component('mail::button', ['url' => config('app.frontend_url') . '/appointments'])
View My Appointments
@endcomponent

---

**Location:**  
Legal Ease Office  
[Your Office Address Here]

**Contact:**  
Phone: [Your Phone Number]  
Email: [Your Email]

---

We look forward to seeing you soon!

Best regards,  
**Legal Ease Team**

<small style="color: #666;">This is an automated reminder email. Please do not reply directly to this message.</small>

@endcomponent
