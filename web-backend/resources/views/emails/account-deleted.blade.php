<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deleted - Legal Ease</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; background-color: #f9fafb; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 24px 20px; text-align: center; border-bottom: 2px solid #e5e7eb; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #111827; }
        .content { background: white; padding: 32px 20px; margin-top: 0; }
        .text { font-size: 14px; color: #4b5563; margin: 12px 0; line-height: 1.6; }
        .info-box { background: #fef3c7; border: 1px solid #f59e0b; border-left: 4px solid #f59e0b; padding: 14px; margin: 16px 0; border-radius: 2px; }
        .info-label { font-size: 12px; color: #92400e; font-weight: 600; }
        .info-text { font-size: 14px; color: #78350f; margin-top: 4px; }
        .footer { font-size: 12px; color: #6b7280; text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Legal Ease</h1>
        </div>
        
        <div class="content">
            <p class="text">Hello {{ $userName }},</p>
            
            <p class="text">We're writing to confirm that your account has been <strong>successfully deleted</strong> as per your request.</p>
            
            <div class="info-box">
                <div class="info-label">Account Details</div>
                <div class="info-text">
                    <strong>Name:</strong> {{ $userName }}<br>
                    <strong>Email:</strong> {{ $userEmail }}<br>
                    <strong>Deleted on:</strong> {{ now()->format('F j, Y \a\t g:i A') }}
                </div>
            </div>
            
            <p class="text">All your personal information and data associated with this account have been permanently removed from our system.</p>
            
            <p class="text">If you did not request this deletion, please contact our support team immediately.</p>
            
            <div class="footer">
                <p>&copy; {{ date('Y') }} Legal Ease. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
