<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Exceptions\GroupRuleException;
use App\Models\GroupInvite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * An emailed invitation: a single-use link for one address. The admin who sent
 * it has vouched for the person, so it adds them at the role the admin chose.
 */
class GroupInvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(string $token): Response|View
    {
        $invite = GroupInvite::findOpen($token);

        if ($invite === null) {
            return $this->unavailable('invitation');
        }

        $account = User::where('email', $invite->email)->first();

        return view('groups.invitation', [
            'invite' => $invite,
            'hasAccount' => $account !== null,
            'signedInAs' => Auth::user(),
            'signedInIsInvitee' => Auth::check() && Str::lower(Auth::user()->email) === $invite->email,
        ]);
    }

    /**
     * Accept while signed in as the invited person.
     */
    public function accept(Request $request, string $token): RedirectResponse|Response|View
    {
        $invite = GroupInvite::findOpen($token);

        if ($invite === null) {
            return $this->unavailable('invitation');
        }

        $user = $request->user();
        abort_unless(Str::lower($user->email) === $invite->email, 403);

        $this->join($invite, $user);

        return redirect()->route('groups.show', $invite->group_id);
    }

    /**
     * Create an account from an invitation for an address that has none.
     */
    public function register(Request $request, string $token): RedirectResponse|Response|View
    {
        $invite = GroupInvite::findOpen($token);

        if ($invite === null) {
            return $this->unavailable('invitation');
        }

        abort_if(User::where('email', $invite->email)->exists(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ]);

        $user = new User;
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $invite->email,
            'password' => $validated['password'],
            // The invitation went to this address, so opening it proves they own it.
            'email_verified_at' => now(),
        ])->save();

        $this->join($invite, $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('groups.show', $invite->group_id);
    }

    private function join(GroupInvite $invite, User $user): void
    {
        try {
            $invite->group->addMember($user, $invite->role);
        } catch (GroupRuleException) {
            // They are already in the group, and the invitation would have demoted its only admin.
            // Leave their role alone.
        }

        $invite->forceFill(['accepted_at' => now()])->save();
    }
}
