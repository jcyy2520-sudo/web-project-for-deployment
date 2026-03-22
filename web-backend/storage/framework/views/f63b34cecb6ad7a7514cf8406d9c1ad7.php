<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Booking Confirmed - Legal Ease</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; background-color: #f9fafb; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 24px 20px; text-align: center; border-bottom: 2px solid #e5e7eb; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #111827; }
        .content { background: white; padding: 32px 20px; margin-top: 0; }
        .text { font-size: 14px; color: #4b5563; margin: 12px 0; line-height: 1.6; }
        .details-box { background: #f9fafb; border: 1px solid #d1d5db; padding: 16px; margin: 20px 0; border-radius: 4px; }
        .detail-row { display: flex; padding: 8px 0; font-size: 13px; }
        .detail-label { font-weight: 600; color: #4b5563; min-width: 90px; }
        .detail-value { color: #1f2937; flex: 1; }
        .status-text { display: inline-block; font-weight: 700; color: #1f2937; }
        .footer { font-size: 12px; color: #6b7280; text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Legal Ease</h1>
        </div>
        
        <div class="content">
            <p class="text">Hello <?php echo e($appointment->user->first_name ?? 'there'); ?>,</p>
            
            <p class="text">Thank you for booking an appointment with us. We have received your request and it is now pending review by our team.</p>
            
            <div class="details-box">
                <strong style="font-size: 13px;">Appointment Details:</strong>
                <div class="detail-row">
                    <div class="detail-label">Date:</div>
                    <div class="detail-value"><?php echo e($appointment->appointment_date->format('F d, Y')); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Time:</div>
                    <div class="detail-value"><?php echo e(\Carbon\Carbon::parse($appointment->appointment_time)->format('g:i A')); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Service:</div>
                    <div class="detail-value"><?php echo e($appointment->service_type ?? \App\Models\Appointment::getTypes()[$appointment->type] ?? $appointment->type); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value"><span class="status-text">PENDING REVIEW</span></div>
                </div>
            </div>
            
            <?php if($appointment->service && !empty($appointment->service->public_requirements)): ?>
                <div class="details-box" style="border-left: 4px solid #3b82f6; background-color: #eff6ff;">
                    <strong style="font-size: 13px; color: #1e40af;">Important Requirements:</strong>
                    <p style="font-size: 13px; color: #1e3a8a; margin: 4px 0 8px 0;">If your appointment is approved, please prepare the following:</p>
                    <ul style="font-size: 13px; color: #1e3a8a; margin: 0; padding-left: 20px;">
                        <?php $__currentLoopData = $appointment->service->public_requirements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $req): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li style="margin-bottom: 4px;"><?php echo e($req); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <p class="text">Our team will review your appointment within 24 hours. You will receive an email confirmation once your appointment has been approved or if we need to discuss alternative times.</p>
            
            <p class="text">If you need to make changes, please contact our support team.</p>
            
            <div class="footer">
                <p>&copy; <?php echo e(date('Y')); ?> Legal Ease. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html><?php /**PATH C:\laragon\www\web\web-backend\resources\views/emails/appointment-confirmation.blade.php ENDPATH**/ ?>