<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Rbac;
use App\Core\Request;
use App\Core\Response;
use App\Models\CourseEdition;
use App\Models\Preinscription;
use App\Services\ExportService;

/**
 * Exportaciones e integraciones: AlexiaEdu (CSV) e iCal.
 */
final class ExportController extends Controller
{
    /** CSV de matriculados de una edición para AlexiaEdu (staff). */
    public function alexia(Request $request): never
    {
        $editionId = (int) $request->route('id');
        [$headers, $rows] = (new ExportService())->alexiaCsv($editionId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="alexia-edicion-' . $editionId . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($out, $row, ',', '"', '');
        }
        fclose($out);
        exit;
    }

    /** Calendario iCal de una edición (estudiantes matriculados y staff). */
    public function ical(Request $request): never
    {
        $editionId = (int) $request->route('id');
        $ics = (new ExportService())->ical($editionId);
        if ($ics === null) {
            Response::html('<h1>404</h1>', 404);
        }
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="edicion-' . $editionId . '.ics"');
        echo $ics;
        exit;
    }
}
