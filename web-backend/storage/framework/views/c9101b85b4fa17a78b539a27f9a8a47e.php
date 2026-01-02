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
            background: linear-gradient(135deg, #D97706 0%, #B45309 100%);
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
        .feedback-box {
            background: #f9f9f9;
            border-left: 4px solid #D97706;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .rating {
            font-size: 24px;
            margin: 15px 0;
            letter-spacing: 2px;
        }
        .message-text {
            font-style: italic;
            color: #555;
            margin: 10px 0;
        }
        .footer {
            background: #f5f5f5;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #e0e0e0;
        }
        .button {
            display: inline-block;
            background: #D97706;
            color: white;
            padding: 12px 30px;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Thank You!</h1>
            <p style="margin: 5px 0 0 0;">Your feedback is valuable to us</p>
        </div>

        <div class="content">
            <p>Hello,</p>

            <p>We appreciate you taking the time to share your feedback with LegalEase. Your insights help us improve our services and provide better experiences for all our clients.</p>

            <div class="feedback-box">
                <strong style="color: #D97706;">Your Feedback Summary:</strong>
                
                <div class="rating">
                    <?php echo $rating; ?>

                </div>

                <p style="margin: 10px 0; color: #666;">
                    <strong>Rating:</strong> <?php echo e($feedback->rating); ?> out of 5 stars
                </p>

                <p style="margin: 10px 0;">
                    <strong>Your Message:</strong>
                </p>
                
                <div class="message-text">
                    "<?php echo e($feedback->message); ?>"
                </div>
            </div>

            <p>If you provided your contact information, our team may reach out to discuss your feedback further and address any concerns you may have.</p>

            <p>Thank you again for being part of the LegalEase community!</p>

            <p style="margin-top: 25px;">
                Best regards,<br>
                <strong>The LegalEase Team</strong>
            </p>
        </div>

        <div class="footer">
            <p>&copy; <?php echo e(now()->year); ?> LegalEase System. All rights reserved.</p>
            <p>233 Aljenjay Building, Vicente Ylagan Street, Bagong Bayan 2, Bongabong, Oriental Mindoro</p>
        </div>
    </div>
</body>
</html>
<?php /**PATH C:\laragon\www\web\web-backend\resources\views/emails/feedback-confirmation.blade.php ENDPATH**/ ?>