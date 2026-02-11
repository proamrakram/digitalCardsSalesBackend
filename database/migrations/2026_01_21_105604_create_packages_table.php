<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('(UUID())'));
            $table->string('name');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->string('duration'); // "5 hours" / "1 month"
            $table->decimal('price', 10, 2);
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->enum('type', ['hourly', 'monthly'])->default('monthly');
            $table->timestamps();

            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->index(['category_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
