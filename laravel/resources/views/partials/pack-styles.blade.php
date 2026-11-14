{{--
    Styles for pack cards and the pack pages.

    Inline on purpose: the server has no Node toolchain, so a rule added to
    app.scss would not reach visitors until the next local build. Pushed onto
    the "meta" stack, which the layout prints inside <head>, and wrapped in
    once so a page with many cards emits it a single time.

    These can move into resources/scss/app.scss unchanged at the next build;
    delete this partial and its includes when they do.

    Everything themable reads the --ico-* tokens, so light mode, dark mode and
    admin branding all apply without anything extra here. The preview tiles
    stay light in both themes for the same reason asset tiles do: most
    artwork is dark-on-transparent and vanishes on a dark surface.
--}}
@once
@push('meta')
<style id="ico-pack-styles">
.ico-card.pack-card{position:relative;display:flex;flex-direction:column;height:100%}
.ico-card.pack-card:hover,.ico-card.pack-card:focus-within{transform:translateY(-3px);box-shadow:var(--ico-shadow-card-hover);border-color:rgba(60,247,162,.6)}
.pack-card__mosaic{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,1fr);gap:6px;padding:6px}
.pack-card__side{display:grid;grid-template-rows:repeat(3,minmax(0,1fr));gap:6px;min-height:0}
.pack-card__cell{position:relative;display:grid;place-items:center;min-width:0;min-height:0;overflow:hidden;border-radius:10px;background:#f2f3f7;color:#a9aebb}
.pack-card__cell--lead{aspect-ratio:1/1;background:radial-gradient(120% 120% at 50% 0%,rgba(60,247,162,.14) 0%,transparent 62%),#f2f3f7}
.pack-card__cell .ico-lottie{inline-size:76%}
.pack-card__cell img{inline-size:70%;block-size:70%;object-fit:contain}
.pack-card__cell--lead .ico-lottie,.pack-card__cell--lead img{transition:transform .35s cubic-bezier(.34,1.2,.64,1)}
.pack-card:hover .pack-card__cell--lead .ico-lottie,.pack-card:hover .pack-card__cell--lead img{transform:scale(1.05)}
.pack-card__cell .bi{font-size:1.1rem;opacity:.5}
.pack-card__badge{position:absolute;inset-block-start:.8rem;inset-inline-start:.8rem;z-index:2}
.pack-card__body{display:flex;align-items:baseline;justify-content:space-between;gap:.75rem;padding:.7rem .9rem .85rem;border-top:1px solid var(--ico-border);background:var(--ico-surface)}
.pack-card__title{flex:1 1 auto;min-width:0;margin:0;font-size:.92rem;font-weight:650;line-height:1.35;color:var(--ico-heading);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pack-card__title a{text-decoration:none}
.pack-card__count{flex:0 0 auto;font-size:.78rem;color:var(--ico-muted);white-space:nowrap}
.section-head h2 .pack-rail__meta{margin-inline-start:.6rem;font-size:.85rem;font-weight:500;color:var(--ico-muted);white-space:nowrap}
.pack-chips{display:flex;gap:.5rem;overflow-x:auto;padding-block-end:.35rem;scrollbar-width:thin}
.pack-chips .btn{flex:0 0 auto}
.pack-chips .btn.is-active{background:var(--ico-primary);color:var(--ico-on-primary);border-color:transparent}
.pack-hero{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:1rem 2rem;padding:clamp(1.1rem,3vw,1.75rem)}
.pack-hero__title{margin:0 0 .35rem;font-size:clamp(1.4rem,3vw,2rem);font-weight:750;color:var(--ico-heading)}
.pack-hero__meta{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem .9rem;margin:0;font-size:.9rem;color:var(--ico-muted)}
.pack-hero__text{margin:.75rem 0 0;max-width:62ch;font-size:.95rem;color:var(--ico-body)}
@media (max-width:420px){.pack-card__body{flex-direction:column;align-items:flex-start;gap:.1rem}.pack-card__title{max-width:100%}}
</style>
@endpush
@endonce
