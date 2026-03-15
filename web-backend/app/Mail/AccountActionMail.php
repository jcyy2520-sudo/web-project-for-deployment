<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountActionMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userName;
    public $userEmail;
    public $actionType;
    public $reason;
    public $appealUrl;

    public function __construct(string $userName, string $userEmail, string $actionType, string $reason, string $appealUrl)
    {
        $this->userName = $userName;
        $this->userEmail = $userEmail;
        $this->actionType = $actionType;
        $this->reason = $reason;
        $this->appealUrl = $appealUrl;
    }

    public function build()
    {
        $actionLabels = [
            'deleted' => 'Deleted',
            'blocked' => 'Blocked',
            'deactivated' => 'Deactivated',
        ];

        $actionLabel = $actionLabels[$this->actionType] ?? ucfirst($this->actionType);

        return $this->subject("Your Account Has Been {$actionLabel} - Legal Ease")
                    ->view('emails.account-action')
                    ->with([
                        'userName' => $this->userName,
                        'userEmail' => $this->userEmail,
                        'actionType' => $this->actionType,
                        'actionLabel' => $actionLabel,
                        'reason' => $this->reason,
                        'appealUrl' => $this->appealUrl,
                    ]);
    }
}
