<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

/** Starter delivery options (placeholder prices — edit in Admin › Sales › Delivery options). */
class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        if (ShippingMethod::exists()) {
            return;
        }

        ShippingMethod::create(['name' => 'Standard delivery', 'zone' => 'za', 'price' => 10000, 'free_over' => 150000, 'estimate' => '2–5 working days', 'sort_order' => 1]);
        ShippingMethod::create(['name' => 'Express delivery', 'zone' => 'za', 'price' => 18000, 'estimate' => '1–2 working days', 'sort_order' => 2]);
        ShippingMethod::create(['name' => 'International delivery', 'zone' => 'international', 'price' => 65000, 'estimate' => '5–10 working days', 'sort_order' => 3]);
    }
}
