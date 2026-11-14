<?php

namespace App\Services\Catalogue;

use App\Models\Asset;
use App\Models\Pack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-side helpers for showing packs as cards.
 *
 * A pack card is a mosaic: one large preview and three small ones, with the
 * pack title and the number of assets inside. Both the homepage and the
 * /packs pages build their cards here so the rules stay in one place:
 *
 *  - only published packs that actually contain published assets are shown;
 *  - the asset count is counted live, never trusted from the stored counter,
 *    so a card can never promise more than the pack page delivers;
 *  - cover previews prefer colourful artwork, for the same reason the old
 *    homepage did — a mosaic of black line art reads as empty.
 */
class PackShowcase
{
    /** One lead tile plus three small ones. */
    public const COVER_SIZE = 4;

    /** Published packs holding at least one published asset, with a live count. */
    public function query(): Builder
    {
        return Pack::query()
            ->where('packs.status', 'published')
            ->whereNotNull('packs.published_at')
            ->where('packs.published_at', '<=', now())
            ->whereHas('assets', fn ($q) => $q->published())
            ->withCount(['assets as published_assets_count' => fn ($q) => $q->published()]);
    }

    /**
     * Whitelisted sorts only — an unknown value falls through to the default
     * rather than reaching the query builder.
     */
    public function applySort(Builder $query, string $sort = 'popular'): Builder
    {
        return match ($sort) {
            'newest' => $query->orderByDesc('packs.published_at')->orderByDesc('packs.id'),
            'largest' => $query->orderByDesc('published_assets_count')->orderBy('packs.title'),
            'az' => $query->orderBy('packs.title'),
            default => $query
                ->orderByDesc('packs.is_featured')
                ->orderByDesc('packs.downloads_count')
                ->orderByDesc('published_assets_count')
                ->orderBy('packs.title'),
        };
    }

    /**
     * Shop-window order: featured first, then the packs with the most
     * colourful artwork. Used by the homepage, where the first impression
     * matters more than strict popularity.
     */
    public function applyShowcaseOrder(Builder $query): Builder
    {
        return $query
            ->withCount(['assets as colourful_assets_count' => fn ($q) => $q
                ->published()
                ->where('metadata->is_colourful', true)])
            ->orderByDesc('packs.is_featured')
            ->orderByDesc('colourful_assets_count')
            ->orderByDesc('published_assets_count')
            ->orderBy('packs.title');
    }

    /**
     * Attaches a "coverAssets" relation (up to four assets, previews loaded)
     * to every pack given.
     *
     * Which assets represent a pack is cached per pack; the assets themselves
     * are then loaded for all packs in a single query, so a page of cards
     * costs a fixed number of queries rather than several per card.
     */
    public function attachCovers(iterable $packs, int $perPack = self::COVER_SIZE): void
    {
        $idsByPack = [];

        foreach ($packs as $pack) {
            $idsByPack[$pack->id] = Cache::remember(
                "pack.cover.{$pack->id}.{$perPack}",
                1800,
                fn () => $this->pickCoverIds((int) $pack->id, $perPack),
            );
        }

        $allIds = [];

        foreach ($idsByPack as $ids) {
            foreach ((array) $ids as $id) {
                $allIds[] = (int) $id;
            }
        }

        $assets = $allIds === []
            ? new Collection()
            : Asset::published()
                ->with(['previews', 'assetType:id,name,slug'])
                ->whereIn('id', $allIds)
                ->get()
                ->keyBy('id');

        foreach ($packs as $pack) {
            $covers = new Collection();

            foreach ((array) $idsByPack[$pack->id] as $id) {
                // A cached id can outlive its asset; skip it rather than
                // render an empty tile where artwork should be.
                if ($asset = $assets->get((int) $id)) {
                    $covers->push($asset);
                }
            }

            $pack->setRelation('coverAssets', $covers);
        }
    }

    /** Colourful assets first, then whatever else the pack holds. */
    private function pickCoverIds(int $packId, int $limit): array
    {
        $base = fn () => Asset::published()
            ->where('pack_id', $packId)
            ->whereHas('previews')
            ->orderBy('id');

        $ids = $base()
            ->where('metadata->is_colourful', true)
            ->limit($limit)
            ->pluck('id')
            ->all();

        if (count($ids) < $limit) {
            $ids = array_merge($ids, $base()
                ->whereNotIn('id', $ids)
                ->limit($limit - count($ids))
                ->pluck('id')
                ->all());
        }

        return array_map('intval', $ids);
    }
}
