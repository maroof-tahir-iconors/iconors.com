{{--
    Below the tool: how it works, questions, and the other tools.

    The questions are also emitted as FAQPage structured data. The JSON is
    built from the same array that renders the visible answers, so the two
    can never say different things.
--}}
<section class="tl-more">
    <div class="container-ico">

        <div class="section-head">
            <div>
                <h2>How it works</h2>
            </div>
        </div>

        <ol class="tl-steps list-unstyled mb-5">
            @foreach ($steps as $i => $step)
                <li>
                    <div class="ico-card tl-step">
                        <span class="tl-step__n" aria-hidden="true">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <div>
                            <h3>{{ $step[0] }}</h3>
                            <p>{{ $step[1] }}</p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>

        <div class="section-head">
            <div>
                <h2>Questions</h2>
            </div>
        </div>

        <div class="tl-faq mb-5">
            @foreach ($faqs as $faq)
                <details>
                    <summary>{{ $faq[0] }}</summary>
                    <p>{{ $faq[1] }}</p>
                </details>
            @endforeach
        </div>

        <div class="section-head">
            <div>
                <h2>More free tools</h2>
                <p>All of them run in your browser.</p>
            </div>
            <a class="section-head__link" href="{{ route('tools.index') }}">
                All tools <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <div class="tl-cards">
            @foreach ($tools as $other)
                @continue($other['key'] === $tool['key'])
                @include('tools.partials.card', ['item' => $other, 'level' => 'h3'])
            @endforeach
        </div>
    </div>
</section>

@php
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $faq[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
        ], $faqs),
    ];
@endphp
<script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
