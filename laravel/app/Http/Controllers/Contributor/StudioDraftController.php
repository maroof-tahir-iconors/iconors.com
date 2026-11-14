<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\AssetPreview;
use App\Models\Category;
use App\Models\License;
use App\Models\Pack;
use App\Models\PackMockup;
use App\Models\StudioBundle;
use App\Models\StudioDraft;
use App\Models\Tag;
use App\Services\Contributor\StudioTypes;
use App\Services\Contributor\StudioUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "My Drafts" — what happens after files are uploaded: pack title,
 * description, price + licence, bundle, category, per-item names,
 * descriptions and tags, pack mockups, then Submit for review.
 */
class StudioDraftController extends Controller
{
    private const MIN_TAGS = 4;

    private const MAX_TAGS = 50;

    /* ------------------------------------------------------------- list */

    public function index(Request $request, string $type)
    {
        $t = $this->type($request, $type);
        $c = $request->user()->contributor;

        $drafts = StudioDraft::where('contributor_id', $c->id)->where('type_key', $type)->where('status', 'open')
            ->latest('updated_at')->get();
        foreach ($drafts as $d) {
            $d->items = $d->assetsQuery()->with('previews')->orderBy('id')->limit(4)->get();
            $d->items_count = $d->assetsQuery()->count();
        }
        // Empty sessions (upload abandoned before any file landed) are noise.
        $drafts = $drafts->filter(fn ($d) => $d->items_count > 0)->values();

        return view('contributor.studio.drafts', ['type' => $t, 'drafts' => $drafts]);
    }

    /** Called by the uploader right before the first file is sent. */
    public function create(Request $request, string $type)
    {
        $this->type($request, $type);
        abort_unless($request->user()->can('create', Asset::class), 403);
        $c = $request->user()->contributor;

        $draft = StudioDraft::create([
            'contributor_id' => $c->id,
            'type_key' => $type,
            'license_id' => License::where('slug', 'free-commercial')->value('id'),
        ]);

        return response()->json(['id' => $draft->id, 'url' => route('contributor.drafts.edit', [$type, $draft])]);
    }

    /* ----------------------------------------------------------- editor */

    public function edit(Request $request, string $type, StudioDraft $draft)
    {
        $t = $this->type($request, $type);
        $this->own($request, $draft, $type);

        $items = $draft->assetsQuery()->with(['previews', 'tags'])->orderBy('id')->get();
        if ($items->isEmpty() && $draft->mockups()->count() === 0) {
            return redirect()->route('contributor.type.upload', [$type, 'draft' => $draft->id]);
        }

        $licenses = License::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']);
        $premiumLicense = $licenses->firstWhere('slug', 'iconors-standard');

        return view('contributor.studio.draft-edit', [
            'type' => $t,
            'draft' => $draft,
            'items' => $items,
            'mockups' => $draft->mockups,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'freeLicenses' => $licenses->where('slug', '!=', 'iconors-standard')->values(),
            'premiumLicense' => $premiumLicense,
            'bundle' => $draft->bundle_id ? StudioBundle::find($draft->bundle_id) : null,
            'minTags' => self::MIN_TAGS,
            'maxTags' => self::MAX_TAGS,
        ]);
    }

    public function save(Request $request, string $type, StudioDraft $draft)
    {
        $this->type($request, $type);
        $this->own($request, $draft, $type);
        $this->persist($request, $draft);

        return response()->json(['ok' => true, 'message' => 'Draft saved.']);
    }

