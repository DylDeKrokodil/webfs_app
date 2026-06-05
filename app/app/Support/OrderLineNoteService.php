<?php

namespace App\Support;

use App\Models\OrderLine;
use App\Models\OrderLineNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OrderLineNoteService
{
    public const MAX_NOTES_PER_LINE = 5;

    public const MAX_NOTE_LENGTH = 160;

    public function validationRules(): array
    {
        return [
            'lines.*.notes' => ['sometimes', 'array', 'max:'.self::MAX_NOTES_PER_LINE],
            'lines.*.notes.*' => ['nullable', 'string', 'max:'.self::MAX_NOTE_LENGTH],
        ];
    }

    public function prepareLines(array $lines): Collection
    {
        return collect($lines)
            ->map(fn (array $line): array => [
                'menu_item_id' => (int) $line['menu_item_id'],
                'quantity' => (int) $line['quantity'],
                'notes' => $this->cleanNotes($line['notes'] ?? []),
            ])
            ->groupBy(fn (array $line): string => implode('|', [
                $line['menu_item_id'],
                $line['notes']->pluck('normalized_note')->implode("\n"),
            ]))
            ->map(function (Collection $lines): array {
                $firstLine = $lines->first();

                return [
                    'menu_item_id' => $firstLine['menu_item_id'],
                    'quantity' => (int) $lines->sum('quantity'),
                    'notes' => $firstLine['notes'],
                ];
            })
            ->values();
    }

    public function createNotes(OrderLine $orderLine, Collection $notes): void
    {
        $notes->each(fn (array $note): OrderLineNote => $orderLine->notes()->create($note));
    }

    public function serializeNotes(OrderLine $orderLine): array
    {
        return $orderLine->notes
            ->map(fn (OrderLineNote $note): string => $note->note)
            ->values()
            ->all();
    }

    private function cleanNotes(array $notes): Collection
    {
        return collect($notes)
            ->map(fn ($note): string => $this->cleanNote((string) $note))
            ->filter()
            ->unique(fn (string $note): string => $this->normalizeNote($note))
            ->take(self::MAX_NOTES_PER_LINE)
            ->map(fn (string $note): array => [
                'note' => $note,
                'normalized_note' => $this->normalizeNote($note),
            ])
            ->values();
    }

    private function cleanNote(string $note): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $note));
    }

    private function normalizeNote(string $note): string
    {
        return Str::lower($this->cleanNote($note));
    }
}
