<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\Promotion;
use App\Services\TranslationService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MenuPdfController extends Controller
{
    public function __invoke(Request $request, TranslationService $translator): Response
    {
        $lang = $request->query('lang', 'nl');
        app()->setLocale($lang);

        $generatedAt = now();
        $categories = $this->activeMenuCategories();
        $promotions = $this->activePromotions();

        if (strtolower($lang) !== 'nl') {
            $categories = $this->translateCategories($categories, $translator, $lang);
            $promotions = $this->translatePromotions($promotions, $translator, $lang);
        }

        $html = view('pdf.public-menu', [
            'categories' => $categories,
            'promotions' => $promotions,
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

        $filename = $lang === 'nl' ? 'menukaart-de-gouden-draak.pdf' : "menu-de-gouden-draak-{$lang}.pdf";

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function translateCategories($categories, TranslationService $translator, string $lang)
    {
        $categoryNames = $categories->pluck('name')->toArray();
        $translatedCategoryNames = $translator->translateArray($categoryNames, $lang);
        $categoryLookup = array_combine($categoryNames, $translatedCategoryNames);

        $itemNames = [];
        $itemDescriptions = [];

        foreach ($categories as $category) {
            foreach ($category->items as $item) {
                if ($item->name) $itemNames[] = $item->name;
                if ($item->description) $itemDescriptions[] = $item->description;
            }
        }

        $translatedItemNames = $translator->translateArray(array_unique($itemNames), $lang);
        $translatedItemDescriptions = $translator->translateArray(array_unique($itemDescriptions), $lang);

        $itemNameLookup = array_combine(array_unique($itemNames), $translatedItemNames);
        $itemDescLookup = array_combine(array_unique($itemDescriptions), $translatedItemDescriptions);

        foreach ($categories as $category) {
            $category->name = $categoryLookup[$category->name] ?? $category->name;
            foreach ($category->items as $item) {
                $item->name = $itemNameLookup[$item->name] ?? $item->name;
                $item->description = $itemDescLookup[$item->description] ?? $item->description;
            }
        }

        return $categories;
    }

    private function translatePromotions($promotions, TranslationService $translator, string $lang)
    {
        $titles = $promotions->pluck('title')->toArray();
        $descriptions = $promotions->pluck('description')->filter()->toArray();

        $translatedTitles = $translator->translateArray($titles, $lang);
        $translatedDescriptions = $translator->translateArray($descriptions, $lang);

        $titleLookup = array_combine($titles, $translatedTitles);
        $descLookup = array_combine($descriptions, $translatedDescriptions);

        $itemNames = [];
        foreach ($promotions as $promotion) {
            foreach ($promotion->menuItems as $item) {
                if ($item->name) $itemNames[] = $item->name;
            }
        }

        $translatedItemNames = $translator->translateArray(array_unique($itemNames), $lang);
        $itemNameLookup = array_combine(array_unique($itemNames), $translatedItemNames);

        foreach ($promotions as $promotion) {
            $promotion->title = $titleLookup[$promotion->title] ?? $promotion->title;
            $promotion->description = $descLookup[$promotion->description] ?? $promotion->description;
            foreach ($promotion->menuItems as $item) {
                $item->name = $itemNameLookup[$item->name] ?? $item->name;
            }
        }

        return $promotions;
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
