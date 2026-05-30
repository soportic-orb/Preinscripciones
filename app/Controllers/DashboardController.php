<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Rbac;
use App\Core\Request;

/**
 * Paneles tras el login. En esta fase muestra un panel base por rol;
 * las funcionalidades de estudiante y gestión se amplían en bloques posteriores.
 */
final class DashboardController extends Controller
{
    public function index(Request $request): never
    {
        $user = Auth::user();
        // Redirigir al staff a su panel de gestión.
        if ($user !== null && Rbac::isStaff($user)) {
            $this->redirect('/gestion');
        }
        $this->view('dashboard/student', [
            'title' => __('dashboard.student_title'),
            'user' => $user,
        ]);
    }

    public function management(Request $request): never
    {
        $user = Auth::user();
        $this->view('dashboard/management', [
            'title' => __('dashboard.management_title'),
            'user' => $user,
        ]);
    }
}
