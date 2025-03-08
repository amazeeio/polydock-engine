<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\PolydockVariableScopeEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polydock_variables', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship
            $table->morphs('variabled');
            
            // Variable details
            $table->string('name');
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            
            // Timestamps
            $table->timestamps();

            // Index for efficient lookups
            $table->index(['variabled_type', 'variabled_id', 'name'], 'polydock_variables_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polydock_variables');
    }
}; 