<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Status Update - Legal Ease</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .header { background: #f59e0b; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .content { background: #fff; border: 1px solid #e5e7eb; padding: 30px 20px; }
        .text { font-size: 14px; color: #4b5563; margin: 12px 0; line-height: 1.6; }
        
        .status-box { text-align: center; margin: 20px 0; padding: 16px; border-radius: 4px; font-weight: 600; font-size: 16px; }
        .status-approved { background: #d1fae5; color: #065f46; border: 2px solid #10b981; }
        .status-declined { background: #fee2e2; color: #7f1d1d; border: 2px solid #ef4444; }
        .status-completed { background: #cffafe; color: #0c4a6e; border: 2px solid #06b6d4; }
        
        .details-box { background: #f9fafb; border-left: 4px solid #f59e0b; padding: 16px; margin: 20px 0; }
        .detail-row { display: flex; padding: 6px 0; font-size: 13px; }
        .detail-label { font-weight: 600; color: #4b5563; min-width: 90px; }
        .detail-value { color: #1f2937; }
        
        .message-box { background: #f3f4f6; border-left: 4px solid #f59e0b; padding: 16px; margin: 20px 0; border-radius: 2px; }
        .message-text { font-size: 14px; color: #374151; line-height: 1.6; margin: 0; }
        
        .decline-reason { background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; padding: 12px; margin: 16px 0; border-radius: 2px; }
        .reason-label { font-weight: 600; color: #7f1d1d; font-size: 13px; margin-bottom: 6px; }
        .reason-text { color: #5f1f1f; font-size: 13px; line-height: 1.5; }
        
        .footer { font-size: 12px; color: #6b7280; text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Legal Ease</h1>
        </div>
        
        <div class="content">
            <p class="text">Hello {{ $appointment->user->first_name ?? 'there' }},</p>
            
            @if($appointment->status === 'approved')
                <div class="status-box status-approved">✓ APPROVED</div>
                <p class="text">Your appointment has been approved.</p>
            @elseif($appointment->status === 'declined')
                <div class="status-box status-declined">✕ DECLINED</div>
                <p class="text">Your appointment request has been declined.</p>
            @elseif($appointment->status === 'completed')
                <div class="status-box status-completed">✓ COMPLETED</div>
                <p class="text">Your appointment has been completed. Thank you for choosing Legal Ease.</p>
            @elseif($appointment->status === 'cancelled')
                <div class="status-box status-approved">✓ CANCELLED</div>
                <p class="text">Your appointment has been cancelled.</p>
            @endif
            
            <div class="details-box">
                <strong style="font-size: 13px;">Appointment Details:</strong>
                <div class="detail-row">
                    <div class="detail-label">Date:</div>
                    <div class="detail-value">{{ $appointment->appointment_date->format('F d, Y') }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Time:</div>
                    <div class="detail-value">{{ \Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A') }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Service:</div>
                    <div class="detail-value">{{ $appointment->service_type ?? \App\Models\Appointment::getTypes()[$appointment->type] ?? $appointment->type }}</div>
                </div>
            </div>
            
            @if($appointment->status === 'declined' && $appointment->decline_reason)
                <div class="decline-reason">
                    <div class="reason-label">Reason for Decline:</div>
                    <div class="reason-text">{{ $appointment->decline_reason }}</div>
                </div>
            @endif
            
            @if($appointment->staff_notes)
                <div class="message-box">
                    <strong style="font-size: 13px; display: block; margin-bottom: 6px;">Additional Notes:</strong>
                    <p class="message-text">{{ $appointment->staff_notes }}</p>
                </div>
            @endif
            
            <p class="text">If you have questions, please contact support.</p>
            
            <div class="footer">
                <p>&copy; {{ date('Y') }} Legal Ease. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>