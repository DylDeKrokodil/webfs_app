<?php

namespace App\Services;

use DeepL\Translator;
use Illuminate\Support\Facades\Cache;

class TranslationService
{
    protected ?Translator $translator = null;
    protected string $authKey;

    public function __construct()
    {
        $this->authKey = env('DEEPL_AUTH_KEY', '');
    }

    protected function getTranslator(): ?Translator
    {
        if (!$this->translator && $this->authKey) {
            $this->translator = new Translator($this->authKey);
        }
        return $this->translator;
    }

    protected function mapLanguageCode(string $lang): string
    {
        $lang = strtolower($lang);
        $map = [
            'en' => 'en-GB',
            'pt' => 'pt-PT',
        ];

        return $map[$lang] ?? $lang;
    }

    public function translate(string $text, string $targetLang, string $sourceLang = 'nl'): string
    {
        if (empty($text) || strtolower($targetLang) === strtolower($sourceLang)) {
            return $text;
        }

        // Create a unique cache key based on the text and target language
        $cacheKey = 'trans_' . $targetLang . '_' . md5($text);

        return Cache::store('file')->rememberForever($cacheKey, function () use ($text, $targetLang, $sourceLang) {
            $translator = $this->getTranslator();
            if (!$translator) {
                return $text;
            }

            try {
                $deeplTarget = $this->mapLanguageCode($targetLang);
                
                // Protect "De Gouden Draak" from translation
                $protectedText = str_ireplace('De Gouden Draak', '<keep>De Gouden Draak</keep>', $text);
                // Protect {variable} placeholders
                $protectedText = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '<keep>{$1}</keep>', $protectedText);

                $result = $translator->translateText($protectedText, $sourceLang, $deeplTarget, [
                    'tag_handling' => 'xml',
                    'ignore_tags' => ['keep']
                ]);
                
                $finalText = str_replace(['<keep>', '</keep>'], '', $result->text);
                return $finalText;
            } catch (\Exception $e) {
                \Log::error('DeepL Translation Error: ' . $e->getMessage());
                return $text; // Fallback to original text
            }
        });
    }

    /**
     * Batch translate an array of strings to save API requests
     */
    public function translateArray(array $texts, string $targetLang, string $sourceLang = 'nl'): array
    {
        if (empty($texts) || strtolower($targetLang) === strtolower($sourceLang)) {
            return $texts;
        }

        $results = [];
        $toTranslate = [];
        $map = [];

        foreach ($texts as $index => $text) {
            if (empty($text)) {
                $results[$index] = $text;
                continue;
            }

            $cacheKey = 'trans_' . $targetLang . '_' . md5($text);
            if (Cache::store('file')->has($cacheKey)) {
                $results[$index] = Cache::store('file')->get($cacheKey);
            } else {
                $toTranslate[] = $text;
                $map[] = $index;
            }
        }

        if (!empty($toTranslate)) {
            $translator = $this->getTranslator();
            if ($translator) {
                try {
                    $deeplTarget = $this->mapLanguageCode($targetLang);

                    $protectedTexts = array_map(function($t) {
                        $t = str_ireplace('De Gouden Draak', '<keep>De Gouden Draak</keep>', $t);
                        return preg_replace('/\{([a-zA-Z0-9_]+)\}/', '<keep>{$1}</keep>', $t);
                    }, $toTranslate);

                    $apiResults = $translator->translateText($protectedTexts, $sourceLang, $deeplTarget, [
                        'tag_handling' => 'xml',
                        'ignore_tags' => ['keep']
                    ]);
                    
                    foreach ($apiResults as $i => $apiResult) {
                        $originalIndex = $map[$i];
                        $translatedText = str_replace(['<keep>', '</keep>'], '', $apiResult->text);
                        $results[$originalIndex] = $translatedText;
                        
                        $cacheKey = 'trans_' . $targetLang . '_' . md5($toTranslate[$i]);
                        Cache::store('file')->forever($cacheKey, $translatedText);
                    }
                } catch (\Exception $e) {
                    \Log::error('DeepL Batch Translation Error: ' . $e->getMessage());
                    foreach ($map as $index) {
                        $results[$index] = $texts[$index];
                    }
                }
            } else {
                foreach ($map as $index) {
                    $results[$index] = $texts[$index];
                }
            }
        }

        ksort($results);
        return $results;
    }
}
