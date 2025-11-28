<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Booking Confirmed - Legal Ease</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .header { background: #f59e0b; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .content { background: #fff; border: 1px solid #e5e7eb; padding: 30px 20px; }
        .text { font-size: 14px; color: #4b5563; margin: 12px 0; line-height: 1.6; }
        .details-box { background: #f9fafb; border-left: 4px solid #f59e0b; padding: 16px; margin: 20px 0; }
        .detail-row { display: flex; padding: 8px 0; font-size: 14px; }
        .detail-label { font-weight: 600; color: #4b5563; min-width: 100px; }
        .detail-value { color: #1f2937; }
        .status { display: inline-block; background: #fbbf24; color: #78350f; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
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
            
            <p class="text">Thank you for booking an appointment with us. We have received your request and it is now pending review by our team.</p>
            
            <div class="details-box">
                <strong>Appointment Details:</strong>
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
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value"><span class="status">Pending Review</span></div>
                </div>
            </div>
            
            <p class="text">Our team will review your appointment within 24 hours. You will receive an email confirmation once your appointment has been approved or if we need to discuss alternative times.</p>
            
            <p class="text">If you need to make changes, please contact our support team.</p>
            
            <div class="footer">
                <p>&copy; {{ date('Y') }} Legal Ease. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>