<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - Legal Ease</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; background-color: #f9fafb; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 24px 20px; text-align: center; border-bottom: 2px solid #e5e7eb; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #111827; }
        .content { background: white; padding: 32px 20px; margin-top: 0; }
        .text { font-size: 14px; color: #4b5563; margin: 12px 0; line-height: 1.6; }
        .code-box { background: #fef3c7; border: 1px solid #f59e0b; padding: 20px; margin: 24px 0; border-radius: 4px; }
        .code { font-size: 36px; font-weight: 700; color: #92400e; text-align: center; font-family: 'Courier New', monospace; letter-spacing: 6px; margin: 0; }
        .code-expiry { font-size: 12px; color: #92400e; text-align: center; margin-top: 12px; }
        .warning { background: #fef2f2; border: 1px solid #fecaca; padding: 12px; margin: 16px 0; border-radius: 4px; }
        .warning p { font-size: 12px; color: #991b1b; margin: 0; }
        .footer { font-size: 12px; color: #6b7280; text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Legal Ease</h1>
        </div>
        
        <div class="content">
            <p class="text">Hello,</p>
            
            <p class="text">We received a request to reset your password. Please use the verification code below to proceed with your password reset.</p>
            
            <div class="code-box">
                <div class="code"><?php echo e($code); ?></div>
                <div class="code-expiry">This code expires in 15 minutes</div>
            </div>
            
            <p class="text">Enter this code on the password reset page to set your new password.</p>
            
            <div class="warning">
                <p>If you did not request a password reset, please ignore this email. Your account remains secure.</p>
            </div>
            
            <div class="footer">
                <p>&copy; <?php echo e(date('Y')); ?> Legal Ease. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
<?php /**PATH C:\laragon\www\web\web-backend\resources\views/emails/password-reset-code.blade.php ENDPATH**/ ?>