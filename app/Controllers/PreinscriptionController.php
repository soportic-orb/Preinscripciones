<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\DocumentRequirement;
use App\Models\FieldDefinition;
use App\Models\Preinscription;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\DocumentService;
use App\Services\FieldService;
use App\Services\Notifier;
use App\Services\PreinscriptionService;
use App\Services\PreinscriptionStatus;

/**
 * Asistente de preinscripción multipaso. Requiere sesión: el alta de cuenta se
 * realiza en el registro (con verificación de email); aquí el estudiante elige
 * edición y completa datos, tutor (si es menor) y documentos, con guardado y
 * reanudación mediante un borrador persistido.
 */
final class PreinscriptionController extends Controller
{
    private const STEPS = 5; // 1 datos, 2 académicos, 3 tutor, 4 documentos, 5 revisión

    /** Catálogo público de ediciones abiertas. */
    public function catalog(Request $request): never
    {
        $this->view('preinscription/catalog', [
            'title' => __('catalog.title'),
            'editions' => CourseEdition::openForPreinscription(),
        ]);
    }

    /** Inicia (o reanuda) una preinscripción a una edición. */
    public function start(Request $request): never
    {
        $editionId = (int) $request->input('edition_id');
        $edition = CourseEdition::findWithCourse($editionId);
        if ($edition === null || !CourseEdition::isOpenNow($edition)) {
            Flash::error(__('catalog.edition_closed'));
            $this->redirect('/preinscripcion');
        }
        $userId = (int) Auth::id();

        // Prerrequisito entre cursos.
        if (!empty($edition['prerequisite_course_id']) && !$this->hasCompletedCourse($userId, (int) $edition['prerequisite_course_id'])) {
            Flash::error(__('catalog.prerequisite_missing'));
            $this->redirect('/preinscripcion');
        }

        if (Preinscription::existsActive($userId, $editionId)) {
            Flash::warning(__('catalog.already_preinscribed'));
            $this->redirect('/panel');
        }

        $draft = Preinscription::draftFor($userId, $editionId);
        if ($draft === null) {
            $id = Preinscription::store([
                'user_id' => $userId,
                'edition_id' => $editionId,
                'status' => PreinscriptionStatus::BORRADOR,
                'wizard_step' => 1,
            ]);
            Audit::log('preinscription.start', $userId, 'preinscription', $id, ['edition' => $editionId], $request->ip());
        } else {
            $id = (int) $draft['id'];
        }
        $this->redirect('/preinscripcion/' . $id . '/paso/' . (int) ($draft['wizard_step'] ?? 1));
    }

    /** Muestra un paso del asistente. */
    public function step(Request $request): never
    {
        [$pre, $edition] = $this->loadOwnedDraft($request);
        $step = max(1, min(self::STEPS, (int) $request->route('step')));

        // Saltar el paso de tutor si no es menor.
        if ($step === 3 && (int) $pre['is_minor'] !== 1) {
            $this->redirect('/preinscripcion/' . $pre['id'] . '/paso/4');
        }

        $fieldSvc = new FieldService();
        $docSvc = new DocumentService();

        $this->view('preinscription/wizard', [
            'title' => __('preinscription.title'),
            'pre' => $pre,
            'edition' => $edition,
            'step' => $step,
            'steps' => self::STEPS,
            'studentFields' => FieldDefinition::forForm('preinscription'),
            'academicFields' => FieldDefinition::forForm('academic'),
            'values' => $fieldSvc->values('preinscription', (int) $pre['id']),
            'service' => $fieldSvc,
            'requirements' => DocumentRequirement::forEdition((int) $pre['edition_id'], (int) $edition['course_id']),
            'documents' => $docSvc->forPreinscription((int) $pre['id']),
            'guardian' => Database::instance()->fetch('SELECT * FROM {guardians} WHERE preinscription_id = ? LIMIT 1', [$pre['id']]),
        ]);
    }

