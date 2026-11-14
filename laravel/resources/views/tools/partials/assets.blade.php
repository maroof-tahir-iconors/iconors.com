{{--
    CSS and shared script for the tool pages.

    Plain files under public/ico-ext rather than Vite entries: the server
    has no Node toolchain, so nothing added to resources/ would be built.
    ToolRegistry::asset() appends each file's modified time, so replacing a
    file is all it takes to get it past browser caches.

    $scripts lists the page's own files, in load order. Everything is
    deferred, so the order here is the execution order.
--}}
@push('meta')
    <link rel="stylesheet" href="{{ \App\Services\Tools\ToolRegistry::asset('tools.css') }}">
@endpush

@push('scripts')
    <script defer src="{{ \App\Services\Tools\ToolRegistry::asset('js/tools-core.js') }}"></script>
    @foreach ($scripts ?? [] as $script)
        <script defer src="{{ \App\Services\Tools\ToolRegistry::asset($script) }}"></script>
    @endforeach
@endpush
