<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->enum('category', ['oli', 'perbaikan', 'servis', 'pajak', 'lainnya']);
            $table->text('description');
            $table->decimal('amount', 12, 2);
            $table->date('spent_at');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['vehicle_id', 'spent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
