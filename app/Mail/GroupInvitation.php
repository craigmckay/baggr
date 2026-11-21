<?php

namespace App\Mail;

use App\Models\GroupInvite;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class GroupInvitation extends Mailable
{
    public function __construct(public GroupInvite $invite) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('You have been invited to :group on :app', [
            'group' => $this->invite->group->name,
            'app' => config('app.name'),
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.group-invitation', with: [
            'groupName' => $this->invite->group->name,
            'inviter' => $this->invite->inviter?->name,
            'role' => $this->invite->role->label(),
            'url' => route('invitations.show', $this->invite->token),
            'days' => (int) now()->diffInDays($this->invite->expires_at, false) + 1,
        ]);
    }
}
