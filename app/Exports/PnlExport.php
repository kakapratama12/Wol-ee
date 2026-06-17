<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PnlExport
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function download(array $report, string $periodLabel): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('P&L');

        $sheet->setCellValue('A1', "Laporan P&L - {$periodLabel}");
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $rupiah = '#,##0';
        $row = 3;

        $write = function (string $label, float $value, bool $bold = false, bool $negative = false) use ($sheet, &$row, $rupiah) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $negative ? -1 * $value : $value);
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode("\"Rp \"{$rupiah};\"Rp \"-{$rupiah}");
            if ($bold) {
                $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
            }
            $row++;
        };

        $write('Revenue', (float) $report['revenue']);
        $write('COGS', (float) $report['cogs'], false, true);
        $write('Gross Profit', (float) $report['gross_profit'], true);
        $row++;

        $sheet->setCellValue("A{$row}", 'Expenses');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        foreach ($report['expenses'] as $expense) {
            $write(ucfirst((string) $expense['category']), (float) $expense['amount'], false, true);
        }
        $write('Total Expenses', (float) $report['total_expenses'], true, true);
        $row++;

        $write('Laba (Rugi) bersih', (float) $report['net_profit'], true);

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getStyle('B3:B'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $writer = new Xlsx($spreadsheet);
        $filename = 'pnl-'.str_replace(' ', '-', strtolower($periodLabel)).'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
