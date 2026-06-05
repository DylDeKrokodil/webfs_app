<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneratedFile;
use App\Services\Reports\DailySalesSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesSummaryController extends Controller
{
    public function index(): JsonResponse
    {
        $summaries = GeneratedFile::query()
            ->where('type', DailySalesSummaryService::FILE_TYPE)
            ->latest('generated_at')
            ->limit(100)
            ->get()
            ->map(fn (GeneratedFile $file): array => [
                'id' => $file->id,
                'date' => $file->metadata['date'] ?? null,
                'original_name' => $file->original_name,
                'generated_at' => $file->generated_at?->toIso8601String(),
                'orders_count' => $file->metadata['orders_count'] ?? 0,
                'items_count' => $file->metadata['items_count'] ?? 0,
                'gross_total' => $file->metadata['gross_total'] ?? 0,
                'download_url' => route('admin.sales-summaries.download', $file),
            ]);

        return response()->json([
            'data' => $summaries,
        ]);
    }

    public function download(GeneratedFile $generatedFile): BinaryFileResponse
    {
        abort_unless($generatedFile->type === DailySalesSummaryService::FILE_TYPE, 404);
        abort_unless(Storage::disk('local')->exists($generatedFile->path), 404);

        return response()->download(
            Storage::disk('local')->path($generatedFile->path),
            $generatedFile->original_name ?? basename($generatedFile->path),
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }
}
