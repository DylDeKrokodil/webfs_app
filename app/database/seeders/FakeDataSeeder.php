<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Review;
use App\Models\ReviewInvite;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FakeDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Genereren van nepdata... dit kan even duren.');

        $menuItems = MenuItem::all();

        if ($menuItems->isEmpty()) {
            $this->command->warn('Geen menu-items gevonden. Draai eerst de import.');
            return;
        }

        // Genereer 5000 bestellingen verspreid over de periode vanaf maart 2021
        for ($i = 0; $i < 5000; $i++) {
            $order = Order::factory()->create();
            
            $numLines = rand(1, 6);
            $subtotal = 0;

            for ($j = 0; $j < $numLines; $j++) {
                $item = $menuItems->random();
                $qty = rand(1, 3);
                $lineTotal = $item->price * $qty;

                OrderLine::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_price' => $item->price,
                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $order->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            // 30% kans op een recensie voor deze bestelling
            if (rand(1, 100) <= 30) {
                $invite = ReviewInvite::create([
                    'token' => Str::random(32),
                    'table_code' => $order->table_code ?? 'KASSA',
                    'order_ids' => [$order->id],
                    'order_fingerprint' => Str::random(16),
                    'paid_at' => $order->paid_at,
                    'submitted_at' => $order->paid_at->addMinutes(rand(10, 60)),
                ]);

                Review::factory()->create([
                    'review_invite_id' => $invite->id,
                    'created_at' => $invite->submitted_at,
                ]);
            }
        }

        $this->command->info('Nepdata succesvol gegenereerd!');
    }
}
