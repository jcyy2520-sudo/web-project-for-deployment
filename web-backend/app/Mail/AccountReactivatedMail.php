<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountReactivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userName;
    public $userEmail;
    public $reason;

    public function __construct(string $userName, string $userEmail, string $reason = '')
    {
        $this->userName = $userName;
        $this->userEmail = $userEmail;
        $this->reason = $reason;
    }

    public function build()
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        return $this->subject('Your Account Has Been Reactivated - Legal Ease')
                    ->view('emails.account-reactivated')
                    ->with([
                        'userName' => $this->userName,
                        'userEmail' => $this->userEmail,
                        'reason' => $this->reason,
                        'loginUrl' => $frontendUrl . '?force_logout=true',
                    ]);
    }
}
