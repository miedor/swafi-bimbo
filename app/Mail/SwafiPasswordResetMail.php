<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SwafiPasswordResetMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $resetUrl,
        public string $userName,
        public int $minutes = 60
    ) {
    }

    public function build()
    {
        return $this
            ->subject('SWAFI | Restablecimiento de contraseña')
            ->view('emails.password-reset')
            ->with([
                'resetUrl' => $this->resetUrl,
                'userName' => $this->userName,
                'minutes' => $this->minutes,
            ]);
    }
}
