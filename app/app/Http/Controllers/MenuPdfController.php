<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\Promotion;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

class MenuPdfController extends Controller
{
    public function __invoke(): Response
    {
        $generatedAt = now();

        $html = view('pdf.public-menu', [
            'categories' => $this->activeMenuCategories(),
            'promotions' => $this->activePromotions(),
            'generatedAt' => $generatedAt,
        ])->render();

        $options = new Options;
        $options->set('chroot', public_path());
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="menukaart-de-gouden-draak.pdf"',
        ]);
    }

    private function activeMenuCategories()
    {
        return MenuCategory::query()
            ->where('is_active', true)
            ->whereHas('items', fn (Builder $query): Builder => $query->where('is_active', true))
            ->with(['items' => fn ($query) => $query
                ->where('is_active', true)
                ->orderByRaw('number IS NULL')
                ->orderBy('number')
                ->orderBy('suffix')
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function activePromotions()
    {
        $today = today();

        return Promotion::query()
            ->with(['menuItems' => fn ($query) => $query
                ->where('menu_items.is_active', true)
                ->with('category')
                ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.menu_category_id')
                ->select('menu_items.*')
                ->orderBy('menu_categories.sort_order')
                ->orderByRaw('menu_items.number IS NULL')
                ->orderBy('menu_items.number')
                ->orderBy('menu_items.suffix')
                ->orderBy('menu_items.name')])
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $today)
            ->whereDate('ends_at', '>=', $today)
            ->orderBy('starts_at')
            ->orderBy('title')
            ->get();
    }
}
