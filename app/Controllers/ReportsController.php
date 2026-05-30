<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Services\ReportService;

/**
 * Informes, KPIs y exportación CSV (staff).
 */
final class ReportsController extends Controller
{
    public function index(Request $request): never
    {
        $svc = new ReportService();
        $this->view('management/reports/index', [
            'title' => __('reports.title'),
            'user' => Auth::user(),
            'kpis' => $svc->kpis(),
            'occupancy' => $svc->editionOccupancy(),
        ]);
    }

    /** Exporta un informe en CSV. */
    public function export(Request $request): never
    {
        $type = $request->str('type', 'students');
        [$headers, $rows] = (new ReportService())->export($type);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="iem-' . $type . '-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        // BOM para Excel.
        fwrite($out, "\xEF\xBB\xBF");
        // PHP 8.4: el parámetro $escape debe indicarse explícitamente ('' = estándar CSV).
        fputcsv($out, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        fclose($out);
        exit;
    }
}
