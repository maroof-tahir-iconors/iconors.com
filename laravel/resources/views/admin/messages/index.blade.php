@extends('layouts.admin')

@section('title', 'Messages')
@section('subtitle', 'Everything sent through the contact form, whether or not the email went out.')

@section('content')

    <div class="row g-3 mb-4">
        @foreach ([
            ['New',          $counts['new'],    'envelope',             'new'],
            ['Not emailed',  $counts['unsent'], 'exclamation-triangle', 'unsent'],
            ['Spam caught',  $counts['spam'],   'shield-check',         'spam'],
            ['All messages', $counts['total'],  'inbox',                ''],
        ] as [$label, $value, $icon, $filter])
            <div class="col-6 col-xl-3">
                <a class="ico-card stat-card d-block text-decoration-none"
                   href="{{ route('admin.messages.index', array_filter(['status' => $filter])) }}">
                    <span class="stat-card__label">
                        <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-{{ $icon }}"></i></span>
                        {{ $label }}
                    </span>
                    <span class="stat-card__value">{{ number_format($value) }}</span>
                </a>
            </div>
        @endforeach
    </div>

    @if ($messages->isEmpty())
        <div class="empty-state ico-card">
            <div class="empty-state__icon"><i class="bi bi-inbox" aria-hidden="true"></i></div>
            <h2 class="h6">No messages here</h2>
            <p class="text-muted small mb-0">
                {{ $status === '' ? 'Nobody has used the contact form yet.' : 'Nothing matches this filter.' }}
            </p>
        </div>
    @else
        <div class="ico-card p-0">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">From</th>
                            <th scope="col">Message</th>
                            <th scope="col">Email</th>
                            <th scope="col">Received</th>
                            <th scope="col">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($messages as $item)
                            <tr>
                                <td style="min-width:180px">
                                    <div class="fw-semibold">{{ $item->name }}</div>
                                    <a href="mailto:{{ $item->email }}?subject={{ rawurlencode('Re: '.$item->subject.' ('.$item->reference.')') }}">{{ $item->email }}</a>
                                    <div class="small text-muted">{{ $item->reference }}</div>
                                </td>
                                <td style="min-width:280px">
                                    <span class="badge text-bg-secondary">{{ $item->topicLabel() }}</span>
                                    @if ($item->status === 'spam')
                                        <span class="badge text-bg-danger">Spam</span>
                                    @elseif ($item->status !== 'new')
                                        <span class="badge text-bg-light">{{ ucfirst($item->status) }}</span>
                                    @endif
                                    <details class="mt-1">
                                        <summary class="fw-semibold">{{ $item->subject }}</summary>
                                        <div class="small mt-2" style="white-space:pre-wrap;word-break:break-word">{{ $item->message }}</div>
                                        @if ($item->context)
                                            <div class="small text-muted mt-2">{{ $item->context }}</div>
                                        @endif
                                    </details>
                                </td>
                                <td>
                                    @if ($item->notified_at)
                                        <span class="badge text-bg-success">Emailed</span>
                                    @elseif ($item->status === 'spam')
                                        <span class="badge text-bg-light">Held</span>
                                    @else
                                        <span class="badge text-bg-warning">Not emailed</span>
                                    @endif
                                </td>
                                <td>{{ $item->created_at->diffForHumans() }}</td>
                                <td style="min-width:200px">
                                    <div class="d-flex flex-wrap gap-1">
                                        @if (! $item->notified_at)
                                            <form method="POST" action="{{ route('admin.messages.resend', $item) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-ico-primary btn-sm">Email to support</button>
                                            </form>
                                        @endif

                                        @foreach (['read' => 'Mark read', 'replied' => 'Replied', 'spam' => 'Spam', 'new' => 'Not spam'] as $to => $label)
                                            @continue($to === $item->status)
                                            @continue($to === 'new' && $item->status !== 'spam')
                                            @continue($to !== 'new' && $item->status === 'spam')
                                            <form method="POST" action="{{ route('admin.messages.update', $item) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="{{ $to }}">
                                                <button type="submit" class="btn btn-ico-outline btn-sm">{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $messages->links() }}
        </div>
    @endif
@endsection
