<?php

namespace App\Http\Controllers;

use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class LocaleController extends Controller
{
    public function show(string $lang, TranslationService $translator): JsonResponse
    {
        $lang = strtolower($lang);
        $sourcePath = resource_path('js/locales/nl.json');
        $targetPath = resource_path("js/locales/{$lang}.json");

        if (!File::exists($sourcePath)) {
            return response()->json(['error' => 'Source locale not found'], 404);
        }

        // If it's the source language or already exists, return it
        if ($lang === 'nl' || File::exists($targetPath)) {
            return response()->json(json_decode(File::get($lang === 'nl' ? $sourcePath : $targetPath), true));
        }

        // Otherwise, generate it on the fly
        try {
            $nlData = json_decode(File::get($sourcePath), true);
            $flattened = Arr::dot($nlData);
            
            $keys = array_keys($flattened);
            $texts = array_values($flattened);

            // Translate in batch
            $translatedTexts = $translator->translateArray($texts, $lang);
            $translatedFlattened = array_combine($keys, $translatedTexts);
            
            $translatedData = [];
            foreach ($translatedFlattened as $key => $value) {
                Arr::set($translatedData, $key, $value);
            }

            // Cache it to disk for future requests
            File::ensureDirectoryExists(resource_path('js/locales'));
            File::put($targetPath, json_encode($translatedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return response()->json($translatedData);
        } catch (\Exception $e) {
            \Log::error("Failed to generate locale {$lang}: " . $e->getMessage());
            return response()->json(json_decode(File::get($sourcePath), true)); // Fallback to NL
        }
    }
}
