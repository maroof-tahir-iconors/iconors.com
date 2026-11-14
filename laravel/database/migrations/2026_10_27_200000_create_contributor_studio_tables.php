<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contributor Studio: per-type upload access.
 *
 * Lottie is open to every approved contributor. Icons, Illustrations and
 * 3D need a portfolio request that an admin approves; one row per
 * contributor and type records that request and its outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contributor_type_access')) {
            return;
        }

        Schema::create('contributor_type_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contributor_id')->constrained('contributors')->cascadeOnDelete();
            $table->string('type_key', 30);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('portfolio_url', 255)->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['contributor_id', 'type_key']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributor_type_access');
    }
};
