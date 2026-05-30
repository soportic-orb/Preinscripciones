<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Models\Course;

/**
 * CRUD de cursos del catálogo (staff: owner/admin/gestor).
 */
final class CoursesController extends Controller
{
    public function index(Request $request): never
    {
        $this->view('management/courses/index', [
            'title' => __('catalog.courses'),
            'user' => Auth::user(),
            'courses' => Course::all(),
        ]);
    }

    public function create(Request $request): never
    {
        $this->view('management/courses/edit', [
            'title' => __('catalog.new_course'),
            'user' => Auth::user(),
            'course' => null,
            'locales' => Config::locales(),
            'courses' => Course::all(),
        ]);
    }

    public function edit(Request $request): never
    {
        $course = Course::find((int) $request->route('id'));
        if ($course === null) {
            Flash::error(__('catalog.course_not_found'));
            $this->redirect('/gestion/cursos');
        }
        $this->view('management/courses/edit', [
            'title' => __('catalog.edit_course'),
            'user' => Auth::user(),
            'course' => $course,
            'locales' => Config::locales(),
            'courses' => Course::all(),
        ]);
    }

    public function store(Request $request): never
    {
        $data = $this->collect($request);
        if ($data['code'] === '') {
            Flash::error(__('catalog.code_required'));
            $this->redirect('/gestion/cursos/nuevo');
        }
        $id = Course::store($data);
        Audit::log('course.create', Auth::id(), 'course', $id, ['code' => $data['code']], $request->ip());
        Flash::success(__('catalog.course_saved'));
        $this->redirect('/gestion/cursos/' . $id . '/editar');
    }

    public function update(Request $request): never
    {
        $id = (int) $request->route('id');
        Database::instance()->update('courses', $this->collect($request) + ['updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        Audit::log('course.update', Auth::id(), 'course', $id, [], $request->ip());
        Flash::success(__('catalog.course_saved'));
        $this->redirect('/gestion/cursos/' . $id . '/editar');
    }

    private function collect(Request $request): array
    {
        $title = $desc = $access = [];
        foreach (Config::locales() as $loc) {
            $title[$loc] = trim((string) ($request->post['title'][$loc] ?? ''));
            $desc[$loc] = trim((string) ($request->post['description'][$loc] ?? ''));
            $access[$loc] = trim((string) ($request->post['access_requirements'][$loc] ?? ''));
        }
        $prereq = (int) $request->input('prerequisite_course_id', 0);
        return [
            'code' => trim((string) $request->input('code', '')),
            'title' => json_encode($title, JSON_UNESCAPED_UNICODE),
            'description' => json_encode($desc, JSON_UNESCAPED_UNICODE),
            'access_requirements' => json_encode($access, JSON_UNESCAPED_UNICODE),
            'course_type' => in_array($request->input('course_type'), Course::TYPES, true) ? $request->input('course_type') : 'reglado',
            'area' => trim((string) $request->input('area', '')),
            'prerequisite_course_id' => $prereq > 0 ? $prereq : null,
            'price' => $request->str('price') !== '' ? (float) $request->str('price') : null,
            'is_active' => $request->input('is_active') ? 1 : 0,
        ];
    }
}
