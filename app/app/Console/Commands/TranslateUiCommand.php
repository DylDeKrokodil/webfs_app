<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DeepL\Translator;
use Illuminate\Support\Arr;

class TranslateUiCommand extends Command
{
    protected $signature = 'translate:ui {--target=en}';
    protected $description = 'Translate UI strings from nl.json to target language using DeepL';

    public function handle()
    {
        $targetLang = $this->option('target');
        $deeplTargetLang = $targetLang;
        if (strtolower($targetLang) === 'en') {
            $deeplTargetLang = 'en-GB';
        } elseif (strtolower($targetLang) === 'pt') {
            $deeplTargetLang = 'pt-PT';
        }
        
        $authKey = env('DEEPL_AUTH_KEY');

        if (!$authKey) {
            $this->error('DEEPL_AUTH_KEY is not set in .env');
            return 1;
        }

        $sourceFile = resource_path('js/locales/nl.json');
        if (!file_exists($sourceFile)) {
            $this->error("Source file $sourceFile not found.");
            return 1;
        }

        $nlData = json_decode(file_get_contents($sourceFile), true);
        $flattened = Arr::dot($nlData);
        
        $keys = array_keys($flattened);
        $texts = array_values($flattened);

        $this->info("Translating " . count($texts) . " strings to " . strtoupper($targetLang) . "...");

        try {
            $translator = new Translator($authKey);
            
            $protectedTexts = array_map(function($t) {
                $t = str_ireplace('De Gouden Draak', '<keep>De Gouden Draak</keep>', $t);
                // Protect {variable} placeholders
                return preg_replace('/\{([a-zA-Z0-9_]+)\}/', '<keep>{$1}</keep>', $t);
            }, $texts);

            $results = $translator->translateText($protectedTexts, 'nl', $deeplTargetLang, [
                'tag_handling' => 'xml',
                'ignore_tags' => ['keep']
            ]);
            
            $translatedTexts = array_map(fn($r) => str_replace(['<keep>', '</keep>'], '', $r->text), $results);
            $translatedFlattened = array_combine($keys, $translatedTexts);
            
            $translatedData = [];
            foreach ($translatedFlattened as $key => $value) {
                Arr::set($translatedData, $key, $value);
            }

            $targetFile = resource_path("js/locales/{$targetLang}.json");
            file_put_contents($targetFile, json_encode($translatedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info("Translation saved to $targetFile");
        } catch (\Exception $e) {
            $this->error('Translation failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
