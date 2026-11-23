<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\GroupRole;
use App\Mail\VisitorWaiting;
use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * The group's reusable link, shared on Signal or WhatsApp. Opening it changes
 * nothing, so a link preview can't join anyone; only the Join button does, and
 * it makes the person a Visitor until an admin approves them.
 */
class GroupJoinController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    public function show(string $token): Response|View
    {
        $group = Group::findByLiveJoinToken($token);

        if ($group === null) {
            return $this->unavailable('link');
        }

        return view('groups.join', [
            'group' => $group,
            'token' => $token,
            'already' => Auth::check() ? $group->roleOf(Auth::user()) : null,
            'full' => ! $group->hasRoomForVisitors(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse|Response|View
    {
        $group = Group::findByLiveJoinToken($token);

        if ($group === null) {
            return $this->unavailable('link');
        }

        if (Auth::check()) {
            $user = $request->user();

            if ($group->roleOf($user) !== null) {
                return redirect()->route('groups.show', $group);
            }

            $this->requireRoom($group);
            $this->arrive($group, $user);

            return redirect()->route('groups.show', $group);
        }

        $validated = $request->validate([
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        $this->requireRoom($group);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $this->arrive($group, $user);

        // Sends the verification email. Nothing else in Baggr opens until it is used.
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    /**
     * Make the person a Visitor and tell the group's admins. A mail problem is
     * reported but never stops someone joining.
     */
    private function arrive(Group $group, User $user): void
    {
        $group->addMember($user, GroupRole::Visitor);

        try {
            foreach ($group->users()->wherePivot('role', GroupRole::Admin->value)->get() as $admin) {
                Mail::to($admin)->send(new VisitorWaiting($group, $user));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function requireRoom(Group $group): void
    {
        if (! $group->hasRoomForVisitors()) {
            throw ValidationException::withMessages([
                'email' => __('This group has too many people waiting to be approved. Ask an admin to clear the list, then try again.'),
            ]);
        }
    }
}
