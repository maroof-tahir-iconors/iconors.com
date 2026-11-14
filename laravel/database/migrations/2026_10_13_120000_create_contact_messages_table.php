<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact form submissions.
 *
 * Separate from `tickets` on purpose: a ticket requires a user account,
 * and the contact form has to work for visitors who have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_messages')) {
            return;
        }

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 120);
            $table->string('email', 255);
            $table->string('topic', 40)->default('general');
            $table->string('subject', 160);
            $table->text('message');

            // Where the visitor came from, e.g. "tool:converter".
            $table->string('context', 120)->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->string('status', 20)->default('new');   // new, read, replied, spam

            // Email outcome. A null notified_at means support was never emailed.
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('mail_error', 500)->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
