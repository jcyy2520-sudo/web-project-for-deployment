<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Completion Confirmation - Legal Ease</title>
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
        .detail-label { font-weight: 600; color: #4b5563; min-width: 100px; }
        .detail-value { color: #1f2937; flex: 1; }
        .notes-section { background: #f9fafb; border: 1px solid #d1d5db; border-left: 4px solid #111827; padding: 14px; margin: 16px 0; border-radius: 2px; }
        .notes-label { font-weight: 600; color: #111827; font-size: 13px; margin-bottom: 8px; }
        .notes-text { font-size: 13px; color: #374151; line-height: 1.5; margin: 0; }
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
            
            <p class="text">We are pleased to confirm that your appointment has been successfully completed. Thank you for choosing Legal Ease for your legal service needs.</p>
            
            <div class="status-line">
                <div class="status-text">✓ COMPLETED</div>
            </div>
            
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
                @if($appointment->staff)
                    <div class="detail-row">
                        <div class="detail-label">Completed by:</div>
                        <div class="detail-value">{{ $appointment->staff->first_name }} {{ $appointment->staff->last_name }}</div>
                    </div>
                @endif
                @if($appointment->completed_at)
                    <div class="detail-row">
                        <div class="detail-label">Completed on:</div>
                        <div class="detail-value">{{ $appointment->completed_at->format('F d, Y \a\t g:i A') }}</div>
                    </div>
                @endif
            </div>
            
            @if($appointment->completion_notes)
                <div class="notes-section">
                    <div class="notes-label">Completion Notes:</div>
                    <div class="notes-text">{{ $appointment->completion_notes }}</div>
                </div>
            @endif
            
            <p class="text">If you have any questions or need any follow-up assistance, please don't hesitate to reach out to our support team.</p>
            
            <div class="footer">
                <p>&copy; {{ date('Y') }} Legal Ease. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>