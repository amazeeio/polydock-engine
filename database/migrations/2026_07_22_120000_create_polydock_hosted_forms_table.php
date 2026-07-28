<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polydock_hosted_forms', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('form_class');
            $table->boolean('enabled')->default(true);
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('notice')->nullable();
            $table->text('disclaimer')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('polydock_hosted_form_store_app', function (Blueprint $table) {
            $table->id();
            $table->foreignId('polydock_hosted_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('polydock_store_app_id')->constrained()->cascadeOnDelete();
            $table->unique(['polydock_hosted_form_id', 'polydock_store_app_id'], 'hosted_form_store_app_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polydock_hosted_form_store_app');
        Schema::dropIfExists('polydock_hosted_forms');
    }
};
