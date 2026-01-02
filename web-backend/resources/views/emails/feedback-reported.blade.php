<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 30px;
        }
        .alert-box {
            background: #FEF2F2;
            border-left: 4px solid #DC2626;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .reason-box {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .footer {
            background: #f5f5f5;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #e0e0e0;
        }
        .warning-text {
            color: #DC2626;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Your Feedback Has Been Reported</h1>
            <p style="margin: 5px 0 0 0;">Review Details</p>
        </div>

        <div class="content">
            <p>Hello,</p>

            <p>We're writing to inform you that your feedback submission has been reported for review by our moderation team.</p>

            <div class="alert-box">
                <p class="warning-text">What does this mean?</p>
                <p>Your feedback has been flagged as potentially violating our community guidelines. Our team will review it to determine if any action is needed. We take the safety and respect of our community seriously.</p>
            </div>

            <div class="reason-box">
                <p style="margin: 0 0 10px 0;"><strong>Report Reason:</strong></p>
                <p style="margin: 0;">{{ $reason ?? 'Not specified' }}</p>
                
                @if($explanation)
                    <p style="margin: 15px 0 5px 0;"><strong>Additional Details:</strong></p>
                    <p style="margin: 0; color: #666;">{{ $explanation }}</p>
                @endif
            </div>

            <p><strong>What happens next?</strong></p>
            <ul style="color: #555;">
                <li>Our moderation team will review your feedback within 24-48 hours</li>
                <li>If your feedback was reported in error, it will be approved and no action will be taken</li>
                <li>If it violates our community guidelines, you may receive a warning or your account may be temporarily restricted</li>
                <li>You can contact our support team if you believe this report was made in error</li>
            </ul>

            <p>We appreciate your understanding and your contribution to keeping our community positive and respectful.</p>

            <p style="color: #666; font-size: 14px;">
                If you have any questions, please contact our support team at <strong>support@legalease.com</strong>
            </p>
        </div>

        <div class="footer">
            <p>&copy; 2025 LegalEase. All rights reserved.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
