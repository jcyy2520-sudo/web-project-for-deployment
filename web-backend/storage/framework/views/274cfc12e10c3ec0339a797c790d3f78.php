<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Your Registration</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; background: #f3f4f6; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 20px; }
        .card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .head { padding: 20px; border-bottom: 1px solid #e5e7eb; font-weight: bold; font-size: 20px; }
        .body { padding: 20px; }
        .text { margin: 0 0 14px 0; font-size: 14px; }
        .actions { margin: 24px 0; text-align: center; }
        .btn { display: inline-block; padding: 10px 18px; margin: 6px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; }
        .btn-yes { background: #16a34a; color: #ffffff; }
        .btn-no { background: #dc2626; color: #ffffff; }
        .note { font-size: 12px; color: #6b7280; margin-top: 18px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="head">Law Notary System</div>
            <div class="body">
                <p class="text">Hello <?php echo e($user->first_name ?: $user->username); ?>,</p>
                <p class="text">A new account registration was completed using this email address.</p>
                <p class="text">Please choose one option below to continue.</p>

                <div class="actions">
                    <a class="btn btn-yes" href="<?php echo e($confirmUrl); ?>">It is me</a>
                    <a class="btn btn-no" href="<?php echo e($denyUrl); ?>">Its not me</a>
                </div>

                <p class="note">If you click It is me, your account will be activated and you can log in.</p>
                <p class="note">If you click Its not me, this registration will be blocked.</p>
                <p class="note">This link expires in 24 hours.</p>
            </div>
        </div>
    </div>
</body>
</html>
<?php /**PATH C:\laragon\www\web\web-backend\resources\views/emails/registration-decision.blade.php ENDPATH**/ ?>