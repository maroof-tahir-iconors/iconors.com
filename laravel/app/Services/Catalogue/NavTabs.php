<?php

namespace App\Services\Catalogue;

use App\Models\AssetType;
use Illuminate\Support\Facades\Cache;

/**
 * The fixed catalogue tabs in the site header: Icons, Illustrations,
 * Animations and 3D Assets.
 *
 * The header always shows all four, in this order, whatever the catalogue
 * holds. What changes is where each one leads:
 *
 *   - a tab whose asset type has at least one published asset opens the
 *     normal browse page, filtered to that type;
 *   - a tab with nothing published yet opens its "coming soon" page.
 *
 * Nothing has to be switched on by hand. The first published asset of a
 * type flips its tab from the coming-soon page to the real listing the
 * next time the cache below refreshes.
 */
class NavTabs
{
    private const CACHE_KEY = 'nav.tabs.live-types';

    /** Ten minutes: the header is on every page, so this must not be a query per request. */
    private const CACHE_TTL = 600;

    /**
     * `types` is an ordered list of asset-type slugs. The first one that
     * has published assets is the one the tab links to.
     */
    private const TABS = [
        'icons' => [
            'label' => 'Icons',
            'icon' => 'bi-grid-3x3-gap',
            'types' => ['svg-icon', 'icon-pack'],
            'pitch' => 'Crisp, consistent icon sets for interfaces, decks and docs — in SVG and PNG.',
        ],
        'illustrations' => [
            'label' => 'Illustrations',
            'icon' => 'bi-palette',
            'types' => ['illustration'],
            'pitch' => 'Editable vector illustrations to give landing pages and products some personality.',
        ],
        'animations' => [
            'label' => 'Animations',
            'icon' => 'bi-play-circle',
            'types' => ['lottie-animation', 'animated-icon'],
            'pitch' => 'Lightweight Lottie animations that drop straight into websites and apps.',
        ],
        '3d-assets' => [
            'label' => '3D Assets',
            'icon' => 'bi-box',
            'types' => ['3d-icon'],
            'pitch' => '3D icons and objects rendered from every angle, ready for hero sections and mockups.',
        ],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::TABS);
    }

    /**
     * Every tab, resolved for the current request.
     *
     * @return list<array{key:string,label:string,icon:string,pitch:string,ready:bool,type:?string,url:string,active:bool}>
     */
    public function all(): array
    {
        $live = $this->liveTypes();
        $request = request();
        $currentType = $request->query('type');
        $currentTab = $request->route('tab');
        $onListing = $request->routeIs('assets.index', 'packs.index');

        $tabs = [];

        foreach (self::TABS as $key => $tab) {
            $type = null;

            foreach ($tab['types'] as $slug) {
                if (in_array($slug, $live, true)) {
                    $type = $slug;
                    break;
                }
            }

            $tabs[] = [
                'key' => $key,
                'label' => $tab['label'],
                'icon' => $tab['icon'],
                'pitch' => $tab['pitch'],
                'ready' => $type !== null,
                'type' => $type,
                'url' => $type !== null
                    ? route('assets.index', ['type' => $type])
                    : route('catalogue.tab', ['tab' => $key]),
                'active' => ($onListing && is_string($currentType) && in_array($currentType, $tab['types'], true))
                    || $currentTab === $key,
            ];
        }

        return $tabs;
    }

    /** One tab by its URL key, or null for an unknown key. */
    public function find(string $key): ?array
    {
        foreach ($this->all() as $tab) {
            if ($tab['key'] === $key) {
                return $tab;
            }
        }

        return null;
    }

    /**
     * Slugs of the asset types that currently have published assets.
     *
     * A failure here must never take the header — and with it every page
     * on the site — down, so any error degrades to "nothing is live" and
     * is not cached.
     *
     * @return list<string>
     */
    private function liveTypes(): array
    {
        $slugs = [];

        foreach (self::TABS as $tab) {
            $slugs = array_merge($slugs, $tab['types']);
        }

        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => AssetType::query()
                ->whereIn('slug', $slugs)
                ->whereHas('assets', fn ($q) => $q->published())
                ->pluck('slug')
                ->all());
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
