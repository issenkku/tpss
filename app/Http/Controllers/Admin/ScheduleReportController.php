<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ScheduleReportExporter;
use App\Services\ScheduleReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleReportController extends Controller
{
    public function index(Request $request, ScheduleReportService $reports)
    {
        $data = $reports->data($request);
        $routes = $this->routeNames((string) $request->session()->get('active_role'));

        return view('admin.reports.schedules', [
            ...$data,
            ...$routes,
        ]);
    }

    public function excel(
        Request $request,
        ScheduleReportService $reports,
        ScheduleReportExporter $exporter
    ): StreamedResponse {
        $data = $reports->data($request, paginate: false);

        return $exporter->downloadExcel($data, $this->filename($data, 'xlsx'));
    }

    public function pdf(
        Request $request,
        ScheduleReportService $reports,
        ScheduleReportExporter $exporter
    ): Response {
        $data = $reports->data($request, paginate: false);

        return $exporter->downloadPdf($data, $this->filename($data, 'pdf'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function filename(array $data, string $extension): string
    {
        $year = $data['year']?->name ?? 'all';
        $term = $data['termSequence'] ? '-term-' . $data['termSequence'] : '';

        return "schedule-report-{$year}{$term}.{$extension}";
    }

    /**
     * @return array{reportRouteName: string, excelRouteName: string, pdfRouteName: string, reportContextLabel: string}
     */
    private function routeNames(string $activeRole): array
    {
        return match ($activeRole) {
            'staff' => [
                'reportRouteName' => 'staff.reports.schedules',
                'excelRouteName' => 'staff.reports.schedules.excel',
                'pdfRouteName' => 'staff.reports.schedules.pdf',
                'reportContextLabel' => 'รายงาน / เจ้าหน้าที่',
            ],
            'executive' => [
                'reportRouteName' => 'approver.reports.schedules',
                'excelRouteName' => 'approver.reports.schedules.excel',
                'pdfRouteName' => 'approver.reports.schedules.pdf',
                'reportContextLabel' => 'ตารางทั้งหมด / ผู้บริหาร',
            ],
            default => [
                'reportRouteName' => 'admin.reports.schedules',
                'excelRouteName' => 'admin.reports.schedules.excel',
                'pdfRouteName' => 'admin.reports.schedules.pdf',
                'reportContextLabel' => 'ตารางและรายงาน / ผู้ดูแลระบบ',
            ],
        };
    }
}
