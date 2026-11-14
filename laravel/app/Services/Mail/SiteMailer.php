<?php

namespace App\Services\Mail;

use App\Services\Settings\SettingsService;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The single place the site sends email from.
 *
 * The mail credentials live in the settings table (Admin → Settings → Email),
 * encrypted at rest, not in config files. Laravel's mailer only knows about
 * config, so every send has to copy the saved settings into config first.
 * Doing that here means the contact form, the notifications and anything
 * added later all use exactly what the administrator saved.
 *
 * send() never throws. A broken mail server must not turn a successful form
 * submission into an error page — the caller has already stored the data,
 * so a failed send is recorded in `email_logs` and reported as `false`.
 */
class SiteMailer
{
    /** Site-wide ceiling on outgoing mail, so a flood cannot burn the mailbox's reputation. */
    private const HOURLY_CAP = 120;

    private bool $configured = false;

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /** Where form submissions and notifications are delivered. */
    public function supportAddress(): string
    {
        $address = trim((string) $this->settings->get('support_email', ''));

        return filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : 'support@iconors.com';
    }

    /** Copies the saved mail settings into the live config for this request. */
    public function configure(): void
    {
        if ($this->configured) {
            return;
        }

        $driver     = (string) $this->settings->get('mail_driver', 'log');
        $port       = (int) $this->settings->get('mail_port', 587);
        $encryption = (string) $this->settings->get('mail_encryption', 'tls');
        $fromEmail  = trim((string) $this->settings->get('mail_from_email', ''));

        $before = [config('mail.default'), config('mail.mailers.smtp'), config('mail.from')];

        config([
            'mail.default'                 => $driver,
            'mail.mailers.smtp.host'       => $this->settings->get('mail_host'),
            'mail.mailers.smtp.port'       => $port,
            'mail.mailers.smtp.username'   => $this->settings->get('mail_username'),
            'mail.mailers.smtp.password'   => $this->settings->get('mail_password'),
            // Laravel 12 picks the transport from `scheme`. "smtps" is TLS
            // from the first byte (port 465); "smtp" upgrades with STARTTLS
            // when the server offers it (port 587).
            'mail.mailers.smtp.scheme'     => ($encryption === 'ssl' || $port === 465) ? 'smtps' : 'smtp',
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            // A slow mail server must not hang a visitor's request.
            'mail.mailers.smtp.timeout'    => 15,
            'mail.from.address'            => filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? $fromEmail : $this->supportAddress(),
            'mail.from.name'               => self::oneLine((string) $this->settings->get('mail_from_name', 'Iconors'), 120),
        ]);

        // Drop any mailer that was built before the saved settings were
        // applied — it would still be holding the old connection details.
        if ($before !== [config('mail.default'), config('mail.mailers.smtp'), config('mail.from')]) {
            app('mail.manager')->forgetMailers();
        }

        $this->configured = true;
    }

