<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleReportExporter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function downloadExcel(array $data, string $filename): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($data);

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
    public function downloadPdf(array $data, string $filename): Response
    {
        $fontDirectory = storage_path('framework/dompdf-fonts');
        File::ensureDirectoryExists($fontDirectory);

        $options = new Options;
        $options->set('defaultFont', 'IBM Plex Sans Thai');
        $options->set('fontDir', $fontDirectory);
        $options->set('fontCache', $fontDirectory);
        $options->set('chroot', [base_path(), public_path()]);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $fontMetrics = $dompdf->getFontMetrics();
        $fontMetrics->registerFont(
            ['family' => 'IBM Plex Sans Thai', 'style' => 'normal', 'weight' => 'normal'],
            public_path('ui/fonts/IBMPlexSansThai-Regular.ttf')
        );
        $fontMetrics->registerFont(
            ['family' => 'IBM Plex Sans Thai', 'style' => 'normal', 'weight' => 'bold'],
            public_path('ui/fonts/IBMPlexSansThai-SemiBold.ttf')
        );

        $dompdf->loadHtml(view('admin.reports.schedules-pdf', $data)->render(), 'UTF-8');
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildSpreadsheet(array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name'))
            ->setTitle('รายงานตารางสอน')
            ->setSubject('ตารางสอนที่เผยแพร่แล้วจากระบบ TPSS');

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ตารางสอน');
        $sheet->setShowGridlines(false);
        $sheet->freezePane('A5');

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'รายงานตารางสอน');
        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', $this->periodLabel($data));
        $sheet->fromArray([
            'วันที่',
            'เวลา',
            'รายวิชา',
            'กิจกรรม / หัวข้อ',
            'กลุ่มนักศึกษา',
            'ผู้สอน',
            'ห้อง / สถานที่',
            'ภาคเรียน',
            'หมายเหตุ',
        ], null, 'A4');

        $row = 5;
        foreach ($data['schedules'] as $schedule) {
            $course = $schedule->courseOffering?->course;
            $courseLabel = trim(($course?->course_code ?? '-') . ' ' . ($course?->name_th ?? ''));
            $activityLabel = collect([
                $schedule->activityType?->name,
                $schedule->topic,
            ])->filter()->implode(' : ');

            $sheet->setCellValueExplicit("A{$row}", $this->dateLabel($schedule), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$row}", $this->timeLabel($schedule), DataType::TYPE_STRING);
            $sheet->setCellValue("C{$row}", $courseLabel);
            $sheet->setCellValue("D{$row}", $activityLabel ?: '-');
            $sheet->setCellValue("E{$row}", $schedule->studentGroups->pluck('group_code')->implode(', ') ?: '-');
            $sheet->setCellValue("F{$row}", $schedule->instructors->pluck('formatted_name')->implode(', ') ?: '-');
            $sheet->setCellValue("G{$row}", $schedule->room?->room_name ?: $schedule->room?->room_code ?: '-');
            $sheet->setCellValue("H{$row}", $schedule->term?->name ?: '-');
            $sheet->setCellValue("I{$row}", $schedule->remark ?: '-');

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:I{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F4F8FC');
            }
            $row++;
        }

        $lastRow = max(5, $row - 1);
        if ($row === 5) {
            $sheet->mergeCells('A5:I5');
            $sheet->setCellValue('A5', 'ไม่พบตารางสอนตามตัวกรองที่เลือก');
        } else {
            $sheet->setAutoFilter("A4:I{$lastRow}");
        }

        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'F8FAFC']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '002454']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2:I2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '34506F']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EAF2F8']],
        ]);
        $sheet->getStyle('A4:I4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'F8FAFC']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F477E']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '002454']]],
        ]);
        $sheet->getStyle("A5:I{$lastRow}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        foreach ([
            'A' => 17,
            'B' => 13,
            'C' => 25,
            'D' => 30,
            'E' => 20,
            'F' => 28,
            'G' => 20,
            'H' => 18,
            'I' => 24,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);

        return $spreadsheet;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function periodLabel(array $data): string
    {
        return sprintf(
            'ปีการศึกษา %s | %s | สร้างเมื่อ %s น.',
            $data['year']?->name ?? '-',
            $data['selectedTerm']?->name ?? 'ทุกภาคเรียน',
            now()->timezone(config('app.timezone'))->format('d/m/Y H:i')
        );
    }

    private function dateLabel($schedule): string
    {
        $start = $schedule->start_date?->format('d/m/Y') ?? '-';
        $end = $schedule->end_date?->format('d/m/Y');

        return $end && $end !== $start ? "{$start} - {$end}" : $start;
    }

    private function timeLabel($schedule): string
    {
        return substr((string) $schedule->start_time, 0, 5)
            . ' - '
            . substr((string) $schedule->end_time, 0, 5);
    }
}
