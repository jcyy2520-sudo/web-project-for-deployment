@component('mail::message')
# 🔄 Refund Request Received

Hello {{ $userDetails['name'] }},

We have received your refund request and it is currently being reviewed by our admin team. We will process your request as soon as possible.

## Refund Request Details

| Item | Details |
|------|---------|
| **Refund Amount** | ₱{{ number_format($refund->refund_amount, 2) }} |
| **Status** | 🔄 Under Review |
| **Request Date** | {{ $refund->created_at->format('F d, Y \a\t g:i A') }} |
| **Reason** | {{ ucfirst(str_replace('_', ' ', $refund->reason)) }} |

@if($refund->description)
**Additional Details:**  
{{ $refund->description }}
@endif

## Appointment Information

| Item | Details |
|------|---------|
| **Service** | {{ $appointmentDetails['service'] }} |
| **Date** | {{ $appointmentDetails['date']->format('F d, Y') }} |
| **Time** | {{ $appointmentDetails['time'] }} |
| **Original Amount** | ₱{{ number_format($appointmentDetails['payment_amount'], 2) }} |
@if($appointmentDetails['discount_amount'] > 0)
| **Discount Applied** | ₱{{ number_format($appointmentDetails['discount_amount'], 2) }} |
@endif

## What Happens Next?

1. ✅ **Your request has been submitted** - We've received your refund request
2. ⏳ **Under Review** - Our admin team will review your request
3. 📧 **You'll be notified** - We'll send you an email once your request is approved or requires additional information

**Typical Processing Time:** 1-3 business days

---

If you have any questions about your refund request, please don't hesitate to contact us.

Thank you for your patience!

Best regards,  
**Legal Ease Team**

@component('mail::button', ['url' => config('app.frontend_url') . '/appointments'])
View My Appointments
@endcomponent

---

<small style="color: #666;">This is an automated email. Please do not reply directly to this message.</small>

@endcomponent
