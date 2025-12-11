# Refund Email Notification Fix

## Issues Fixed

### 1. Missing Email Template Variables
**Problem**: The `RefundRequestedMail` class wasn't passing variables to the email template, causing the email to fail to render.

**Fix**: Added the `with` parameter in the `content()` method to pass `$refund`, `$appointmentDetails`, and `$userDetails` to the template.

### 2. Wrong User Reference
**Problem**: `RefundRequestedMail` was using `requestedBy` user instead of the appointment's user for the email recipient details.

**Fix**: Changed to use `$refund->appointment->user` to get the correct user who should receive the email.

### 3. Insufficient Error Logging
**Problem**: Errors when sending emails were not being logged with enough detail to diagnose issues.

**Fix**: Enhanced logging throughout the refund notification system with:
- Clear success/failure indicators (✅/❌)
- Stack traces for exceptions
- Warnings for missing email addresses
- Detailed context (refund ID, email address)

## Testing Email Notifications

### Test Command
A new artisan command has been created to test refund emails:

```bash
# Test with a specific email address
php artisan refund:test-email your-email@example.com

# Test with a specific refund ID
php artisan refund:test-email your-email@example.com --refund-id=1
```

This command will:
- Send all three types of refund emails (requested, completed, rejected)
- Show the current mail configuration
- Warn if emails are being logged instead of sent

### Check Logs
After attempting to send emails, check the Laravel logs:

```bash
# View latest log entries
tail -f storage/logs/laravel.log

# Or on Windows PowerShell
Get-Content storage/logs/laravel.log -Tail 50
```

Look for:
- ✅ Success messages: "Refund request email sent successfully"
- ❌ Error messages: "Failed to send refund request email"
- Warning messages about missing email addresses

## Common Issues & Solutions

### 1. Emails Going to Spam
**Problem**: Emails are being sent but ending up in spam folders.

**Solution**:
- Verify your SMTP server has proper SPF, DKIM, and DMARC records
- Use a reputable email service (Gmail SMTP, SendGrid, Mailgun, etc.)
- Ensure FROM address matches your domain

### 2. MAIL_MAILER=log (Development Mode)
**Problem**: Emails are being logged to `storage/logs/laravel.log` instead of being sent.

**Solution**:
Update your `.env` file:
```env
MAIL_MAILER=smtp  # Change from 'log' to 'smtp'
```

Then cache the configuration:
```bash
php artisan config:cache
```

### 3. SMTP Configuration Issues
**Problem**: Emails fail to send due to incorrect SMTP settings.

**Solution**:
Verify your `.env` file has correct SMTP settings:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Legal Ease"
```

**For Gmail**:
- Enable 2-factor authentication
- Generate an "App Password" 
- Use the app password in MAIL_PASSWORD

**For Other Providers**:
- **SendGrid**: Use API key as password
- **Mailgun**: Configure domain and API key
- **AWS SES**: Configure credentials

### 4. Queue Not Processing
**Problem**: Emails are queued but not being sent.

**Solution**:
Start the queue worker:
```bash
php artisan queue:work --tries=3
```

Or for development:
```bash
php artisan queue:listen
```

Check if jobs are failing:
```bash
php artisan queue:failed
```

Retry failed jobs:
```bash
php artisan queue:retry all
```

### 5. Missing User Email
**Problem**: User account doesn't have an email address.

**Solution**:
- Check the users table for missing emails
- Update user records to include email addresses
- Look for log warnings: "Cannot send refund notification: User email is missing"

## Email Templates

All refund email templates are located in:
- `resources/views/emails/refund-requested.blade.php`
- `resources/views/emails/refund-completed.blade.php`
- `resources/views/emails/refund-rejected.blade.php`
- `resources/views/emails/refund-approved.blade.php`

## When Emails Are Sent

1. **Refund Requested** - Sent immediately when a user/cashier requests a refund
2. **Refund Completed** - Sent when admin approves/completes the refund
3. **Refund Rejected** - Sent when admin rejects the refund request

## Troubleshooting Steps

1. **Run the test command**:
   ```bash
   php artisan refund:test-email your-email@example.com
   ```

2. **Check the logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Verify mail configuration**:
   ```bash
   php artisan config:show mail
   ```

4. **Test SMTP connection** (if using SMTP):
   ```bash
   php artisan tinker
   Mail::raw('Test email', function($msg) { 
       $msg->to('your-email@example.com')->subject('Test'); 
   });
   ```

5. **Check for failed queue jobs**:
   ```bash
   php artisan queue:failed
   ```

6. **Ensure queue worker is running** (for production):
   ```bash
   # Check if queue worker is running
   ps aux | grep "queue:work"
   
   # Start it if not running
   php artisan queue:work --daemon
   ```

## Production Deployment Checklist

- [ ] Update `.env` with production SMTP settings
- [ ] Set `MAIL_MAILER=smtp` (not 'log')
- [ ] Verify MAIL_FROM_ADDRESS is a valid email
- [ ] Test emails with `php artisan refund:test-email`
- [ ] Set up queue worker as a service (systemd/supervisor)
- [ ] Configure email provider (SendGrid, Mailgun, SES, etc.)
- [ ] Set up SPF/DKIM/DMARC records for your domain
- [ ] Monitor `storage/logs/laravel.log` for email errors
- [ ] Test with real user accounts
- [ ] Check spam folders initially

## Support

If emails still aren't sending after following these steps:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check server email logs (if available)
3. Verify SMTP server is accepting connections
4. Contact your email provider for delivery issues
