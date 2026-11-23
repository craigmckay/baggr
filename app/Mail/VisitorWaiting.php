<?php

namespace App\Mail;

use App\Models\Group;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent to a group's admins when someone arrives through its reusable link, so
 * a waiting Visitor doesn't go unnoticed (spec §2). It approves nothing: the
 * admin signs in and promotes the person on the group page.
 */
class VisitorWaiting extends Mailable
{
    public function __construct(public Group $group, public User $visitor) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __(':name is waiting to join :group on :app', [
            'name' => $this->visitor->name,
            'group' => $this->group->name,
            'app' => config('app.name'),
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.visitor-waiting', with: [
            'groupName' => $this->group->name,
            'visitorName' => $this->visitor->name,
            'visitorEmail' => $this->visitor->email,
            'url' => route('groups.show', $this->group),
        ]);
    }
}
