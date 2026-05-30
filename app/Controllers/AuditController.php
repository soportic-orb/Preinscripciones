<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

/**
 * Visor de auditoría con filtros (solo admin). Solo lectura.
 */
final class AuditController extends Controller
{
    public function index(Request $request): never
    {
        $db = Database::instance();
        $filters = [
            'action' => $request->str('action'),
            'actor' => $request->str('actor'),
            'from' => $request->str('from'),
            'to' => $request->str('to'),
        ];
        $page = max(1, (int) $request->str('page', '1'));
        $perPage = 50;

        $where = '1=1';
        $params = [];
        if ($filters['action'] !== '') {
            $where .= ' AND a.action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }
        if ($filters['actor'] !== '') {
            $where .= ' AND u.email LIKE ?';
            $params[] = '%' . $filters['actor'] . '%';
        }
        if ($filters['from'] !== '') {
            $where .= ' AND a.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if ($filters['to'] !== '') {
            $where .= ' AND a.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $total = (int) $db->scalar(
            "SELECT COUNT(*) FROM {audit_log} a LEFT JOIN {users} u ON u.id = a.actor_id WHERE {$where}",
            $params,
        );
        $rows = $db->fetchAll(
            "SELECT a.*, u.email AS actor_email FROM {audit_log} a
             LEFT JOIN {users} u ON u.id = a.actor_id
             WHERE {$where} ORDER BY a.id DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params,
        );

        $this->view('management/audit/index', [
            'title' => __('audit.title'),
            'user' => Auth::user(),
            'rows' => $rows,
            'filters' => $filters,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'total' => $total,
        ]);
    }
}
