<?php

namespace App\Services\Mail;

use App\Models\ContactMessage;
use App\Models\ContributorApplication;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * What the site emails, and to whom.
 *
 * Each method is called at the moment something has ALREADY been saved
 * (a contact message, an application, a payout request, a new account)
 * and queues the email to go out after the response is sent. Nothing
 * here can fail the request that triggered it.
 */
class SiteNotifier
{
    /** At most this many auto-confirmations to one address per day. */
    private const CONFIRMATIONS_PER_DAY = 3;

    public function __construct(
        private readonly SiteMailer $mailer,
        private readonly SettingsService $settings,
    ) {}

    /* ---------------------------------------------------------------
     | Contact form
     | ------------------------------------------------------------- */

    public function contactReceived(ContactMessage $contact): void
    {
        $this->mailer->afterResponse(function () use ($contact) {
            $this->deliverContact($contact, withConfirmation: true);
        });
    }

    /**
     * Emails support about a stored contact message, and optionally sends
     * the visitor a confirmation. Public so the admin screen can retry a
     * message whose first attempt failed.
     */
    public function deliverContact(ContactMessage $contact, bool $withConfirmation = false): bool
    {
        $sent = $this->mailer->send([
            'to'            => $this->mailer->supportAddress(),
            'subject'       => '[Iconors contact] '.$contact->topicLabel().': '.$contact->subject.' ('.$contact->reference.')',
            'heading'       => 'New contact form message',
            'intro'         => ['Someone used the contact form on '.$this->siteHost().'. Reply to this email to answer them directly.'],
            'fields'        => [
                'Reference' => $contact->reference,
                'Form'      => 'Contact form — '.$contact->topicLabel(),
                'Name'      => $contact->name,
                'Email'     => $contact->email,
                'Subject'   => $contact->subject,
                'About'     => $contact->context,
                'Account'   => $contact->user_id ? 'Signed-in user #'.$contact->user_id : 'Guest (not signed in)',
                'Submitted' => $this->stamp($contact->created_at),
                'IP'        => $contact->ip_address,
            ],
            'body_label'    => 'Message',
            'body'          => $contact->message,
            'action'        => ['label' => 'Open messages in admin', 'url' => url('/admin/messages')],
            'reply_to'      => $contact->email,
            'reply_to_name' => $contact->name,
            'template'      => 'contact_support',
            'user_id'       => $contact->user_id,
        ]);

        $contact->forceFill([
            'notified_at' => $sent ? now() : $contact->notified_at,
            'mail_error'  => $sent ? null : 'Support notification was not sent. See Email logs.',
        ])->save();

        if ($withConfirmation && $sent) {
            $this->confirmContact($contact);
        }

        return $sent;
    }

    /**
     * Confirmation to the visitor.
     *
     * Deliberately contains NOTHING the visitor typed. A form that echoes
     * free text to any address someone enters is a spam relay: the
     * "message" becomes the payload and the victim's address the target.
     * Capped per address per day for the same reason.
     */
    private function confirmContact(ContactMessage $contact): void
    {
        $key = 'contact-confirm:'.sha1(mb_strtolower($contact->email));

        if (RateLimiter::tooManyAttempts($key, self::CONFIRMATIONS_PER_DAY)) {
            return;
        }

        RateLimiter::hit($key, 86400);

        $sent = $this->mailer->send([
            'to'       => $contact->email,
            'subject'  => 'We received your message — Iconors ('.$contact->reference.')',
            'heading'  => 'Thanks, your message is with us',
            'intro'    => [
                'This is an automatic confirmation that the Iconors team received your message. We will reply to this address as soon as we can.',
            ],
            'fields'   => [
                'Reference' => $contact->reference,
                'Topic'     => $contact->topicLabel(),
                'Received'  => $this->stamp($contact->created_at),
            ],
            'outro'    => [
                'Need to add something? Just reply to this email and quote your reference.',
                'If you did not contact Iconors, someone may have entered your address by mistake and you can ignore this email.',
            ],
            'template' => 'contact_confirmation',
            'user_id'  => $contact->user_id,
        ]);

        if ($sent) {
            $contact->forceFill(['confirmed_at' => now()])->save();
        }
    }

    /* ---------------------------------------------------------------
     | Contributor applications
     | ------------------------------------------------------------- */

