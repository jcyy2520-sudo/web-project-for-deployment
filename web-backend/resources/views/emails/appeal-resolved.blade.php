<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appeal {{ $statusLabel }} - Legal Ease</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; background-color: #f9fafb; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 24px 20px; text-align: center; border-bottom: 2px solid #e5e7eb; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #111827; }
        .content { background: white; padding: 32px 20px; margin-top: 0; }
        .text { font-size: 14px; color: #4b5563; margin: 12px 0; line-height: 1.6; }
        .status-approved { background: #f0fdf4; border: 1px solid #86efac; border-left: 4px solid #22c55e; padding: 14px; margin: 16px 0; border-radius: 2px; }
        .status-rejected { background: #fef2f2; border: 1px solid #fca5a5; border-left: 4px solid #ef4444; padding: 14px; margin: 16px 0; border-radius: 2px; }
        .status-label { font-size: 12px; font-weight: 600; }
        .status-label-approved { color: #166534; }
        .status-label-rejected { color: #991b1b; }
        .status-text { font-size: 14px; margin-top: 4px; }
        .status-text-approved { color: #15803d; }
        .status-text-rejected { color: #7f1d1d; }
        .response-box { background: #f9fafb; border: 1px solid #d1d5db; padding: 16px; margin: 20px 0; white-space: pre-wrap; word-break: break-word; font-size: 13px; color: #374151; line-height: 1.6; border-radius: 2px; }
        .response-label { font-size: 12px; color: #6b7280; font-weight: 600; margin-bottom: 6px; }
        .login-box { background: #eff6ff; border: 1px solid #93c5fd; border-left: 4px solid #3b82f6; padding: 16px; margin: 20px 0; border-radius: 2px; text-align: center; }
        .login-text { font-size: 13px; color: #1e40af; margin-bottom: 12px; }
        .login-btn { display: inline-block; background: #d97706; color: white; padding: 10px 24px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 14px; }
        .login-btn:hover { background: #b45309; }
        .footer { font-size: 12px; color: #6b7280; text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Legal Ease</h1>
        </div>
        
        <div class="content">
            <p class="text">Hello {{ $appeal->user_name }},</p>
            
            <div class="status-{{ $appeal->status }}">
                <div class="status-label status-label-{{ $appeal->status }}">Appeal {{ $statusLabel }}</div>
                <div class="status-text status-text-{{ $appeal->status }}">
                    @if($appeal->status === 'approved')
                        Your appeal has been <strong>approved</strong>. Your account has been restored and you can now log in again.
                    @else
                        Your appeal has been <strong>rejected</strong>. The original action on your account remains in effect.
                    @endif
                </div>
            </div>
            
            @if($appeal->admin_response)
                <div>
                    <div class="response-label">Admin Response:</div>
                    <div class="response-box">{{ $appeal->admin_response }}</div>
                </div>
            @endif
            
            <p class="text">
                @if($appeal->status === 'approved')
                    You can log in to your account using your previous credentials at any time.
                @else
                    If you have further questions, please contact our support team.
                @endif
            </p>

            @if($appeal->status === 'approved')
            <div class="login-box">
                <p class="login-text">Your account has been restored. You can now log in:</p>
                <a href="{{ $loginUrl }}" class="login-btn">Log In to Legal Ease</a>
            </div>
            @endif
            
            <div class="footer">
                <p>&copy; {{ date('Y') }} Legal Ease. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
