<?php $__env->startComponent('mail::message'); ?>
# 📋 Refund Request Decision

Hello <?php echo e($userDetails['name']); ?>,

We have reviewed your refund request and unfortunately, it has been **declined** at this time.

## Refund Details

| Item | Details |
|------|---------|
| **Requested Amount** | ₱<?php echo e(number_format($refund->refund_amount, 2)); ?> |
| **Refund Status** | Declined |
| **Request Date** | <?php echo e($refund->created_at->format('F d, Y \a\t g:i A')); ?> |
| **Decision Date** | <?php echo e($refund->approved_at->format('F d, Y \a\t g:i A')); ?> |

## Appointment Information

| Item | Details |
|------|---------|
| **Service** | <?php echo e($appointmentDetails['service']); ?> |
| **Date & Time** | <?php echo e($appointmentDetails['date']->format('F d, Y')); ?> at <?php echo e($appointmentDetails['time']); ?> |
| **Payment Amount** | ₱<?php echo e(number_format($appointmentDetails['payment_amount'], 2)); ?> |

## Reason for Decline

**<?php echo e(ucfirst(str_replace('_', ' ', $refund->rejection_reason ?? 'See details below'))); ?>**

<?php if($refund->rejection_reason): ?>
<?php echo e($refund->rejection_reason); ?>

<?php endif; ?>

## What You Can Do

If you believe this decision is incorrect or have additional information to provide, please:

1. Contact our support team within 7 days with additional documentation
2. Provide a detailed explanation of your situation
3. Include any supporting evidence or communications

## Our Refund Policy

Our refund policy is designed to be fair to both customers and our business:

- Refunds are generally available within 30 days of the appointment
- Service must not have been completed or must be unsatisfactory
- Requests must be accompanied by a valid reason
- Duplicate refunds are not permitted

For more details on our refund policy, please visit our website.

## Need Help?

If you have any questions or would like to discuss this decision further, please don't hesitate to contact us.

Best regards,  
**Legal Ease Team**

<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\laragon\www\web\web-backend\resources\views/emails/refund-rejected.blade.php ENDPATH**/ ?>