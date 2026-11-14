@extends('layouts.app')

@section('title', 'Contact Iconors — Support, Feedback and Questions')
@section('meta_description', 'Contact the Iconors team. Send a support request, feedback, a question about licensing or the free tools, and we will reply by email.')

@section('content')
<div class="container-ico section">

    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Contact</li>
        </ol>
    </nav>

    <header class="mb-4">
        <h1 class="h2 mb-2">Contact us</h1>
        <p class="mb-0" style="color:var(--ico-muted);max-width:60ch">
            Support requests, feedback, questions about licensing or the free tools — send it here and
            we will reply by email.
        </p>
    </header>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-8">

            @if (session('contact_sent'))
                <div class="alert alert-success d-flex gap-2 align-items-start" role="status">
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <div>
                        <strong>Message sent.</strong>
                        Thanks — we have it and will reply by email.
                        Your reference is <strong>{{ session('contact_sent') }}</strong>.
                    </div>
                </div>
            @endif

            @error('form')
                <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
                    <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                    <div>{{ $message }}</div>
                </div>
            @enderror

            <div class="ico-card p-4 p-md-5">
                <form method="POST" action="{{ route('contact.store') }}" id="contact-form">
                    @csrf
                    <input type="hidden" name="_started" value="{{ $startedAt }}">
                    @if ($tool)
                        <input type="hidden" name="tool" value="{{ $tool }}">
                    @endif

                    {{-- Honeypot. Invisible to people and skipped by screen
                         readers and the keyboard; scripts that fill every
                         field give themselves away. --}}
                    <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
                        <label for="company_website">Leave this field empty</label>
                        <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off" value="">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="contact-name" class="form-label">Your name</label>
                            <input id="contact-name" name="name" type="text" required minlength="2" maxlength="120"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user?->name) }}" autocomplete="name">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="contact-email" class="form-label">Email address</label>
                            <input id="contact-email" name="email" type="email" required maxlength="255"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $user?->email) }}" autocomplete="email"
                                   aria-describedby="contact-email-help">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div id="contact-email-help" class="form-text">We reply to this address.</div>
                        </div>

                        <div class="col-md-5">
                            <label for="contact-topic" class="form-label">What is it about?</label>
                            <select id="contact-topic" name="topic" required
                                    class="form-select @error('topic') is-invalid @enderror">
                                @foreach ($topics as $key => $label)
                                    <option value="{{ $key }}" @selected(old('topic', $topic) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('topic')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-7">
                            <label for="contact-subject" class="form-label">Subject</label>
                            <input id="contact-subject" name="subject" type="text" required minlength="3" maxlength="160"
                                   class="form-control @error('subject') is-invalid @enderror"
                                   value="{{ old('subject') }}">
                            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label for="contact-message" class="form-label">Message</label>
                            <textarea id="contact-message" name="message" rows="7" required minlength="10" maxlength="5000"
                                      class="form-control @error('message') is-invalid @enderror"
                                      aria-describedby="contact-message-help">{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div id="contact-message-help" class="form-text">
                                @if ($tool)
                                    About the {{ str_replace('-', ' ', $tool) }} tool — tell us what you were doing and what happened.
                                @else
                                    The more detail you give, the faster we can help. Up to 5,000 characters.
                                @endif
                            </div>
                        </div>

                        <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                            <button type="submit" class="btn btn-ico-primary" id="contact-submit">Send message</button>
                            <span class="small" style="color:var(--ico-muted)">
                                By sending this you agree to our
                                <a href="{{ route('page', 'privacy') }}">privacy policy</a>.
                            </span>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <aside class="col-lg-4">
            <div class="ico-card p-4 mb-3">
                <h2 class="h6 mb-2">Prefer email?</h2>
                <p class="small mb-0" style="color:var(--ico-muted)">
                    Write to
                    <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                    and it reaches the same team.
                </p>
            </div>

            <div class="ico-card p-4">
                <h2 class="h6 mb-2">Quick answers</h2>
                <ul class="list-unstyled small mb-0" role="list">
                    <li class="mb-2"><a href="{{ route('page', 'license') }}">Licence information</a></li>
                    <li class="mb-2"><a href="{{ route('page', 'refunds') }}">Refund policy</a></li>
                    <li class="mb-2"><a href="{{ route('pricing') }}">Pricing and plans</a></li>
                    <li><a href="{{ route('tools.index') }}">Free design tools</a></li>
                </ul>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Stops a double click sending the same message twice. The form works
// without this — it is only a guard.
(function () {
    var form = document.getElementById('contact-form');
    var button = document.getElementById('contact-submit');
    if (!form || !button) { return; }

    form.addEventListener('submit', function () {
        button.disabled = true;
        button.textContent = 'Sending…';
    });
})();
</script>
@endpush
