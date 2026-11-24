<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate', 15)->unique();
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 50)->nullable();
            $table->enum('transmission', ['manual', 'matic'])->default('manual');
            $table->unsignedTinyInteger('seats')->default(4);
            $table->decimal('price_per_day', 12, 2);
            $table->decimal('late_fee_per_hour', 12, 2);
            $table->string('photo')->nullable();
            $table->enum('status', ['available', 'maintenance', 'inactive'])->default('available');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['qris', 'bank_transfer']);
            $table->string('label', 100);
            $table->string('account_number', 50)->nullable();
            $table->string('account_name', 100)->nullable();
            $table->string('qris_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->string('booking_code', 20)->unique();
            $table->string('destination');
            $table->text('delivery_address');
            $table->dateTime('pickup_at');
            $table->dateTime('return_at');
            $table->dateTime('actual_returned_at')->nullable();
            $table->unsignedSmallInteger('duration_days');
            $table->decimal('price_per_day', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->decimal('dp_amount', 12, 2)->default(0);
            $table->decimal('late_fee_total', 12, 2)->default(0);
            $table->enum('status', [
                'pending_dp', 'booked', 'assigned', 'delivering',
                'on_rent', 'returned', 'completed', 'cancelled',
            ])->default('pending_dp');
            $table->timestamps();

            $table->index(['vehicle_id', 'pickup_at', 'return_at']);
            $table->index('status');
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_period_check CHECK (return_at > pickup_at)');

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->dateTime('verified_at')->nullable();
            $table->index('status');
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->enum('type', ['dp', 'pelunasan', 'denda']);
            $table->decimal('amount', 12, 2);
            $table->string('proof')->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'type']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('assigned_by')->constrained('users');
            $table->enum('status', [
                'assigned', 'approved', 'delivering', 'delivered', 'picked_up', 'in_garage',
            ])->default('assigned');
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        Schema::create('delivery_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->enum('stage', ['kirim', 'ambil', 'anomali']);
            $table->string('path');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['delivery_id', 'stage']);
        });

        Schema::create('damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('assessed_by')->constrained('users');
            $table->text('description');
            $table->decimal('fine_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->dateTime('recorded_at');

            $table->index(['booking_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_logs');
        Schema::dropIfExists('damages');
        Schema::dropIfExists('delivery_photos');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('vehicles');
    }
};