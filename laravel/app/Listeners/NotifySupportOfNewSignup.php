<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Mail\SiteNotifier;
use Illuminate\Auth\Events\Registered;

/**
 * Tells support when an account is created.
 *
 * Listens to Laravel's own Registered event, which both sign-up paths
 * (email + password, and Continue with Google) already fire — so neither
 * controller needs to know this exists.
 */
class NotifySupportOfNewSignup
{
    public function __construct(
        private readonly SiteNotifier $notifier,
    ) {}

    public function handle(Registered $event): void
    {
        if ($event->user instanceof User) {
            $this->notifier->userRegistered($event->user);
        }
    }
}
