<?php

namespace App\Services\Contributor;

use App\Models\Contributor;
use Illuminate\Support\Facades\DB;

/**
 * Contributor achievements — badges that unlock at upload and download
 * milestones. Everything is computed from live data, so there is nothing
 * to award or revoke by hand.
 *
 * The milestone numbers live in the two arrays below; change them there.
 */
class StudioAchievements
{
    /** All-time badges. kind: flag | uploads_non_icon | uploads_icon | downloads */
    private const ALL_TIME = [
        ['key' => 'community', 'title' => 'Elite Iconors Contributor Community', 'kind' => 'flag', 'icon' => 'patch-check-fill', 'from' => '#38bdf8', 'to' => '#2563eb'],
        ['key' => 'free-uploader', 'title' => 'Free Asset Uploader', 'kind' => 'flag', 'icon' => 'cloud-arrow-up-fill', 'from' => '#4ade80', 'to' => '#15803d'],
        ['key' => 'curators-pick', 'title' => "Iconors Curator's Pick", 'kind' => 'flag', 'icon' => 'hand-thumbs-up-fill', 'from' => '#fcd34d', 'to' => '#d97706'],
        ['key' => 'non-icons-15k', 'title' => 'Uploading 15,000 non-icons on Iconors', 'kind' => 'uploads_non_icon', 'target' => 15000, 'label' => '15K', 'from' => '#93c5fd', 'to' => '#3b82f6'],
        ['key' => 'non-icons-30k', 'title' => 'Uploading 30,000 non-icons on Iconors', 'kind' => 'uploads_non_icon', 'target' => 30000, 'label' => '30K', 'from' => '#7dd3fc', 'to' => '#0284c7'],
        ['key' => 'non-icons-50k', 'title' => 'Uploading 50,000 non-icons on Iconors', 'kind' => 'uploads_non_icon', 'target' => 50000, 'label' => '50K', 'from' => '#fdba74', 'to' => '#ea580c'],
        ['key' => 'non-icons-100k', 'title' => 'Uploading 100,000 non-icons on Iconors', 'kind' => 'uploads_non_icon', 'target' => 100000, 'label' => '100K', 'from' => '#c4b5fd', 'to' => '#7c3aed'],
        ['key' => 'non-icons-200k', 'title' => 'Uploading 200,000 non-icons on Iconors', 'kind' => 'uploads_non_icon', 'target' => 200000, 'label' => '200K', 'from' => '#fde047', 'to' => '#ca8a04'],
        ['key' => 'icons-10k', 'title' => 'Uploading 10,000 icons on Iconors', 'kind' => 'uploads_icon', 'target' => 10000, 'label' => '10K', 'from' => '#a5b4fc', 'to' => '#4f46e5'],
        ['key' => 'icons-100k', 'title' => 'Uploading 100,000 icons on Iconors', 'kind' => 'uploads_icon', 'target' => 100000, 'label' => '100K', 'from' => '#cbd5e1', 'to' => '#475569'],
        ['key' => 'downloads-1k', 'title' => '1,000 downloads of your work', 'kind' => 'downloads', 'target' => 1000, 'label' => '1K', 'from' => '#f9a8d4', 'to' => '#db2777'],
        ['key' => 'downloads-10k', 'title' => '10,000 downloads of your work', 'kind' => 'downloads', 'target' => 10000, 'label' => '10K', 'from' => '#5eead4', 'to' => '#0d9488'],
        ['key' => 'downloads-100k', 'title' => '100,000 downloads of your work', 'kind' => 'downloads', 'target' => 100000, 'label' => '100K', 'from' => '#fca5a5', 'to' => '#dc2626'],
    ];

    /** Badges that reset each calendar month. */
    private const MONTHLY = [
        ['key' => 'month-uploads-100', 'title' => '100 new assets published this month', 'kind' => 'month_uploads', 'target' => 100, 'label' => '100', 'from' => '#93c5fd', 'to' => '#2563eb'],
        ['key' => 'month-uploads-500', 'title' => '500 new assets published this month', 'kind' => 'month_uploads', 'target' => 500, 'label' => '500', 'from' => '#86efac', 'to' => '#16a34a'],
        ['key' => 'month-downloads-100', 'title' => '100 downloads this month', 'kind' => 'month_downloads', 'target' => 100, 'label' => '100', 'from' => '#fdba74', 'to' => '#ea580c'],
        ['key' => 'month-downloads-1k', 'title' => '1,000 downloads this month', 'kind' => 'month_downloads', 'target' => 1000, 'label' => '1K', 'from' => '#f0abfc', 'to' => '#c026d3'],
    ];

    /** @return array{all: list<array>, monthly: list<array>, unlocked: int, total: int} */
    public function for(Contributor $contributor): array
    {
        $counts = $this->counts($contributor);
        $all = array_map(fn ($b) => $this->resolve($b, $counts, $contributor), self::ALL_TIME);
        $monthly = array_map(fn ($b) => $this->resolve($b, $counts, $contributor), self::MONTHLY);

        return [
            'all' => $all,
            'monthly' => $monthly,
            'unlocked' => count(array_filter($all, fn ($b) => $b['unlocked'])),
            'total' => count($all),
        ];
    }

