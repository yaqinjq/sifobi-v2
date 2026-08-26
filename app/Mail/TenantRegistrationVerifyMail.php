<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\URL;

class TenantRegistrationVerifyMail extends Mailable
{
    public string $verifyUrl;

    public function __construct(public User $user)
    {
        $this->verifyUrl = URL::signedRoute('register.verify', ['user' => $user->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verifikasi Email — Aktifkan Akun SIFOBI Anda',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-registration-verify',
        );
    }
}
