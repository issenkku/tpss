<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkloadReportExporter
{
    /**
     * @param  array{
     *     year: mixed,
     *     selectedPeriodLabel: string,
     *     instructors: iterable,
     *     instructorHours: array,
     *     instructorWeeklyAverages: array<int, float>,
     *     teachingWeeks: int,
     *     hoursPerWeek: int
     * }  $data
     */
    public function download(array $data, string $filename): StreamedResponse
    {
        $spreadsheet = $this->build($data);

        return response()->streamDownload(
            function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
                $spreadsheet->disconnectWorksheets();
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function build(array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle('รายงานภาระงานสอน')
            ->setSubject('ชั่วโมงสอนจริงจากตารางที่อนุมัติแล้ว');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ภาระงานสอน');
        $sheet->setShowGridlines(false);
        $sheet->freezePane('A5');

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'รายงานภาระงานสอน');
        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue(
            'A2',
            sprintf(
                'ปีการศึกษา %s • %s • สร้างเมื่อ %s น.',
                $data['year']?->name ?? '-',
                $data['selectedPeriodLabel'],
                now()->timezone(config('app.timezone'))->format('d/m/Y H:i')
            )
        );

        $headers = [
            'รหัส',
            'ชื่อ-นามสกุล',
            'ภาควิชา',
            'ชั่วโมงสะสมถึงวันนี้',
            'ชั่วโมงตามช่วงที่เลือก',
            'เฉลี่ยต่อสัปดาห์ (ชม.)',
            'ชั่วโมงฝึกปฏิบัติ',
            'เกณฑ์ทั้งปี (ชม.)',
            'ใช้ไปเทียบเกณฑ์ทั้งปี (%)',
        ];
        $sheet->fromArray($headers, null, 'A4');

        $calculator = new WorkloadCalculator;
        $row = 5;
        foreach ($data['instructors'] as $instructor) {
            $hours = $data['instructorHours'][$instructor->id]
                ?? ['accrued' => 0, 'total' => 0, 'by_category' => []];
            $quota = $calculator->quotaFor(
                $instructor->instructorProfile,
                $data['teachingWeeks'],
                $data['hoursPerWeek']
            );

            $sheet->setCellValueExplicit("A{$row}", $instructor->employee_id ?: '-', DataType::TYPE_STRING);
            $sheet->fromArray([
                $instructor->formatted_name,
                $instructor->instructorProfile?->department?->name ?? '-',
                (float) $hours['accrued'],
                (float) $hours['total'],
                (float) ($data['instructorWeeklyAverages'][$instructor->id] ?? 0),
                (float) ($hours['by_category']['practicum'] ?? 0),
                $quota !== null ? round($quota, 1) : null,
            ], null, "B{$row}");

            if ($quota !== null && $quota > 0) {
                $sheet->setCellValue("I{$row}", "=IFERROR(E{$row}/H{$row},0)");
            }

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:I{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F4F8FC');
            }

            $row++;
        }

        $lastDataRow = $row - 1;
        if ($lastDataRow >= 5) {
            $totalRow = $lastDataRow + 1;
            $sheet->setCellValue("A{$totalRow}", 'รวม');
            $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
            foreach (['D', 'E', 'F', 'G', 'H'] as $column) {
                $sheet->setCellValue("{$column}{$totalRow}", "=SUM({$column}5:{$column}{$lastDataRow})");
            }
            $sheet->setCellValue("I{$totalRow}", "=IFERROR(E{$totalRow}/H{$totalRow},0)");
            $sheet->setAutoFilter("A4:I{$lastDataRow}");
            $sheet->getStyle("A{$totalRow}:I{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '0B2D5C']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCEAF7']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0B2D5C']]],
            ]);
        } else {
            $sheet->mergeCells('A5:I5');
            $sheet->setCellValue('A5', 'ไม่พบข้อมูลภาระงานสอนในช่วงที่เลือก');
            $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B2D5C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2:I2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '34506F']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF2F8']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A4:I4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F477E']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0B2D5C']]],
        ]);

        $sheet->getStyle("A5:I{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B5:C{$row}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("D5:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("D5:H{$row}")->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle("I5:I{$row}")->getNumberFormat()->setFormatCode('0%');

        foreach ([
            'A' => 13,
            'B' => 29,
            'C' => 36,
            'D' => 19,
            'E' => 20,
            'F' => 21,
            'G' => 19,
            'H' => 19,
            'I' => 23,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(10);
        $sheet->getRowDimension(4)->setRowHeight(42);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.4)
            ->setRight(0.3)
            ->setBottom(0.4)
            ->setLeft(0.3);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $sheet->getHeaderFooter()->setOddFooter('&Lสร้างจากระบบ TPSS&Cหน้า &P จาก &N');

        return $spreadsheet;
    }
}
