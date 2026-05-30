<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Flash;
use App\Core\Request;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\DocumentRequirement;

/**
 * CRUD de convocatorias/ediciones y sus requisitos documentales (staff).
 */
final class EditionsController extends Controller
{
    public function create(Request $request): never
    {
        $course = Course::find((int) $request->route('course'));
        if ($course === null) {
            Flash::error(__('catalog.course_not_found'));
            $this->redirect('/gestion/cursos');
        }
        $this->view('management/editions/edit', [
            'title' => __('catalog.new_edition'),
            'user' => Auth::user(),
            'course' => $course,
            'edition' => null,
            'requirements' => [],
        ]);
    }

    public function edit(Request $request): never
    {
        $edition = CourseEdition::find((int) $request->route('id'));
        if ($edition === null) {
            Flash::error(__('catalog.edition_not_found'));
            $this->redirect('/gestion/cursos');
        }
        $course = Course::find((int) $edition['course_id']);
        $this->view('management/editions/edit', [
            'title' => __('catalog.edit_edition'),
            'user' => Auth::user(),
            'course' => $course,
            'edition' => $edition,
            'requirements' => DocumentRequirement::forEdition((int) $edition['id'], (int) $edition['course_id']),
        ]);
    }

    public function store(Request $request): never
    {
        $courseId = (int) $request->route('course');
        $data = $this->collect($request) + ['course_id' => $courseId];
        $id = CourseEdition::store($data);
        Audit::log('edition.create', Auth::id(), 'edition', $id, ['course' => $courseId], $request->ip());
        Flash::success(__('catalog.edition_saved'));
        $this->redirect('/gestion/ediciones/' . $id . '/editar');
    }

    public function update(Request $request): never
    {
        $id = (int) $request->route('id');
        CourseEdition::update($id, $this->collect($request));
        Audit::log('edition.update', Auth::id(), 'edition', $id, [], $request->ip());
        Flash::success(__('catalog.edition_saved'));
        $this->redirect('/gestion/ediciones/' . $id . '/editar');
    }

    /** Añade un requisito documental a la edición. */
    public function addRequirement(Request $request): never
    {
        $editionId = (int) $request->route('id');
        $edition = CourseEdition::find($editionId);
        if ($edition === null) {
            $this->redirect('/gestion/cursos');
        }
        $name = [];
        foreach (Config::locales() as $loc) {
            $name[$loc] = trim((string) ($request->post['req_name'][$loc] ?? ''));
        }
        DocumentRequirement::store([
            'course_id' => null,
            'edition_id' => $editionId,
            'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
            'description' => '{}',
            'is_required' => $request->input('req_required') ? 1 : 0,
            'has_expiry' => $request->input('req_expiry') ? 1 : 0,
            'sort_order' => (int) $request->input('req_order', 0),
        ]);
        Flash::success(__('catalog.requirement_added'));
        $this->redirect('/gestion/ediciones/' . $editionId . '/editar');
    }

    public function deleteRequirement(Request $request): never
    {
        $reqId = (int) $request->route('reqId');
        $editionId = (int) $request->route('id');
        DocumentRequirement::delete($reqId);
        Flash::info(__('catalog.requirement_deleted'));
        $this->redirect('/gestion/ediciones/' . $editionId . '/editar');
    }

    private function collect(Request $request): array
    {
        $methods = [];
        foreach (['stripe', 'bizum', 'transfer'] as $m) {
            if ($request->input('pay_' . $m)) {
                $methods[] = $m;
            }
        }
        return [
            'name' => trim((string) $request->input('name', '')),
            'start_date' => $request->str('start_date') ?: null,
            'end_date' => $request->str('end_date') ?: null,
            'schedule' => $request->str('schedule'),
            'modality' => in_array($request->input('modality'), CourseEdition::MODALITIES, true) ? $request->input('modality') : 'presencial',
            'location' => $request->str('location'),
            'price' => $request->str('price') !== '' ? (float) $request->str('price') : null,
            'capacity' => (int) $request->input('capacity', 0),
            'waitlist_enabled' => $request->input('waitlist_enabled') ? 1 : 0,
            'payment_methods' => json_encode($methods),
            'preinscription_open_at' => $request->str('open_at') ?: null,
            'preinscription_close_at' => $request->str('close_at') ?: null,
            'status' => in_array($request->input('status'), CourseEdition::STATUS, true) ? $request->input('status') : 'draft',
        ];
    }
}
