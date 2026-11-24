<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@rental.test'],
            [
                'name' => 'Admin Rental',
                'password' => 'password',
                'role' => 'admin',
            ]
        );

        User::firstOrCreate(
            ['email' => 'pegawai@rental.test'],
            [
                'name' => 'Budi Pegawai',
                'password' => 'password',
                'role' => 'pegawai',
                'phone' => '081234567890',
            ]
        );

        User::firstOrCreate(
            ['email' => 'wahyu@rental.test'],
            [
                'name' => 'Wahyu',
                'password' => 'password',
                'role' => 'pelanggan',
                'phone' => '081298765432',
                'address' => 'Jl. Mungsidi No. 53, Surabaya',
            ]
        );

        PaymentMethod::firstOrCreate(
            ['label' => 'QRIS Rental Mobil'],
            [
                'type' => 'qris',
                'qris_image' => 'payment-methods/uPoAkuvEvaHqgNLvervKoX04AUTcdAQKwH9kDeqd.jpg',
                'is_active' => true,
            ]
        );

        PaymentMethod::firstOrCreate(
            ['account_number' => '1234567890'],
            [
                'type' => 'bank_transfer',
                'label' => 'BCA Transfer',
                'account_number' => '1234567890',
                'account_name' => 'PT Rental Mobil Indonesia',
                'is_active' => true,
            ]
        );

        Vehicle::firstOrCreate(
            ['plate' => 'L 1234 WU'],
            [
                'brand' => 'Honda',
                'model' => 'Brio RS',
                'year' => 2022,
                'color' => 'Putih',
                'transmission' => 'matic',
                'seats' => 4,
                'price_per_day' => 300000,
                'late_fee_per_hour' => 10000,
                'photo' => 'vehicles/8qZGXwlSDd4UggveSCkBnWjldtvOV1cKHzLcHGB2.jpg',
                'status' => 'available',
            ]
        );

        Vehicle::firstOrCreate(
            ['plate' => 'W 3241 RY'],
            [
                'brand' => 'Toyota Innova Reborn',
                'model' => 'G',
                'year' => 2018,
                'color' => 'Hitam',
                'transmission' => 'manual',
                'seats' => 7,
                'price_per_day' => 450000,
                'late_fee_per_hour' => 25000,
                'photo' => 'vehicles/NEOmHyLRWlQDs5Y3pcNvMZkuMZZF4L9hYLsiFP1t.jpg',
                'status' => 'available',
            ]
        );
    }
}
