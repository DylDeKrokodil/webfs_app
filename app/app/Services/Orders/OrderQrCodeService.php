<?php

namespace App\Services\Orders;

use App\Models\Order;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class OrderQrCodeService
{
    public function generateForOrder(Order $order): string
    {
        $lines = $order->lines()->with('menuItem')->get();
        
        $text = "Bestelnummer: #{$order->id}\n";
        $text .= "Gerechten:\n";
        
        foreach ($lines as $line) {
            $item = $line->menuItem;
            $number = trim(($item?->number ?? '').($item?->suffix ?? ''));
            $name = $item?->name ?? 'Onbekend gerecht';
            $text .= "- {$number}. {$name} (x{$line->quantity})\n";
        }

        return (new Builder(
            writer: new PngWriter,
            data: $text,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getDataUri();
    }
}
