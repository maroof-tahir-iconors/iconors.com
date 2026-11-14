<?php

namespace App\Services\Contributor;

use App\Models\Contributor;
use App\Models\ContributorTypeAccess;

/**
 * The four uploadable asset types in the Contributor Studio, and who may
 * upload which.
 *
 * Lottie is open to every approved contributor. The other types need a
 * portfolio request approved by an admin (contributor_type_access).
 */
class StudioTypes
{
    public const TYPES = [
        'icons' => [
            'label' => 'Icons',
            'singular' => 'Icon',
            'unit' => 'Icons',
            'icon' => 'bullseye',
            'asset_type' => 'svg-icon',
            'required' => ['svg'],
            'optional' => [],
            'max_mb' => 5,
            'open' => false,
            'notes' => [
                'Upload clean, flat SVG files — no embedded images or scripts.',
                'Use a square artboard (24, 32, 48 or 64 px grids work best).',
                'Name each file after what it shows, e.g. lock.svg, shopping-cart.svg.',
            ],
        ],
        'illustrations' => [
            'label' => 'Illustrations',
            'singular' => 'Illustration',
            'unit' => 'Illustrations',
            'icon' => 'bounding-box',
            'asset_type' => 'illustration',
            'required' => ['svg'],
            'optional' => ['eps', 'ai', 'pdf'],
            'max_mb' => 50,
            'open' => false,
            'notes' => [
                'An SVG is required for every illustration — it is used for the preview.',
                'Add the editable source with the same file name (EPS, AI or PDF).',
                'Do not embed raster images inside the SVG.',
            ],
        ],
        '3d' => [
            'label' => '3D Icons',
            'singular' => '3D Icon',
            'unit' => '3D Icons',
            'icon' => 'box',
            'asset_type' => '3d-icon',
            'required' => ['png'],
            'optional' => ['blend', 'glb', 'fbx', 'obj'],
            'max_mb' => 100,
            'open' => false,
            'notes' => [
                'A transparent PNG render (at least 1024 × 1024) is required for the preview.',
                'Add the 3D source with the same file name (BLEND, GLB, FBX or OBJ).',
                'Keep the object centred with even padding on every side.',
            ],
        ],
        'lottie' => [
            'label' => 'Lottie Animations',
            'singular' => 'Lottie Animation',
            'unit' => 'Lotties',
            'icon' => 'slash-square',
            'asset_type' => 'lottie-animation',
            'required' => ['json'],
            'optional' => ['lottie', 'aep', 'zip'],
            'max_mb' => 20,
            'open' => true,
            'notes' => [
                'Please do not upload Lottie JSON which contains images.',
                'Make sure your Lottie JSON does not have more than 500 frames.',
                'Upload the After Effects project (AEP) or .lottie/.zip with the same name if you have it.',
            ],
        ],
    ];

    /** Status cards on each type page, in IconScout order. */
    public const STATUSES = [
        'live' => ['label' => 'Live in Store', 'icon' => 'check-square-fill', 'color' => '#22c55e', 'db' => ['published']],
        'draft' => ['label' => 'Draft', 'icon' => 'pencil-square', 'color' => '#f97316', 'db' => ['draft']],
        'review' => ['label' => 'Under Review', 'icon' => 'hourglass-split', 'color' => '#3b82f6', 'db' => ['submitted', 'under_review', 'approved', 'scheduled']],
        'improve' => ['label' => 'Need to Improve', 'icon' => 'tools', 'color' => '#eab308', 'db' => ['changes_requested', 'copyright_review']],
        'rejected' => ['label' => 'Rejected', 'icon' => 'slash-circle', 'color' => '#ef4444', 'db' => ['rejected', 'suspended']],
        'deleted' => ['label' => 'Deleted', 'icon' => 'trash3', 'color' => '#94a3b8', 'db' => []],
        'failed' => ['label' => 'Failed', 'icon' => 'x-lg', 'color' => '#ef4444', 'db' => ['validation_failed', 'uploading', 'processing']],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::TYPES[$key]);
    }

    public static function get(string $key): array
    {
        return self::TYPES[$key] + ['key' => $key];
    }

    /** @return array<string,array> */
    public static function all(): array
    {
        $out = [];
        foreach (self::TYPES as $key => $t) {
            $out[$key] = $t + ['key' => $key];
        }

        return $out;
    }

    /**
     * Access state for every type: approved | pending | rejected | none.
     *
     * @return array<string,string>
     */
    public static function accessFor(Contributor $contributor): array
    {
        $rows = ContributorTypeAccess::where('contributor_id', $contributor->id)->pluck('status', 'type_key');
        $liveTypes = \Illuminate\Support\Facades\DB::table('assets')
            ->join('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->where('assets.contributor_id', $contributor->id)
            ->where('assets.status', 'published')
            ->whereNull('assets.deleted_at')
            ->distinct()->pluck('asset_types.slug')->all();
        $out = [];
        foreach (self::TYPES as $key => $t) {
            if ($t['open']) {
                $out[$key] = 'approved';

                continue;
            }
            // A contributor who already has live work of a type keeps
            // access to it (e.g. libraries imported before this existed).
            $out[$key] = in_array($t['asset_type'], $liveTypes, true) ? 'approved' : ($rows[$key] ?? 'none');
        }

        return $out;
    }

    public static function hasAccess(Contributor $contributor, string $key): bool
    {
        return (self::accessFor($contributor)[$key] ?? 'none') === 'approved';
    }
}