    public function find(Contributor $contributor, string $key): ?array
    {
        $data = $this->for($contributor);
        foreach (array_merge($data['all'], $data['monthly']) as $b) {
            if ($b['key'] === $key) {
                return $b;
            }
        }

        return null;
    }

    private function counts(Contributor $c): array
    {
        $byType = DB::table('assets')
            ->join('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->where('assets.contributor_id', $c->id)->where('assets.status', 'published')->whereNull('assets.deleted_at')
            ->selectRaw('asset_types.slug AS slug, COUNT(*) AS n')->groupBy('asset_types.slug')->pluck('n', 'slug');
        $icons = (int) ($byType['svg-icon'] ?? 0) + (int) ($byType['icon-pack'] ?? 0);

        return [
            'icons' => $icons,
            'non_icons' => (int) $byType->sum() - $icons,
            'downloads' => (int) DB::table('downloads')->where('contributor_id', $c->id)->count(),
            'free_live' => DB::table('assets')->where('contributor_id', $c->id)->where('status', 'published')->where('is_premium', false)->whereNull('deleted_at')->min('published_at'),
            'featured' => DB::table('assets')->where('contributor_id', $c->id)->where('status', 'published')->where('is_featured', true)->whereNull('deleted_at')->min('published_at'),
            'month_uploads' => (int) DB::table('assets')->where('contributor_id', $c->id)->where('status', 'published')->whereNull('deleted_at')->where('published_at', '>=', now()->startOfMonth())->count(),
            'month_downloads' => (int) DB::table('downloads')->where('contributor_id', $c->id)->where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    private function resolve(array $b, array $counts, Contributor $c): array
    {
        $current = null;
        $date = null;
        switch ($b['kind']) {
            case 'flag':
                $date = match ($b['key']) {
                    'community' => $c->approved_at,
                    'free-uploader' => $counts['free_live'],
                    'curators-pick' => $counts['featured'],
                    default => null,
                };
                $unlocked = $date !== null;
                break;
            default:
                $current = match ($b['kind']) {
                    'uploads_non_icon' => $counts['non_icons'],
                    'uploads_icon' => $counts['icons'],
                    'downloads' => $counts['downloads'],
                    'month_uploads' => $counts['month_uploads'],
                    'month_downloads' => $counts['month_downloads'],
                };
                $unlocked = $current >= $b['target'];
        }

        return $b + [
            'unlocked' => $unlocked,
            'current' => $current,
            'progress' => isset($b['target']) ? min(100, round(($current ?? 0) / $b['target'] * 100, 1)) : ($unlocked ? 100 : 0),
            'date' => $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') : null,
        ];
    }

    /** The badge artwork as a standalone SVG (shown on the page and downloadable). */
    public static function svg(array $b, int $size = 120): string
    {
        $id = 'g'.substr(md5($b['key']), 0, 6);
        $label = htmlspecialchars($b['label'] ?? '', ENT_XML1);
        $glyph = match ($b['icon'] ?? '') {
            'patch-check-fill' => '<path d="M60 34l6.5 6 8.7-1.2 1.2 8.7 6 6.5-6 6.5-1.2 8.7-8.7-1.2L60 74l-6.5-6-8.7 1.2-1.2-8.7-6-6.5 6-6.5 1.2-8.7 8.7 1.2z" fill="#fff" opacity=".95"/><path d="M52 55l6 6 11-12" stroke="'.$b['to'].'" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
            'cloud-arrow-up-fill' => '<path d="M42 68h36a12 12 0 0 0 1-24 18 18 0 0 0-34-4 13 13 0 0 0-3 28z" fill="#fff"/><path d="M60 48v20M51 56l9-9 9 9" stroke="'.$b['to'].'" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
            'hand-thumbs-up-fill' => '<path d="M45 56h8v22h-8zM57 78h18a6 6 0 0 0 6-5l3-14a6 6 0 0 0-6-7H67l2-9a5 5 0 0 0-9-3l-3 10z" fill="#fff"/>',
            default => '<path d="M60 36v24M50 46l10-10 10 10" stroke="#fff" stroke-width="5" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity=".9"/><text x="60" y="80" text-anchor="middle" font-family="Inter,Arial,sans-serif" font-weight="800" font-size="'.(strlen($label) > 3 ? 19 : 23).'" fill="#fff">'.$label.'</text><g fill="#fff" opacity=".85"><circle cx="50" cy="90" r="2.2"/><circle cx="60" cy="90" r="2.2"/><circle cx="70" cy="90" r="2.2"/></g>',
        };

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 120 120">'
            .'<defs><linearGradient id="'.$id.'" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$b['from'].'"/><stop offset="1" stop-color="'.$b['to'].'"/></linearGradient></defs>'
            .'<path d="M60 6l46.8 27v54L60 114 13.2 87V33z" fill="url(#'.$id.')"/>'
            .'<path d="M60 14l39.8 23v46L60 106 20.2 83V37z" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="2"/>'
            .$glyph.'</svg>';
    }
}
