<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message from Legal Ease - {{ $subject }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; background-color: #f9fafb; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 24px 20px; text-align: center; border-bottom: 2px solid #e5e7eb; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #111827; }
        .content { background: white; padding: 32px 20px; margin-top: 0; }
        .text { font-size: 14px; color: #4b5563; margin: 12px 0; line-height: 1.6; }
        .subject-box { background: #f9fafb; border: 1px solid #d1d5db; border-left: 4px solid #111827; padding: 14px; margin: 16px 0; border-radius: 2px; }
        .subject-label { font-size: 12px; color: #6b7280; font-weight: 600; }
        .subject-text { font-size: 14px; font-weight: 700; color: #111827; margin-top: 4px; }
        .message-box { background: #f9fafb; border: 1px solid #d1d5db; padding: 16px; margin: 20px 0; white-space: pre-wrap; word-break: break-word; font-size: 13px; color: #374151; line-height: 1.6; border-radius: 2px; }
        .footer { font-size: 12px; color: #6b7280; text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Legal Ease</h1>
        </div>
        
        <div class="content">
            <p class="text">Hello {{ $user->first_name ?? 'there' }},</p>
            
            <div class="subject-box">
                <div class="subject-label">Subject</div>
                <div class="subject-text">{{ $subject }}</div>
            </div>
            
            <div class="message-box">{{ $messageContent }}</div>
            
            <p class="text">If you have any questions, please contact our support team through the system.</p>
            
            <div class="footer">
                <p>&copy; {{ date('Y') }} Legal Ease. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>