    public function applicationSubmitted(ContributorApplication $application, User $user): void
    {
        $this->mailer->afterResponse(function () use ($application, $user) {
            $this->mailer->send([
                'to'            => $this->mailer->supportAddress(),
                'subject'       => '[Iconors] New contributor application: '.$application->display_name,
                'heading'       => 'New contributor application',
                'intro'         => ['A user applied to become a contributor. It is waiting in the review queue.'],
                'fields'        => [
                    'Form'          => 'Contributor application',
                    'Full name'     => $application->full_name,
                    'Display name'  => $application->display_name,
                    'Account email' => $user->email,
                    'Country'       => $application->country,
                    'Portfolio'     => $application->portfolio_url,
                    'Website'       => $application->website,
                    'Specialties'   => implode(', ', array_map('strval', (array) $application->specialties)),
                    'Submitted'     => $this->stamp($application->created_at),
                ],
                'body_label'    => 'Biography',
                'body'          => (string) $application->biography,
                'action'        => ['label' => 'Review applications', 'url' => url('/admin/applications')],
                'reply_to'      => $user->email,
                'reply_to_name' => $application->full_name,
                'template'      => 'application_support',
                'user_id'       => $user->id,
            ]);

            // To the account's own address, so this cannot be aimed at a stranger.
            $this->mailer->send([
                'to'       => $user->email,
                'subject'  => 'We received your contributor application — Iconors',
                'heading'  => 'Your application is in',
                'intro'    => [
                    'Thanks for applying to contribute to Iconors. We usually review applications within 3 working days.',
                    'You can check the status at any time from your account.',
                ],
                'fields'   => [
                    'Display name' => $application->display_name,
                    'Submitted'    => $this->stamp($application->created_at),
                ],
                'action'   => ['label' => 'View application status', 'url' => url('/contributor/application')],
                'template' => 'application_confirmation',
                'user_id'  => $user->id,
            ]);
        });
    }

    /* ---------------------------------------------------------------
     | Payout requests
     | ------------------------------------------------------------- */

    public function payoutRequested(int $contributorId, ?User $user = null): void
    {
        $this->mailer->afterResponse(function () use ($contributorId, $user) {
            $payout = DB::table('payout_requests')
                ->where('contributor_id', $contributorId)
                ->orderByDesc('id')
                ->first();

            if (! $payout) {
                return;
            }

            $contributor = DB::table('contributors')->where('id', $contributorId)->first();
            $currency    = (string) ($payout->currency ?? $this->settings->get('default_currency', 'USD'));

            $this->mailer->send([
                'to'       => $this->mailer->supportAddress(),
                'subject'  => '[Iconors] Payout request '.$payout->reference.' — '.$currency.' '.number_format((float) $payout->amount, 2),
                'heading'  => 'New payout request',
                'intro'    => ['A contributor asked to withdraw their available balance.'],
                'fields'   => [
                    'Form'          => 'Payout request',
                    'Reference'     => $payout->reference,
                    'Contributor'   => $contributor->display_name ?? ('Contributor #'.$contributorId),
                    'Account email' => $user?->email,
                    'Amount'        => $currency.' '.number_format((float) $payout->amount, 2),
                    'Fee'           => $currency.' '.number_format((float) $payout->fee, 2),
                    'Net amount'    => $currency.' '.number_format((float) $payout->net_amount, 2),
                    'Requested'     => $this->stamp($payout->created_at),
                ],
                'body_label' => $payout->notes ? 'Contributor note' : '',
                'body'       => (string) ($payout->notes ?? ''),
                'reply_to'   => $user?->email,
                'reply_to_name' => $user?->name,
                'template'   => 'payout_support',
                'user_id'    => $user?->id,
            ]);
        });
    }

    /* ---------------------------------------------------------------
     | New accounts
     | ------------------------------------------------------------- */

    public function userRegistered(User $user): void
    {
        $this->mailer->afterResponse(function () use ($user) {
            $this->mailer->send([
                'to'       => $this->mailer->supportAddress(),
                'subject'  => '[Iconors] New sign-up: '.$user->email,
                'heading'  => 'New account created',
                'fields'   => [
                    'Form'    => 'Sign-up',
                    'Name'    => $user->name,
                    'Email'   => $user->email,
                    'User ID' => (string) $user->id,
                    'Joined'  => $this->stamp($user->created_at),
                ],
                'action'   => ['label' => 'Open in admin', 'url' => url('/admin/users/'.$user->id)],
                'template' => 'signup_support',
                'user_id'  => $user->id,
            ]);
        });
    }

    /* ---------------------------------------------------------------
     | Helpers
     | ------------------------------------------------------------- */

    private function stamp(mixed $time): string
    {
        $tz = (string) $this->settings->get('timezone', 'UTC');

        try {
            $moment = \Illuminate\Support\Carbon::parse($time ?? now());

            return $moment->copy()->timezone($tz)->format('j M Y, H:i').' ('.$tz.') · '.$moment->copy()->utc()->format('H:i').' UTC';
        } catch (\Throwable) {
            return now()->utc()->format('j M Y, H:i').' UTC';
        }
    }

    private function siteHost(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'iconors.com';
    }
}
