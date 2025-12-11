<?php $__env->startComponent('mail::message'); ?>
# 🔄 Refund Request Received

Hello <?php echo e($userDetails['name']); ?>,

We have received your refund request and it is currently being reviewed by our admin team. We will process your request as soon as possible.

## Refund Request Details

| Item | Details |
|------|---------|
| **Refund Amount** | ₱<?php echo e(number_format($refund->refund_amount, 2)); ?> |
| **Status** | 🔄 Under Review |
| **Request Date** | <?php echo e($refund->created_at->format('F d, Y \a\t g:i A')); ?> |
| **Reason** | <?php echo e(ucfirst(str_replace('_', ' ', $refund->reason))); ?> |

<?php if($refund->description): ?>
**Additional Details:**  
<?php echo e($refund->description); ?>

<?php endif; ?>

## Appointment Information

| Item | Details |
|------|---------|
| **Service** | <?php echo e($appointmentDetails['service']); ?> |
| **Date** | <?php echo e($appointmentDetails['date']->format('F d, Y')); ?> |
| **Time** | <?php echo e($appointmentDetails['time']); ?> |
| **Original Amount** | ₱<?php echo e(number_format($appointmentDetails['payment_amount'], 2)); ?> |
<?php if($appointmentDetails['discount_amount'] > 0): ?>
| **Discount Applied** | ₱<?php echo e(number_format($appointmentDetails['discount_amount'], 2)); ?> |
<?php endif; ?>

## What Happens Next?

1. ✅ **Your request has been submitted** - We've received your refund request
2. ⏳ **Under Review** - Our admin team will review your request
3. 📧 **You'll be notified** - We'll send you an email once your request is approved or requires additional information

**Typical Processing Time:** 1-3 business days

---

If you have any questions about your refund request, please don't hesitate to contact us.

Thank you for your patience!

Best regards,  
**Legal Ease Team**

<?php $__env->startComponent('mail::button', ['url' => config('app.frontend_url') . '/appointments']); ?>
View My Appointments
<?php echo $__env->renderComponent(); ?>

---

<small style="color: #666;">This is an automated email. Please do not reply directly to this message.</small>

<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\laragon\www\web\web-backend\resources\views/emails/refund-requested.blade.php ENDPATH**/ ?>