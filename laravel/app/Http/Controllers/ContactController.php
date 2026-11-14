<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Services\Mail\SiteMailer;
use App\Services\Mail\SiteNotifier;
use App\Services\Tools\ToolRegistry;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Public contact form — support requests, feedback, tool questions.
 *
 * Order of operations in store() is the whole design:
 *   1. validate and screen for bots,
 *   2. SAVE the message,
 *   3. only then try to email it.
 * So a mail outage delays a message; it cannot lose one.
 */
class ContactController extends Controller
{
    /** Submissions allowed per IP address per ten minutes. */
    private const PER_IP = 5;

    /** Submissions allowed per email address per hour. */
    private const PER_EMAIL = 4;

    /** A human needs at least this long to fill the form in. */
    private const MIN_SECONDS = 3;

    public function create(Request $request, SiteMailer $mailer)
    {
        $topic = (string) $request->query('topic', '');
        $tool  = (string) $request->query('tool', '');

        return view('pages.contact', [
            'topics'       => ContactMessage::TOPICS,
            'topic'        => array_key_exists($topic, ContactMessage::TOPICS) ? $topic : 'general',
            'tool'         => in_array($tool, ToolRegistry::keys(), true) ? $tool : null,
            'supportEmail' => $mailer->supportAddress(),
            // Encrypted render time: lets store() tell a person from a
            // script that posts the form the instant it loads.
            'startedAt'    => Crypt::encryptString((string) time()),
            'user'         => $request->user(),
        ]);
    }

    public function store(Request $request, SiteNotifier $notifier)
    {
        $ipKey = 'contact:ip:'.sha1((string) $request->ip());

        if (RateLimiter::tooManyAttempts($ipKey, self::PER_IP)) {
            return back()->withInput()->withErrors([
                'form' => 'You have sent several messages in a short time. Please wait '
                    .max(1, (int) ceil(RateLimiter::availableIn($ipKey) / 60)).' minutes and try again.',
            ]);
        }

        // Line breaks are trimmed out of single-line fields before the
        // rules run, so they can never reach an email header.
        $request->merge([
            'name'    => SiteMailer::oneLine((string) $request->input('name', ''), 500),
            'subject' => SiteMailer::oneLine((string) $request->input('subject', ''), 500),
            'email'   => trim((string) $request->input('email', '')),
        ]);

        $validated = $request->validate([
            'name'    => ['required', 'string', 'min:2', 'max:120'],
            'email'   => ['required', 'string', 'email:rfc', 'max:255'],
            'topic'   => ['required', Rule::in(array_keys(ContactMessage::TOPICS))],
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'tool'    => ['nullable', Rule::in(ToolRegistry::keys())],
        ], [
            'message.min' => 'Please add a little more detail so we can help (at least 10 characters).',
        ]);

        // Too fast to be a person, or a form that was not rendered by us.
        if (! $this->tookLongEnough((string) $request->input('_started', ''))) {
            return back()->withInput()->withErrors([
                'form' => 'That did not go through. Please check your message and press send again.',
            ]);
        }

        if (preg_match_all('~https?://|www\.~i', $validated['message']) > 5) {
            return back()->withInput()->withErrors([
                'message' => 'Please remove some of the links — a message can include up to five.',
            ]);
        }

        $emailKey = 'contact:email:'.sha1(mb_strtolower($validated['email']));

        if (RateLimiter::tooManyAttempts($emailKey, self::PER_EMAIL)) {
            return back()->withInput()->withErrors([
                'form' => 'We already have several recent messages from this address. Please wait an hour before sending another.',
            ]);
        }

        RateLimiter::hit($ipKey, 600);
        RateLimiter::hit($emailKey, 3600);

        // The honeypot is a field people cannot see. If it has a value a
        // script filled it in. The message is still STORED (as spam) rather
        // than dropped, so a false positive is recoverable from the admin.
        $isSpam = trim((string) $request->input('company_website', '')) !== '';

        $contact = ContactMessage::create([
            'reference'  => 'IC-'.strtoupper(Str::random(8)),
            'user_id'    => $request->user()?->id,
            'name'       => $validated['name'],
            'email'      => mb_strtolower($validated['email']),
            'topic'      => $validated['topic'],
            'subject'    => $validated['subject'],
            'message'    => $validated['message'],
            'context'    => ! empty($validated['tool']) ? 'Tool: '.$validated['tool'] : null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'status'     => $isSpam ? 'spam' : 'new',
        ]);

        if (! $isSpam) {
            $notifier->contactReceived($contact);
        }

        return redirect()
            ->route('contact')
            ->with('contact_sent', $contact->reference);
    }

    private function tookLongEnough(string $token): bool
    {
        try {
            $started = (int) Crypt::decryptString($token);
        } catch (DecryptException) {
            return false;
        }

        $elapsed = time() - $started;

        return $elapsed >= self::MIN_SECONDS && $elapsed <= 86400;
    }
}
