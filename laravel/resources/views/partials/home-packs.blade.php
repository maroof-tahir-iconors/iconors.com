{{-- ---------------------------------------------------------------
    Pack rails

    The homepage shows complete packs only — never a loose animation or
    icon. Each card opens the pack page, which lists everything inside.

    Rails are built in HomeController::packRails(): one per asset type
    first, then one per large category. A rail with nothing in it is
    never sent, so there is no empty state to handle here.
--------------------------------------------------------------- --}}
@include('partials.pack-styles')

@foreach (($packRails ?? []) as $railIndex => $rail)
    <section class="section--tight" @if($railIndex > 0) data-ico-reveal @endif>
        <div class="container-ico">
            <div class="section-head">
                <div>
                    <h2>
                        {{ $rail['title'] }}
                        <span class="pack-rail__meta">{{ $rail['meta'] }}</span>
                    </h2>
                    <p>{{ $rail['blurb'] }}</p>
                </div>
                <a class="section-head__link" href="{{ $rail['url'] }}">
                    View all <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
                @foreach ($rail['packs'] as $pack)
                    <div class="col">
                        <x-pack-card :pack="$pack" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endforeach
