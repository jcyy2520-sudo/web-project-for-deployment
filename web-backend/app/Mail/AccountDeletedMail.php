<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userName;
    public $userEmail;

    public function __construct(string $userName, string $userEmail)
    {
        $this->userName = $userName;
        $this->userEmail = $userEmail;
    }

    public function build()
    {
        return $this->subject('Your Account Has Been Deleted - Legal Ease')
                    ->view('emails.account-deleted')
                    ->with([
                        'userName' => $this->userName,
                        'userEmail' => $this->userEmail,
                    ]);
    }
}
