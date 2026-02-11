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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(DB::raw('(UUID())'));

            $table->enum('payment_method', ['BOP', 'cash', 'palpay'])->default('BOP');
            $table->string('payment_proof_url')->nullable();

            $table->unsignedInteger('quantity');
            $table->decimal('amount', 10, 2)->change();
            $table->decimal('price', 10, 2)->change();
            $table->decimal('total_price', 10, 2);
            $table->text('notes')->nullable();


            $table->enum('status', ['pending', 'confirmed', 'cancelled'])
                ->default('pending')
                ->index();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->json('cards')->nullable();

            $table->index(['user_id', 'created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
