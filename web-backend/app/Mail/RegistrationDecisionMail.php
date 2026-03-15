<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $confirmUrl;
    public string $denyUrl;

    public function __construct(User $user, string $confirmUrl, string $denyUrl)
    {
        $this->user = $user;
        $this->confirmUrl = $confirmUrl;
        $this->denyUrl = $denyUrl;
    }

    public function build()
    {
        return $this->subject('Confirm New Registration - Law Notary System')
            ->view('emails.registration-decision')
            ->with([
                'user' => $this->user,
                'confirmUrl' => $this->confirmUrl,
                'denyUrl' => $this->denyUrl,
            ]);
    }
}
