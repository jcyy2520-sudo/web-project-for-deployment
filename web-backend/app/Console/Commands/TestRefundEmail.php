<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class TestRefundEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refund:test-email {email} {--refund-id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test refund email notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $refundId = $this->option('refund-id');

        $this->info('Testing refund email notifications...');
        $this->info('Target email: ' . $email);

        try {
            if ($refundId) {
                // Test with actual refund
                $refund = Refund::with(['appointment.user', 'appointment.service'])->findOrFail($refundId);
                $this->info('Using refund ID: ' . $refundId);
            } else {
                // Test with mock refund
                $refund = Refund::with(['appointment.user', 'appointment.service'])->first();
                
                if (!$refund) {
                    $this->error('No refunds found in database. Create a refund first or specify --refund-id');
                    return 1;
                }
                
                $this->info('Using latest refund ID: ' . $refund->id);
            }

            // Test RefundRequestedMail
            $this->info('Sending RefundRequestedMail...');
            Mail::to($email)->send(new \App\Mail\RefundRequestedMail($refund));
            $this->info('✅ RefundRequestedMail sent successfully');

            // Test RefundCompletedMail
            $this->info('Sending RefundCompletedMail...');
            Mail::to($email)->send(new \App\Mail\RefundCompletedMail($refund));
            $this->info('✅ RefundCompletedMail sent successfully');

            // Test RefundRejectedMail
            $this->info('Sending RefundRejectedMail...');
            Mail::to($email)->send(new \App\Mail\RefundRejectedMail($refund));
            $this->info('✅ RefundRejectedMail sent successfully');

            $this->info('');
            $this->info('All test emails sent successfully!');
            $this->info('Check your email inbox (and spam folder) for the test emails.');
            
            // Check mail configuration
            $this->info('');
            $this->info('Current mail configuration:');
            $this->info('Mailer: ' . config('mail.default'));
            $this->info('Host: ' . config('mail.mailers.smtp.host'));
            $this->info('From: ' . config('mail.from.address'));

            if (config('mail.default') === 'log') {
                $this->warn('⚠️  MAIL_MAILER is set to "log" - emails are being logged to storage/logs, not sent!');
                $this->warn('   Set MAIL_MAILER to "smtp" in your .env file to send real emails.');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error sending test emails: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