    /** Procesa un paso (guardar y avanzar / guardar y salir). */
    public function save(Request $request): never
    {
        [$pre, $edition] = $this->loadOwnedDraft($request);
        $step = (int) $request->route('step');
        $exit = (bool) $request->input('save_exit');
        $fieldSvc = new FieldService();

        switch ($step) {
            case 1: // Datos del estudiante (campos dinámicos) + detección menor
                $fields = FieldDefinition::forForm('preinscription');
                $input = (array) ($request->post['field'] ?? []);
                $errors = $fieldSvc->validate($fields, $input);
                if ($errors !== []) {
                    $this->flashErrors($errors);
                    $this->redirect('/preinscripcion/' . $pre['id'] . '/paso/1');
                }
                $fieldSvc->save($fields, $input, 'preinscription', (int) $pre['id']);
                $isMinor = $this->isMinorFrom($input);
                Preinscription::update((int) $pre['id'], ['is_minor' => $isMinor ? 1 : 0, 'wizard_step' => 2]);
                break;

            case 2: // Datos académicos (campos dinámicos)
                $fields = FieldDefinition::forForm('academic');
                $input = (array) ($request->post['field'] ?? []);
                $errors = $fieldSvc->validate($fields, $input);
                if ($errors !== []) {
                    $this->flashErrors($errors);
                    $this->redirect('/preinscripcion/' . $pre['id'] . '/paso/2');
                }
                $fieldSvc->save($fields, $input, 'academic', (int) $pre['id']);
                Preinscription::update((int) $pre['id'], ['wizard_step' => (int) $pre['is_minor'] === 1 ? 3 : 4]);
                break;

            case 3: // Tutor legal (solo menores) + doble consentimiento
                if ((int) $pre['is_minor'] === 1) {
                    if (!$request->input('consent_1') || !$request->input('consent_2')) {
                        Flash::error(__('preinscription.guardian_consents_required'));
                        $this->redirect('/preinscripcion/' . $pre['id'] . '/paso/3');
                    }
                    $this->saveGuardian($request, (int) $pre['id']);
                }
                Preinscription::update((int) $pre['id'], ['wizard_step' => 4]);
                break;

            case 4: // Documentos: la subida se hace por AJAX/submit aparte; aquí solo avanza
                Preinscription::update((int) $pre['id'], ['wizard_step' => 5]);
                break;

            case 5: // Revisión y envío
                $this->submit($request, $pre, $edition);
        }

        if ($exit) {
            Flash::success(__('preinscription.saved_draft'));
            $this->redirect('/panel');
        }
        $next = min(self::STEPS, $step + 1);
        if ($next === 3 && (int) $pre['is_minor'] !== 1) {
            $next = 4;
        }
        $this->redirect('/preinscripcion/' . $pre['id'] . '/paso/' . $next);
    }

    /** Subida de un documento requerido (paso 4). */
    public function uploadDocument(Request $request): never
    {
        [$pre, $edition] = $this->loadOwnedDraft($request, true);
        $requirementId = (int) $request->input('requirement_id') ?: null;
        $reqRow = $requirementId ? Database::instance()->fetch('SELECT * FROM {document_requirements} WHERE id = ?', [$requirementId]) : null;
        $hasExpiry = $reqRow && (int) $reqRow['has_expiry'] === 1;

        $result = (new DocumentService())->upload(
            $request->files['document'] ?? [],
            (int) $pre['id'],
            $requirementId,
            $hasExpiry,
            $hasExpiry ? $request->str('expires_at') : null,
        );
        if ($result['ok']) {
            Flash::success(__('documents.uploaded'));
        } else {
            Flash::error($result['error'] ?? __('documents.upload_error'));
        }
        $this->redirect('/preinscripcion/' . $pre['id'] . '/paso/4');
    }