    public function submit(Request $request, string $type, StudioDraft $draft)
    {
        $t = $this->type($request, $type);
        $this->own($request, $draft, $type);
        $data = $this->persist($request, $draft);
        $items = $draft->assetsQuery()->with('tags')->orderBy('id')->get();

        // ---- validation, collected so the editor can highlight every problem at once
        $errors = [];
        if ($items->isEmpty()) {
            $errors['general'] = 'Upload at least one file before submitting.';
        }
        if (trim((string) $draft->pack_title) === '') {
            $errors['pack_title'] = 'Give the pack a title.';
        }
        if (! $draft->category_id) {
            $errors['category_id'] = 'Choose a category.';
        }
        if (! $draft->bundle_id) {
            $errors['bundle_id'] = 'Choose a bundle (or create a new one).';
        }
        if (! $draft->is_premium && ! $draft->license_id) {
            $errors['license_id'] = 'Choose a licence for free assets.';
        }
        if ($draft->is_premium && (float) $draft->price <= 0) {
            $errors['price'] = 'Set a price for premium assets.';
        }
        foreach ($items as $item) {
            if (trim($item->title) === '') {
                $errors['item_'.$item->id] = 'Every item needs a name.';
            } elseif ($item->tags->count() < self::MIN_TAGS) {
                $errors['item_'.$item->id] = "“{$item->title}” needs at least ".self::MIN_TAGS.' tags.';
            }
        }
        if ($errors) {
            return response()->json(['ok' => false, 'message' => reset($errors), 'errors' => $errors], 422);
        }

        $c = $request->user()->contributor;
        $pack = DB::transaction(function () use ($draft, $items, $c) {
            $base = Str::slug($draft->pack_title) ?: 'pack';
            $slug = $base;
            $i = 1;
            while (Pack::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.(++$i);
            }
            $pack = Pack::create([
                'contributor_id' => $c->id,
                'title' => $draft->pack_title,
                'slug' => $slug,
                'description' => $draft->description,
                'is_premium' => $draft->is_premium,
                'price' => $draft->is_premium ? $draft->price : 0,
                // Goes live when the first of its assets is approved.
                'status' => 'submitted',
            ]);
            $pack->forceFill(['bundle_id' => $draft->bundle_id])->save();

            $order = array_flip(array_map('intval', (array) $draft->preview_asset_ids));
            $sorted = $items->sortBy(fn ($a) => $order[$a->id] ?? (1000 + $a->id))->values();
            foreach ($sorted as $n => $asset) {
                $meta = (array) $asset->metadata;
                $meta['studio_draft_id'] = $draft->id;
                $meta['pack'] = $draft->pack_title;
                $asset->update([
                    'pack_id' => $pack->id,
                    'category_id' => $draft->category_id,
                    'license_id' => $draft->is_premium ? License::where('slug', 'iconors-standard')->value('id') : $draft->license_id,
                    'is_premium' => $draft->is_premium,
                    'price' => 0,
                    'status' => 'submitted',
                    'metadata' => $meta,
                ]);
                DB::table('pack_asset')->insertOrIgnore(['pack_id' => $pack->id, 'asset_id' => $asset->id, 'sort_order' => $n + 1]);
            }

            PackMockup::where('draft_id', $draft->id)->update(['pack_id' => $pack->id]);
            $cover = PackMockup::where('draft_id', $draft->id)->where('mime', 'like', 'image/%')->orderBy('sort_order')->value('path');
            if ($cover) {
                $pack->update(['cover_image' => $cover]);
            }
            $draft->update(['status' => 'submitted', 'pack_id' => $pack->id, 'submitted_at' => now()]);

            return $pack;
        });

        return response()->json([
            'ok' => true,
            'message' => "“{$pack->title}” was submitted for review.",
            'redirect' => route('contributor.type.status', [$t['key'], 'review']),
        ]);
    }

    public function destroy(Request $request, string $type, StudioDraft $draft)
    {
        $this->type($request, $type);
        $this->own($request, $draft, $type);

        DB::transaction(function () use ($draft) {
            foreach ($draft->assetsQuery()->get() as $asset) {
                $asset->delete();
            }
            foreach ($draft->mockups as $m) {
                Storage::disk($m->disk)->delete($m->path);
                $m->delete();
            }
            $draft->delete();
        });

        return response()->json(['ok' => true, 'redirect' => route('contributor.type', $type)]);
    }

    /* ------------------------------------------------- single item tools */

    public function destroyItem(Request $request, string $type, StudioDraft $draft, Asset $asset)
    {
        $this->type($request, $type);
        $this->own($request, $draft, $type);
        $this->item($draft, $asset);
        $asset->delete();

        return response()->json(['ok' => true]);
    }

