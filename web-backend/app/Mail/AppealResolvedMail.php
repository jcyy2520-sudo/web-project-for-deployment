<?php

namespace App\Mail;

use App\Models\AccountAppeal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppealResolvedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $appeal;

    public function __construct(AccountAppeal $appeal)
    {
        $this->appeal = $appeal;
    }

    public function build()
    {
        $statusLabel = $this->appeal->status === 'approved' ? 'Approved' : 'Rejected';
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        return $this->subject("Your Appeal Has Been {$statusLabel} - Legal Ease")
                    ->view('emails.appeal-resolved')
                    ->with([
                        'appeal' => $this->appeal,
                        'statusLabel' => $statusLabel,
                        'loginUrl' => $frontendUrl . '?force_logout=true',
                    ]);
    }
}
