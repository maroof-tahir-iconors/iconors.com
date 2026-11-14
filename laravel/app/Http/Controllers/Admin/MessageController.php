<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\Mail\SiteNotifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Contact form inbox.
 *
 * Email is how support normally hears about a message, but email can fail.
 * This screen is the copy that cannot: every submission is listed here
 * whether or not the notification went out, with a retry for the ones
 * that did not.
 */
class MessageController extends Controller
{
    public function index(Request $request)
    {
        $this->gate($request);

        $status = (string) $request->query('status', '');

        $messages = ContactMessage::query()
            ->when(in_array($status, ContactMessage::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($status === 'unsent', fn ($q) => $q->whereNull('notified_at')->where('status', '!=', 'spam'))
            ->when($status === '', fn ($q) => $q->where('status', '!=', 'spam'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.messages.index', [
            'messages' => $messages,
            'status'   => $status,
            'counts'   => [
                'new'    => ContactMessage::where('status', 'new')->count(),
                'unsent' => ContactMessage::whereNull('notified_at')->where('status', '!=', 'spam')->count(),
                'spam'   => ContactMessage::where('status', 'spam')->count(),
                'total'  => ContactMessage::count(),
            ],
        ]);
    }

    public function update(Request $request, ContactMessage $message)
    {
        $this->gate($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(ContactMessage::STATUSES)],
        ]);

        $message->update(['status' => $validated['status']]);

        return back()->with('status', "Message {$message->reference} marked as {$validated['status']}.");
    }

    /** Re-sends the support notification for a message that was never emailed. */
    public function resend(Request $request, ContactMessage $message, SiteNotifier $notifier)
    {
        $this->gate($request);

        if ($notifier->deliverContact($message)) {
            return back()->with('status', "Message {$message->reference} emailed to support.");
        }

        return back()->withErrors([
            'resend' => "Could not email message {$message->reference}. Check Settings → Email and send a test.",
        ]);
    }

    private function gate(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && ($user->can('users.view') || $user->can('settings.manage')), 403);
    }
}