    public function addFormat(Request $request, string $type, StudioDraft $draft, Asset $asset, StudioUploadService $uploads)
    {
        $t = $this->type($request, $type);
        $this->own($request, $draft, $type);
        $this->item($draft, $asset);
        $request->validate(['file' => ['required', 'file', 'max:'.($t['max_mb'] * 1024)]]);

        try {
            $formats = $uploads->addFormat($asset, $type, $request->file('file'));
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'formats' => $formats]);
    }

    /* ------------------------------------------------------------ mockups */

    public function storeMockup(Request $request, string $type, StudioDraft $draft)
    {
        $this->type($request, $type);
        $this->own($request, $draft, $type);
        $mimes = $type === 'lottie' ? 'mp4,webm,jpg,jpeg,png,webp' : 'jpg,jpeg,png,webp';
        $request->validate(['file' => ['required', 'file', 'mimes:'.$mimes, 'max:2048']], [
            'file.max' => 'Mockups must be smaller than 2 MB.',
            'file.mimes' => $type === 'lottie' ? 'Use an MP4/WebM video or a JPG/PNG/WebP image.' : 'Use a JPG, PNG or WebP image.',
        ]);
        if ($draft->mockups()->count() >= 8) {
            return response()->json(['ok' => false, 'message' => 'You can upload up to 8 mockups.'], 422);
        }

        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $dims = str_starts_with($mime, 'image/') ? @getimagesize($file->getRealPath()) : false;
        $path = $file->store("pack-mockups/{$draft->id}", 'public');
        $m = PackMockup::create([
            'draft_id' => $draft->id,
            'disk' => 'public',
            'path' => $path,
            'mime' => $mime,
            'size_bytes' => $file->getSize(),
            'width' => $dims[0] ?? null,
            'height' => $dims[1] ?? null,
            'sort_order' => $draft->mockups()->count(),
        ]);

        return response()->json(['ok' => true, 'id' => $m->id, 'url' => $m->url(), 'video' => $m->isVideo()]);
    }

    public function destroyMockup(Request $request, string $type, StudioDraft $draft, PackMockup $mockup)
    {
        $this->type($request, $type);
        $this->own($request, $draft, $type);
        abort_unless((int) $mockup->draft_id === (int) $draft->id, 404);
        Storage::disk($mockup->disk)->delete($mockup->path);
        $mockup->delete();

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------ bundles */

    public function bundles(Request $request, string $type)
    {
        $this->type($request, $type);
        $c = $request->user()->contributor;
        $bundles = StudioBundle::where('contributor_id', $c->id)->where('type_key', $type)->orderBy('name')->get();

        return response()->json(['bundles' => $bundles->map(function ($b) {
            $assetId = DB::table('packs')->join('assets', 'assets.pack_id', '=', 'packs.id')
                ->where('packs.bundle_id', $b->id)->whereNull('assets.deleted_at')->orderBy('assets.id')->value('assets.id');
            $preview = $assetId ? AssetPreview::where('asset_id', $assetId)->where('is_primary', true)->first() : null;

            return [
                'id' => $b->id,
                'name' => $b->name,
                'packs' => DB::table('packs')->where('bundle_id', $b->id)->whereNull('deleted_at')->count(),
                'preview' => $preview ? ['url' => $preview->url ?? Storage::disk($preview->disk)->url($preview->path), 'lottie' => $preview->type === 'lottie'] : null,
            ];
        })]);
    }

    public function storeBundle(Request $request, string $type)
    {
        $this->type($request, $type);
        $c = $request->user()->contributor;
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:160']]);
        $bundle = StudioBundle::firstOrCreate(['contributor_id' => $c->id, 'type_key' => $type, 'name' => trim($data['name'])]);

        return response()->json(['ok' => true, 'id' => $bundle->id, 'name' => $bundle->name]);
    }

    /* --------------------------------------------------------- suggestions */

    /** Tags used on similar published assets (same title words), most frequent first. */
    public function suggestTags(Request $request)
    {
        $title = Str::lower((string) $request->query('title'));
        $words = array_values(array_filter(preg_split('/[^a-z0-9]+/', $title), fn ($w) => strlen($w) >= 3));
        $existing = array_map('strtolower', (array) $request->query('have', []));
        if (! $words) {
            return response()->json(['tags' => []]);
        }

        $assetIds = DB::table('assets')->where('status', 'published')->whereNull('deleted_at')
            ->where(function ($q) use ($words) {
                foreach (array_slice($words, 0, 4) as $w) {
                    $q->orWhere('title', 'like', '%'.addcslashes($w, '%_\\').'%');
                }
            })->limit(400)->pluck('id');

        $tags = DB::table('asset_tag')->join('tags', 'tags.id', '=', 'asset_tag.tag_id')
            ->whereIn('asset_tag.asset_id', $assetIds)
            ->selectRaw('tags.name, COUNT(*) AS n')->groupBy('tags.name')->orderByDesc('n')->limit(40)->pluck('name')
            ->map(fn ($n) => Str::lower($n))
            ->reject(fn ($n) => in_array($n, $existing, true) || in_array($n, ['icon', 'svg', 'vector', 'lottie', 'animation', 'animated', '3d', 'illustration'], true))
            ->values();

        $merged = collect($words)->reject(fn ($w) => in_array($w, $existing, true))->merge($tags)->unique()->take(14)->values();

        return response()->json(['tags' => $merged]);
    }

    /* ------------------------------------------------------------ helpers */

    /** Saves every field the editor sends. No validation of completeness — that's Submit's job. */
    private function persist(Request $request, StudioDraft $draft): array
    {
        $data = $request->validate([
            'pack_title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_premium' => ['nullable', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'license_id' => ['nullable', 'integer', 'exists:licenses,id'],
            'bundle_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'preview_ids' => ['nullable', 'array', 'max:4'],
            'preview_ids.*' => ['integer'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.title' => ['nullable', 'string', 'max:200'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.tags' => ['nullable', 'array', 'max:'.self::MAX_TAGS],
            'items.*.tags.*' => ['string', 'max:40'],
        ]);

        $bundleId = $data['bundle_id'] ?? null;
        if ($bundleId && ! StudioBundle::where('id', $bundleId)->where('contributor_id', $draft->contributor_id)->exists()) {
            $bundleId = null;
        }

        $ids = $draft->assetsQuery()->pluck('id')->all();
        $draft->update([
            'pack_title' => $data['pack_title'] ?? null,
            'description' => $data['description'] ?? null,
            'is_premium' => (bool) ($data['is_premium'] ?? false),
            'price' => (float) ($data['price'] ?? 0),
            'license_id' => $data['license_id'] ?? null,
            'bundle_id' => $bundleId,
            'category_id' => $data['category_id'] ?? null,
            'preview_asset_ids' => array_values(array_intersect(array_map('intval', $data['preview_ids'] ?? []), $ids)),
        ]);

        foreach ((array) ($data['items'] ?? []) as $row) {
            if (! in_array((int) $row['id'], $ids, true)) {
                continue;
            }
            $asset = Asset::find($row['id']);
            $asset->update([
                'title' => trim((string) ($row['title'] ?? $asset->title)) ?: $asset->title,
                'description' => $row['description'] ?? null,
            ]);
            $tagIds = [];
            foreach (array_slice((array) ($row['tags'] ?? []), 0, self::MAX_TAGS) as $name) {
                $name = Str::lower(trim(strip_tags((string) $name)));
                if ($name === '' || Str::slug($name) === '') {
                    continue;
                }
                $tagIds[] = Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
            }
            $asset->tags()->sync(array_values(array_unique($tagIds)));
        }

        return $data;
    }

    private function type(Request $request, string $type): array
    {
        abort_unless(StudioTypes::exists($type), 404);
        abort_unless(StudioTypes::hasAccess($request->user()->contributor, $type), 403);

        return StudioTypes::get($type);
    }

    private function own(Request $request, StudioDraft $draft, string $type): void
    {
        abort_unless((int) $draft->contributor_id === (int) $request->user()->contributor->id
            && $draft->type_key === $type && $draft->status === 'open', 404);
    }

    private function item(StudioDraft $draft, Asset $asset): void
    {
        abort_unless((int) $asset->contributor_id === (int) $draft->contributor_id
            && $asset->status === 'draft'
            && (int) ($asset->metadata['studio_draft_id'] ?? 0) === (int) $draft->id, 404);
    }
}