    /** Confirmación final: pasa de borrador a preinscrito. */
    private function submit(Request $request, array $pre, array $edition): never
    {
        // Comprobar documentos obligatorios subidos (no necesariamente validados aún).
        $reqs = DocumentRequirement::forEdition((int) $pre['edition_id'], (int) $edition['course_id']);
        $docs = (new DocumentService())->forPreinscription((int) $pre['id']);
        $uploadedReqs = array_filter(array_map(fn ($d) => (int) ($d['requirement_id'] ?? 0), $docs));
        foreach ($reqs as $r) {
            if ((int) $r['is_required'] === 1 && !in_array((int) $r['id'], $uploadedReqs, true)) {
                Flash::error(__('preinscription.missing_documents'));
                $this->redirect('/preinscripcion/' . $pre['id'] . '/paso/4');
            }
        }
        if (!$request->input('final_consent')) {
            Flash::error(__('preinscription.final_consent_required'));
            $this->redirect('/preinscripcion/' . $pre['id'] . '/paso/5');
        }

        $userId = (int) $pre['user_id'];
        Preinscription::update((int) $pre['id'], [
            'status' => PreinscriptionStatus::PREINSCRITO,
            'submitted_at' => date('Y-m-d H:i:s'),
            'wizard_step' => self::STEPS,
        ]);
        Database::instance()->insert('preinscription_status_history', [
            'preinscription_id' => (int) $pre['id'],
            'from_status' => PreinscriptionStatus::BORRADOR,
            'to_status' => PreinscriptionStatus::PREINSCRITO,
            'actor_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        (new ConsentService())->record($userId, 'terms', $request->ip());
        Audit::log('preinscription.submit', $userId, 'preinscription', (int) $pre['id'], [], $request->ip());

        // Notificar al estudiante y a los gestores.
        $user = Auth::user();
        $course = Course::localized($edition['course_title'] ?? '');
        if ($user !== null) {
            Notifier::toUser($user, 'preinscription_created', ['name' => $user->name, 'course' => $course, 'edition' => (string) $edition['name']]);
        }
        $this->notifyStaff('preinscription_created_staff', $course, (string) $edition['name']);

        Flash::success(__('preinscription.submitted'));
        $this->redirect('/panel');
    }

    // ------------------------------------------------------------- helpers
    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function loadOwnedDraft(Request $request, bool $allowAnyEditable = false): array
    {
        $pre = Preinscription::find((int) $request->route('id'));
        if ($pre === null || (int) $pre['user_id'] !== (int) Auth::id()) {
            Flash::error(__('preinscription.not_found'));
            $this->redirect('/panel');
        }
        if ($pre['status'] !== PreinscriptionStatus::BORRADOR && !$allowAnyEditable) {
            Flash::info(__('preinscription.already_submitted'));
            $this->redirect('/panel');
        }
        $edition = CourseEdition::findWithCourse((int) $pre['edition_id']);
        if ($edition === null) {
            Flash::error(__('catalog.edition_closed'));
            $this->redirect('/panel');
        }
        return [$pre, $edition];
    }

    private function isMinorFrom(array $input): bool
    {
        $dob = $input['fecha_nacimiento'] ?? null;
        if (!is_string($dob) || $dob === '') {
            return false;
        }
        $ts = strtotime($dob);
        if ($ts === false) {
            return false;
        }
        $age = (int) ((time() - $ts) / (365.25 * 86400));
        return $age < 18;
    }

    private function saveGuardian(Request $request, int $preId): void
    {
        $db = Database::instance();
        $db->run('DELETE FROM {guardians} WHERE preinscription_id = ?', [$preId]);
        $db->insert('guardians', [
            'preinscription_id' => $preId,
            'name' => $request->str('guardian_name'),
            'dni' => $request->str('guardian_dni'),
            'email' => $request->str('guardian_email'),
            'phone' => $request->str('guardian_phone'),
            'relationship' => $request->str('guardian_relationship'),
            'consent_1' => $request->input('consent_1') ? 1 : 0,
            'consent_2' => $request->input('consent_2') ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function hasCompletedCourse(int $userId, int $courseId): bool
    {
        return (bool) Database::instance()->scalar(
            "SELECT 1 FROM {preinscriptions} p JOIN {course_editions} e ON e.id = p.edition_id
             WHERE p.user_id = ? AND e.course_id = ? AND p.status = 'matriculado' LIMIT 1",
            [$userId, $courseId],
        );
    }

    private function notifyStaff(string $event, string $course, string $edition): void
    {
        $staff = Database::instance()->fetchAll(
            "SELECT * FROM {users} WHERE role IN ('owner','admin','gestor') AND is_active = 1",
        );
        foreach ($staff as $row) {
            Notifier::toEmail((string) $row['email'], (string) $row['name'], $event, [
                'course' => $course, 'edition' => $edition,
            ], (string) $row['locale']);
        }
    }
}
