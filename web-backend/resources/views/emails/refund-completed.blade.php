@component('mail::message')
# ✅ Refund Successfully Processed

Hello {{ $userDetails['name'] }},

Your refund has been successfully processed! You should see the funds in your account shortly, depending on your refund method.

## Refund Details

| Item | Details |
|------|---------|
| **Refund Amount** | ₱{{ number_format($refund->refund_amount, 2) }} |
| **Status** | ✅ Completed |
| **Refund Method** | {{ ucfirst(str_replace('_', ' ', $refund->refund_method ?? 'Original Method')) }} |
| **Transaction ID** | {{ $refund->transaction_id ?? 'N/A' }} |
| **Processed Date** | {{ $refund->completed_at->format('F d, Y \a\t g:i A') }} |

## Appointment Information

| Item | Details |
|------|---------|
| **Service** | {{ $appointmentDetails['service'] }} |
| **Date & Time** | {{ $appointmentDetails['date']->format('F d, Y') }} at {{ $appointmentDetails['time'] }} |
| **Original Amount** | ₱{{ number_format($appointmentDetails['payment_amount'], 2) }} |

## Timeline for Refund

**Original Payment Method (Credit/Debit Card):**
- Your bank may take 3-5 business days to process
- Check your bank statement for the credit

**Bank Transfer:**
- Direct deposit completed
- Funds should appear in your account

**Cash/Check:**
- Will be issued according to our policies
- Contact us if you have not received it

## Questions or Issues?

If you do not see the refund in your account after the expected timeframe, please:

1. Check your bank or payment processor
2. Contact our support team with your transaction ID
3. Provide any documentation related to the original payment

We appreciate your business and regret that we could not fulfill your appointment needs.

Best regards,  
**Legal Ease Team**

@endcomponent
