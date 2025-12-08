@component('mail::message')
# 💰 Refund Request Approved

Hello {{ $userDetails['name'] }},

Great news! Your refund request has been **approved** and is being processed.

## Refund Details

| Item | Details |
|------|---------|
| **Refund Amount** | ₱{{ number_format($refund->refund_amount, 2) }} |
| **Refund Status** | Approved |
| **Refund Method** | {{ ucfirst(str_replace('_', ' ', $refund->refund_method ?? 'Original Method')) }} |
| **Request Date** | {{ $refund->created_at->format('F d, Y \a\t g:i A') }} |
| **Approved Date** | {{ $refund->approved_at->format('F d, Y \a\t g:i A') }} |

## Appointment Information

| Item | Details |
|------|---------|
| **Service** | {{ $appointmentDetails['service'] }} |
| **Date & Time** | {{ $appointmentDetails['date']->format('F d, Y') }} at {{ $appointmentDetails['time'] }} |
| **Original Amount** | ₱{{ number_format($appointmentDetails['payment_amount'], 2) }} |
| **Discount Applied** | ₱{{ number_format($appointmentDetails['discount_amount'] ?? 0, 2) }} |

## What Happens Next

Your refund will be processed within **3-5 business days**. Depending on your refund method:

- **Original Method**: Will be credited back to your original payment source
- **Bank Transfer**: Will be sent to your registered bank account
- **Cash/Check**: Will be processed according to our standard procedures

@if($refund->approval_notes)
## Additional Notes

{{ $refund->approval_notes }}
@endif

## Questions?

If you have any questions about your refund, please contact our support team or reply to this email.

Thank you for your patience.

Best regards,  
**Legal Ease Team**

@endcomponent
