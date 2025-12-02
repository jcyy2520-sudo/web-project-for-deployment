<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Status Update - Legal Ease</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; background-color: #f9fafb; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 24px 20px; text-align: center; border-bottom: 2px solid #e5e7eb; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #111827; }
        .content { background: white; padding: 32px 20px; margin-top: 0; }
        .text { font-size: 14px; color: #4b5563; margin: 12px 0; line-height: 1.6; }
        .status-line { margin: 20px 0; padding: 16px; border-left: 4px solid #111827; border-radius: 2px; }
        .status-text { font-weight: 700; font-size: 16px; color: #111827; }
        .details-box { background: #f9fafb; border: 1px solid #d1d5db; padding: 16px; margin: 20px 0; border-radius: 4px; }
        .detail-row { display: flex; padding: 8px 0; font-size: 13px; }
        .detail-label { font-weight: 600; color: #4b5563; min-width: 90px; }
        .detail-value { color: #1f2937; flex: 1; }
        .decline-reason { background: #f9fafb; border: 1px solid #d1d5db; border-left: 4px solid #111827; padding: 14px; margin: 16px 0; border-radius: 2px; }
        .reason-label { font-weight: 600; color: #111827; font-size: 13px; margin-bottom: 8px; }
        .reason-text { color: #374151; font-size: 13px; line-height: 1.5; }
        .notes-section { background: #f9fafb; border: 1px solid #d1d5db; border-left: 4px solid #111827; padding: 14px; margin: 16px 0; border-radius: 2px; }
        .notes-label { font-weight: 600; color: #111827; font-size: 13px; margin-bottom: 8px; }
        .notes-text { color: #374151; font-size: 13px; line-height: 1.5; margin: 0; }
        .footer { font-size: 12px; color: #6b7280; text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
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
                <div class="status-line">
                    <div class="status-text">✓ APPROVED</div>
                </div>
                <p class="text">Your appointment has been approved.</p>
            @elseif($appointment->status === 'declined')
                <div class="status-line">
                    <div class="status-text">✕ DECLINED</div>
                </div>
                <p class="text">Your appointment request has been declined.</p>
            @elseif($appointment->status === 'completed')
                <div class="status-line">
                    <div class="status-text">✓ COMPLETED</div>
                </div>
                <p class="text">Your appointment has been completed. Thank you for choosing Legal Ease.</p>
            @elseif($appointment->status === 'cancelled')
                <div class="status-line">
                    <div class="status-text">⊗ CANCELLED</div>
                </div>
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
                <div class="notes-section">
                    <div class="notes-label">Additional Notes:</div>
                    <div class="notes-text">{{ $appointment->staff_notes }}</div>
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