    /**
     * Sends one email and records the outcome in `email_logs`.
     *
     * Keys: to, subject, heading (required) · intro[], fields[label => value],
     * body_label, body, outro[], action[label, url], reply_to, reply_to_name,
     * template, user_id (optional).
     *
     * Every value is escaped by the Blade template; nothing here is trusted.
     */
    public function send(array $mail): bool
    {
        $to       = trim((string) ($mail['to'] ?? ''));
        $subject  = self::oneLine((string) ($mail['subject'] ?? ''), 200);
        $template = (string) ($mail['template'] ?? 'notice');
        $userId   = $mail['user_id'] ?? null;

        // An address with a line break in it is the classic header-injection
        // attempt. FILTER_VALIDATE_EMAIL rejects those outright.
        if (! filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '') {
            $this->log($to !== '' ? self::oneLine($to, 250) : '(missing)', $subject, $template, 'failed', $userId, 'Invalid recipient or empty subject.');

            return false;
        }

        if (RateLimiter::tooManyAttempts('site-mail:hourly', self::HOURLY_CAP)) {
            $this->log($to, $subject, $template, 'failed', $userId, 'Skipped: hourly sending cap reached.');
            Log::error('Mail skipped: hourly sending cap reached.', ['template' => $template]);

            return false;
        }

        $replyTo     = trim((string) ($mail['reply_to'] ?? ''));
        $replyToName = self::oneLine((string) ($mail['reply_to_name'] ?? ''), 120);

        if ($replyTo === '' || ! filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo     = trim((string) $this->settings->get('mail_reply_to', ''));
            $replyToName = '';
        }

        $data = [
            'siteName'  => self::oneLine((string) $this->settings->get('site_name', 'Iconors'), 80),
            'siteUrl'   => rtrim((string) config('app.url'), '/'),
            'heading'   => self::oneLine((string) ($mail['heading'] ?? $subject), 200),
            'intro'     => array_values(array_filter((array) ($mail['intro'] ?? []), 'is_string')),
            'fields'    => array_filter((array) ($mail['fields'] ?? []), fn ($v) => $v !== null && $v !== ''),
            'bodyLabel' => (string) ($mail['body_label'] ?? ''),
            'bodyText'  => (string) ($mail['body'] ?? ''),
            'outro'     => array_values(array_filter((array) ($mail['outro'] ?? []), 'is_string')),
            'action'    => $mail['action'] ?? null,
        ];

        try {
            $this->configure();

            Mail::send(
                ['html' => 'emails.notice', 'text' => 'emails.notice-text'],
                $data,
                function (Message $message) use ($to, $subject, $replyTo, $replyToName) {
                    $message->to($to)->subject($subject);

                    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                        $message->replyTo($replyTo, $replyToName !== '' ? $replyToName : null);
                    }
                },
            );

            RateLimiter::hit('site-mail:hourly', 3600);
            $this->log($to, $subject, $template, 'sent', $userId);

            return true;
        } catch (\Throwable $e) {
            $this->log($to, $subject, $template, 'failed', $userId, $e->getMessage());

            // No recipient, subject or body here: the log file is not the
            // place for visitors' personal data.
            Log::error('Mail send failed.', [
                'template' => $template,
                'error'    => mb_substr($e->getMessage(), 0, 500),
            ]);

            return false;
        }
    }

    /**
     * Runs the callback once the response has been sent to the browser, so
     * the visitor is not kept waiting on the mail server.
     */
    public function afterResponse(callable $callback): void
    {
        $ran = false;

        app()->terminating(function () use ($callback, &$ran) {
            // Run once. Where the application outlives a single request
            // (tests, long-running workers) the hook would otherwise fire
            // again on every later request and re-send the email.
            if ($ran) {
                return;
            }

            $ran = true;

            try {
                $callback($this);
            } catch (\Throwable $e) {
                Log::error('Deferred mail task failed.', ['error' => mb_substr($e->getMessage(), 0, 500)]);
            }
        });
    }

    /** Collapses a value onto one line and strips control characters. */
    public static function oneLine(string $value, int $max = 200): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s{2,}/u', ' ', $value) ?? '');

        return mb_substr($value, 0, $max);
    }

    private function log(string $to, string $subject, string $template, string $status, mixed $userId, ?string $error = null): void
    {
        try {
            DB::table('email_logs')->insert([
                'recipient'     => mb_substr($to, 0, 250),
                'subject'       => mb_substr($subject, 0, 290),
                'template_key'  => mb_substr($template, 0, 120),
                'provider'      => mb_substr((string) $this->settings->get('mail_driver', 'log'), 0, 60),
                'status'        => $status,
                'user_id'       => is_numeric($userId) ? (int) $userId : null,
                'error_message' => $error !== null ? mb_substr($error, 0, 1000) : null,
                'sent_at'       => $status === 'sent' ? now() : null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Could not write to email_logs.', ['error' => mb_substr($e->getMessage(), 0, 300)]);
        }
    }
}
