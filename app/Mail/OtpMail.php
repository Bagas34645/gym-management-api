<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public int $ttl) {}

    public function build()
    {
        return $this->subject('Kode OTP Verifikasi')
            ->view('emails.otp')
            ->with(['code' => $this->code, 'ttl' => $this->ttl]);
    }
}
