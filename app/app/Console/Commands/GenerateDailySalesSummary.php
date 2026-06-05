<?php

namespace App\Console\Commands;

use App\Services\Reports\DailySalesSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateDailySalesSummary extends Command
{
    protected $signature = 'sales:generate-daily-summary {date? : Date to summarize in Y-m-d format. Defaults to yesterday.}';

    protected $description = 'Generate the daily Excel sales summary.';

    public function handle(DailySalesSummaryService $service): int
    {
        $date = $this->argument('date')
            ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->argument('date'))->startOfDay()
            : CarbonImmutable::yesterday();

        $file = $service->generate($date);

        $this->info(sprintf(
            'Generated daily sales summary for %s: %s',
            $date->toDateString(),
            $file->path,
        ));

        return self::SUCCESS;
    }
}
