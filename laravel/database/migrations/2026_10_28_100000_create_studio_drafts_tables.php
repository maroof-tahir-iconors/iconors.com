<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contributor Studio, part 2: upload drafts, bundles, pack mockups,
 * the Learning Center and a starter set of licences.
 *
 * Only new tables, one new nullable column (packs.bundle_id) and new
 * licence rows — no existing row is modified.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('studio_bundles')) {
            Schema::create('studio_bundles', function (Blueprint $t) {
                $t->id();
                $t->foreignId('contributor_id')->constrained('contributors')->cascadeOnDelete();
                $t->string('type_key', 30);
                $t->string('name', 160);
                $t->timestamps();
                $t->unique(['contributor_id', 'type_key', 'name']);
            });
        }

        if (! Schema::hasColumn('packs', 'bundle_id')) {
            Schema::table('packs', function (Blueprint $t) {
                $t->unsignedBigInteger('bundle_id')->nullable()->after('contributor_id')->index();
            });
        }

        if (! Schema::hasTable('studio_drafts')) {
            Schema::create('studio_drafts', function (Blueprint $t) {
                $t->id();
                $t->foreignId('contributor_id')->constrained('contributors')->cascadeOnDelete();
                $t->string('type_key', 30);
                $t->string('pack_title', 200)->nullable();
                $t->text('description')->nullable();
                $t->boolean('is_premium')->default(false);
                $t->decimal('price', 12, 2)->default(0);
                $t->unsignedBigInteger('license_id')->nullable();
                $t->unsignedBigInteger('bundle_id')->nullable();
                $t->unsignedBigInteger('category_id')->nullable();
                $t->json('preview_asset_ids')->nullable();
                $t->enum('status', ['open', 'submitted'])->default('open');
                $t->unsignedBigInteger('pack_id')->nullable();
                $t->timestamp('submitted_at')->nullable();
                $t->timestamps();
                $t->index(['contributor_id', 'type_key', 'status']);
            });
        }

        if (! Schema::hasTable('pack_mockups')) {
            Schema::create('pack_mockups', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('draft_id')->nullable()->index();
                $t->unsignedBigInteger('pack_id')->nullable()->index();
                $t->string('disk', 30)->default('public');
                $t->string('path', 255);
                $t->string('mime', 80);
                $t->unsignedInteger('size_bytes')->default(0);
                $t->unsignedInteger('width')->nullable();
                $t->unsignedInteger('height')->nullable();
                $t->unsignedSmallInteger('sort_order')->default(0);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('learning_courses')) {
            Schema::create('learning_courses', function (Blueprint $t) {
                $t->id();
                $t->string('section', 120);
                $t->string('title', 200);
                $t->string('author', 120)->nullable();
                $t->string('url', 500);
                $t->string('thumbnail', 255)->nullable();
                $t->string('color', 20)->nullable();
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });

            $courses = [
                ['Learn Icon Design', 'Material Design — Icons guidelines', 'Google', 'https://m3.material.io/styles/icons/overview', '#4f46e5'],
                ['Learn Icon Design', 'Apple Human Interface Guidelines — Icons', 'Apple', 'https://developer.apple.com/design/human-interface-guidelines/icons', '#0ea5e9'],
                ['Learn Icon Design', 'SVG Tutorial', 'MDN Web Docs', 'https://developer.mozilla.org/en-US/docs/Web/SVG/Tutorial', '#f97316'],
                ['Learn Icon Design', 'Inkscape Tutorials', 'Inkscape', 'https://inkscape.org/learn/tutorials/', '#16a34a'],
                ['Learn Illustration', 'Adobe Illustrator tutorials', 'Adobe', 'https://helpx.adobe.com/illustrator/tutorials.html', '#ea580c'],
                ['Learn Illustration', 'Inkscape Manual', 'Inkscape', 'https://inkscape-manuals.readthedocs.io/en/latest/', '#22c55e'],
                ['Learn 3D', 'Blender Manual', 'Blender Foundation', 'https://docs.blender.org/manual/en/latest/', '#f59e0b'],
                ['Learn 3D', 'Spline Documentation', 'Spline', 'https://docs.spline.design/', '#a855f7'],
                ['Learn Lottie Animation', 'Adobe After Effects tutorials', 'Adobe', 'https://helpx.adobe.com/after-effects/tutorials.html', '#7c3aed'],
                ['Learn Lottie Animation', 'Lottie Documentation', 'Airbnb', 'https://airbnb.io/lottie/', '#14b8a6'],
                ['Learn Lottie Animation', 'lottie-web on GitHub', 'Airbnb', 'https://github.com/airbnb/lottie-web', '#0f766e'],
            ];
            foreach ($courses as $i => [$section, $title, $author, $url, $color]) {
                DB::table('learning_courses')->insert([
                    'section' => $section, 'title' => $title, 'author' => $author, 'url' => $url, 'color' => $color,
                    'sort_order' => $i, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // Licences shown on the draft page. Added only if missing.
        $licences = [
            ['iconors-standard', 'Iconors Standard License', 'Premium licence for paid assets: commercial use in unlimited projects, no redistribution as-is.', 1, 1, 0, 0],
            ['free-commercial', 'Free for Commercial Use', 'Free to use in personal and commercial projects. No attribution required.', 1, 1, 0, 0],
            ['free-personal', 'Free for Personal Use', 'Free for personal, non-commercial projects only.', 0, 1, 0, 0],
            ['cc0', 'Creative Commons Zero', 'Public domain dedication — use for anything, no attribution required.', 1, 1, 1, 0],
            ['cc-by-4', 'Creative Commons 4 Attribution', 'Use, share and adapt for any purpose, with attribution.', 1, 1, 1, 1],
            ['cc-by-sa-4', 'Creative Commons 4 Attribution - ShareAlike', 'Attribution required; adaptations must use the same licence.', 1, 1, 1, 1],
            ['cc-by-nd-4', 'Creative Commons 4 Attribution - NoDerivatives', 'Share with attribution; no modified versions.', 1, 0, 1, 1],
            ['cc-by-nc-4', 'Creative Commons 4 Attribution - NonCommercial', 'Use and adapt with attribution, non-commercial only.', 0, 1, 1, 1],
            ['mit', 'MIT', 'Permissive open-source licence; keep the copyright notice.', 1, 1, 1, 1],
            ['apache-2', 'Apache 2.0', 'Permissive open-source licence with patent grant; keep the notice.', 1, 1, 1, 1],
            ['gpl', 'GNU General Public License', 'Copyleft: derivative works must be released under the GPL.', 1, 1, 1, 1],
        ];
        foreach ($licences as $i => [$slug, $name, $summary, $commercial, $modify, $redistribute, $attrib]) {
            if (! DB::table('licenses')->where('slug', $slug)->exists()) {
                DB::table('licenses')->insert([
                    'name' => $name, 'slug' => $slug, 'summary' => $summary, 'full_text' => $summary,
                    'allows_commercial' => $commercial, 'allows_modification' => $modify, 'allows_redistribution' => $redistribute,
                    'requires_attribution' => $attrib, 'is_extended' => 0, 'is_active' => 1, 'sort_order' => 100 + $i,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_courses');
        Schema::dropIfExists('pack_mockups');
        Schema::dropIfExists('studio_drafts');
        if (Schema::hasColumn('packs', 'bundle_id')) {
            Schema::table('packs', fn (Blueprint $t) => $t->dropColumn('bundle_id'));
        }
        Schema::dropIfExists('studio_bundles');
    }
};
