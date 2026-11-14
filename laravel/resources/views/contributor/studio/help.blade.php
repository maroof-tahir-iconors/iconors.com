@extends('layouts.studio')
@section('title', 'Contributor FAQs')

@php
$sections = [
    'getting-started' => ['Getting Started', [
        ['What can I sell on Iconors?', 'Icons, illustrations, 3D icons and Lottie animations that you created yourself (or hold the full rights to). Every asset is reviewed by our team before it goes live in the store.'],
        ['Which asset types can I upload straight away?', 'Lottie Animations are open to every approved contributor. Icons, Illustrations and 3D Icons need a quick portfolio check: hover the card on your dashboard, click "Request to Upload", add your portfolio link and we will review it — usually within a few working days.'],
        ['How do I upload?', 'Open the asset type from the sidebar, choose "Upload New" and drag & drop your files (or click to browse). You can drop many files at once — files with the same name are grouped into one asset, e.g. rocket.svg + rocket.eps.'],
        ['How should I name my files?', 'Name each file after what it shows — a lock is "lock.svg", a shopping cart is "shopping-cart.svg". The file name becomes the asset title, so keep it short and descriptive. Numbers and pack names are not needed.'],
    ]],
    'approval' => ['Review & Approval', [
        ['How long does a review take?', 'Most uploads are reviewed within 3–5 working days. You can follow them under each asset type → Under Review.'],
        ['What does "Need to Improve" mean?', 'The reviewer found something small to fix (for example an alignment issue or a file that does not open). Read the note on the asset, fix the file and upload it again.'],
        ['Why was my asset rejected?', 'Common reasons: the work is not original, it duplicates an existing asset, the quality is below our standard, the file contains embedded images or scripts, or the Lottie has more than 500 frames. The reason is shown on each rejected asset.'],
        ['Can I delete an asset?', 'You can delete drafts and rejected uploads yourself. Published assets cannot be deleted from the dashboard because customers may already hold a licence for them — contact support if you need one removed.'],
    ]],
    'payout' => ['Earnings & Payout', [
        ['How do I earn?', 'You earn a share of every premium sale and every subscriber ("Pro") download of your work. Free downloads help your ranking but do not earn royalties. See Statistics → Sales Activities for every download.'],
        ['When can I withdraw?', 'Earnings become available after the holding period (shown on the Payout page), which protects against refunds. Once your available balance reaches the minimum payout, the Request Cashout button unlocks.'],
        ['How am I paid?', 'Payouts are sent to the payout account on your profile. Finance reviews each request; you can follow its status under Payout → Cashout History.'],
    ]],
    'uploads' => ['Upload Guidelines', [
        ['Icons', 'Clean SVG only, no embedded raster images, no scripts. Keep a consistent grid and stroke width across a set.'],
        ['Illustrations', 'An SVG is required (used for the preview). Add the editable EPS, AI or PDF with the same file name.'],
        ['3D Icons', 'A transparent PNG render of at least 1024 × 1024 is required. Add the source (BLEND, GLB, FBX or OBJ) with the same file name.'],
        ['Lottie Animations', 'Upload the Lottie JSON (max 500 frames, no embedded images). Add the AEP, .lottie or ZIP with the same file name if you have them.'],
    ]],
    'terms' => ['Contributor Terms', [
        ['Ownership', 'You confirm that you created the work or hold all rights needed to sell it, and that it does not infringe anyone else\'s rights, trademarks or likeness.'],
        ['Licence', 'You keep the copyright. You grant Iconors the right to display, distribute and license your assets to customers under the Iconors licence.'],
        ['AI training', 'You choose under Shop Details whether your work may be used for AI training. You can change this at any time.'],
        ['Account', 'We may remove assets or suspend accounts that break these terms. Payouts on sales later refunded or charged back may be reversed.'],
    ]],
];
@endphp

@section('content')
<div class="cs-help__hero">
    <h1>How can we help you?</h1>
    <div class="d-flex gap-2 flex-wrap">
        <input class="cs-input flex-grow-1" style="background:#fff;color:#0b0a1a;min-width:220px" type="search" placeholder="Search for answers" aria-label="Search the FAQs" oninput="document.querySelectorAll('.cs-faq details').forEach(function(d){d.style.display=d.textContent.toLowerCase().includes(this.value.toLowerCase())?'':'none'},this)">
        <a class="cs-btn cs-btn--white" href="{{ route('contact') }}">Contact Support</a>
    </div>
</div>

<div class="cs-help">
    <nav class="cs-help__nav">
        @foreach ($sections as $id => [$title])<a href="#{{ $id }}">{{ $title }}</a>@endforeach
    </nav>
    <div class="cs-faq">
        <h1 class="cs-page-title mb-1">Contributor FAQs</h1>
        <p class="cs-sub">Answers to common questions for contributors, including submitting content, approval guidelines, payouts and account management.</p>
        @foreach ($sections as $id => [$title, $items])
            <h2 id="{{ $id }}">{{ $title }}</h2>
            @foreach ($items as [$q, $a])
                <details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
            @endforeach
        @endforeach
    </div>
</div>
@endsection
