<?php

namespace App\Services\Reports;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DailySalesSummaryWorkbook
{
    /**
     * @param array{
     *     date: string,
     *     generated_at: string,
     *     totals: array{orders_count: int, items_count: int, gross_total: float, net_total: float, vat_amount: float, average_order_value: float, vat_percentage: int},
     *     channels: list<array{channel: string, orders_count: int, gross_total: float}>,
     *     items: list<array{display_number: string, name: string, category: string, quantity: int, gross_total: float}>
     * } $summary
     */
    public function write(array $summary, string $absolutePath): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('De Gouden Draak')
            ->setTitle('Dagelijkse verkoop samenvatting '.$summary['date'])
            ->setSubject('Verkoop samenvatting');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Samenvatting');

        $this->writeSummarySheet($sheet, $summary);
        $this->writeItemsSheet($spreadsheet, $summary['items']);

        (new Xlsx($spreadsheet))->save($absolutePath);
        $spreadsheet->disconnectWorksheets();
    }

    /**
     * @param array{
     *     date: string,
     *     generated_at: string,
     *     totals: array{orders_count: int, items_count: int, gross_total: float, net_total: float, vat_amount: float, average_order_value: float, vat_percentage: int},
     *     channels: list<array{channel: string, orders_count: int, gross_total: float}>
     * } $summary
     */
    private function writeSummarySheet(Worksheet $sheet, array $summary): void
    {
        $totals = $summary['totals'];

        $sheet->setCellValue('A1', 'Dagelijkse verkoop samenvatting');
        $sheet->setCellValue('A3', 'Datum');
        $sheet->setCellValue('B3', $summary['date']);
        $sheet->setCellValue('A4', 'Gegenereerd op');
        $sheet->setCellValue('B4', $summary['generated_at']);

        $rows = [
            ['Betaalde orders', $totals['orders_count']],
            ['Verkochte items', $totals['items_count']],
            ['Omzet incl. btw', $totals['gross_total']],
            ['Omzet excl. btw', $totals['net_total']],
            ['Btw '.$totals['vat_percentage'].'%', $totals['vat_amount']],
            ['Gemiddelde orderwaarde', $totals['average_order_value']],
        ];

        $rowNumber = 7;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$rowNumber}", $label);
            $sheet->setCellValue("B{$rowNumber}", $value);
            $rowNumber++;
        }

        $sheet->setCellValue('D7', 'Bron');
        $sheet->setCellValue('E7', 'Orders');
        $sheet->setCellValue('F7', 'Omzet incl. btw');

        $channelRow = 8;
        foreach ($summary['channels'] as $channel) {
            $sheet->setCellValue("D{$channelRow}", $this->channelLabel($channel['channel']));
            $sheet->setCellValue("E{$channelRow}", $channel['orders_count']);
            $sheet->setCellValue("F{$channelRow}", $channel['gross_total']);
            $channelRow++;
        }

        $this->styleTitle($sheet, 'A1:F1');
        $this->styleHeader($sheet, 'A7:B7');
        $this->styleHeader($sheet, 'D7:F7');
        $this->styleMoney($sheet, 'B9:B12');
        $this->styleMoney($sheet, 'F8:F'.max(8, $channelRow - 1));
        $this->autosize($sheet, ['A', 'B', 'D', 'E', 'F']);
    }

    /**
     * @param  list<array{display_number: string, name: string, category: string, quantity: int, gross_total: float}>  $items
     */
    private function writeItemsSheet(Spreadsheet $spreadsheet, array $items): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Producten');

        $headers = ['Nummer', 'Gerecht', 'Categorie', 'Aantal', 'Omzet incl. btw'];
        foreach ($headers as $index => $header) {
            $column = chr(65 + $index);
            $sheet->setCellValue("{$column}1", $header);
        }

        $rowNumber = 2;
        foreach ($items as $item) {
            $sheet->setCellValueExplicit("A{$rowNumber}", $item['display_number'], DataType::TYPE_STRING);
            $sheet->setCellValue("B{$rowNumber}", $item['name']);
            $sheet->setCellValue("C{$rowNumber}", $item['category']);
            $sheet->setCellValue("D{$rowNumber}", $item['quantity']);
            $sheet->setCellValue("E{$rowNumber}", $item['gross_total']);
            $rowNumber++;
        }

        $lastRow = max(1, $rowNumber - 1);

        $this->styleHeader($sheet, 'A1:E1');
        if ($lastRow >= 2) {
            $this->styleMoney($sheet, "E2:E{$lastRow}");
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:E{$lastRow}");
        $this->autosize($sheet, ['A', 'B', 'C', 'D', 'E']);
    }

    private function styleTitle(Worksheet $sheet, string $range): void
    {
        $sheet->mergeCells($range);
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '7F1D1D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
    }

    private function styleHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '991B1B'],
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E7E5E4']],
            ],
        ]);
    }

    private function styleMoney(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('"EUR" #,##0.00');
    }

    /**
     * @param  list<string>  $columns
     */
    private function autosize(Worksheet $sheet, array $columns): void
    {
        foreach ($columns as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function channelLabel(string $channel): string
    {
        return match ($channel) {
            'cashdesk', 'takeaway' => 'Kassa',
            'tablet' => 'Tablet',
            default => ucfirst($channel),
        };
    }
}
