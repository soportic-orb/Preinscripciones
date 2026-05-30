<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Flash;
use App\Core\Request;
use App\Models\FieldDefinition;
use App\Services\FieldService;

/**
 * CRUD de definiciones de campos dinámicos (solo admin).
 */
final class FieldsController extends Controller
{
    private const FORMS = ['preinscription', 'profile', 'academic'];

    public function index(Request $request): never
    {
        $formKey = $request->str('form', 'preinscription');
        if (!in_array($formKey, self::FORMS, true)) {
            $formKey = 'preinscription';
        }
        $fields = FieldDefinition::forForm($formKey, false);
        $this->view('system/fields/index', [
            'title' => __('fields.title'),
            'user' => Auth::user(),
            'forms' => self::FORMS,
            'formKey' => $formKey,
            'fields' => $fields,
            'service' => new FieldService(),
        ]);
    }

    public function create(Request $request): never
    {
        $this->view('system/fields/edit', [
            'title' => __('fields.new'),
            'user' => Auth::user(),
            'forms' => self::FORMS,
            'field' => null,
            'locales' => Config::locales(),
        ]);
    }

    public function edit(Request $request): never
    {
        $field = FieldDefinition::find((int) $request->route('id'));
        if ($field === null) {
            Flash::error(__('fields.not_found'));
            $this->redirect('/gestion/sistema/campos');
        }
        $this->view('system/fields/edit', [
            'title' => __('fields.edit'),
            'user' => Auth::user(),
            'forms' => self::FORMS,
            'field' => $field,
            'locales' => Config::locales(),
        ]);
    }

    public function store(Request $request): never
    {
        $data = $this->collect($request);
        if ($data['field_key'] === '') {
            Flash::error(__('fields.key_required'));
            $this->redirect('/gestion/sistema/campos/nuevo');
        }
        $field = FieldDefinition::createFromInput($data);
        Audit::log('field.create', Auth::id(), 'field', $field->id, ['key' => $field->field_key], $request->ip());
        Flash::success(__('fields.saved'));
        $this->redirect('/gestion/sistema/campos?form=' . urlencode($data['form_key']));
    }

    public function update(Request $request): never
    {
        $id = (int) $request->route('id');
        $data = $this->collect($request);
        FieldDefinition::updateFromInput($id, $data);
        Audit::log('field.update', Auth::id(), 'field', $id, ['key' => $data['field_key']], $request->ip());
        Flash::success(__('fields.saved'));
        $this->redirect('/gestion/sistema/campos?form=' . urlencode($data['form_key']));
    }

    public function destroy(Request $request): never
    {
        $id = (int) $request->route('id');
        FieldDefinition::delete($id);
        Audit::log('field.delete', Auth::id(), 'field', $id, [], $request->ip());
        Flash::info(__('fields.deleted'));
        $this->redirect('/gestion/sistema/campos');
    }

    /** Normaliza la entrada del formulario (textos por idioma + opciones por líneas). */
    private function collect(Request $request): array
    {
        $locales = Config::locales();
        $label = $help = $placeholder = [];
        foreach ($locales as $loc) {
            $label[$loc] = trim((string) ($request->post['label'][$loc] ?? ''));
            $help[$loc] = trim((string) ($request->post['help'][$loc] ?? ''));
            $placeholder[$loc] = trim((string) ($request->post['placeholder'][$loc] ?? ''));
        }

        // Opciones: una por línea, formato "valor|etiqueta". La etiqueta se
        // replica a los 4 idiomas (refinables posteriormente).
        $options = [];
        foreach (preg_split('/\r?\n/', (string) $request->input('options_raw', '')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$val, $lab] = array_pad(explode('|', $line, 2), 2, null);
            $val = trim((string) $val);
            $lab = trim((string) ($lab ?? $val));
            $labels = [];
            foreach ($locales as $loc) {
                $labels[$loc] = $lab;
            }
            $options[] = ['value' => $val, 'label' => $labels];
        }

        $validations = [];
        foreach (['min', 'max', 'regex'] as $vk) {
            $vv = trim((string) $request->input('valid_' . $vk, ''));
            if ($vv !== '') {
                $validations[$vk] = $vv;
            }
        }

        return [
            'form_key' => (string) $request->input('form_key', 'preinscription'),
            'section' => (string) $request->input('section', 'general'),
            'field_key' => preg_replace('/[^a-z0-9_]/', '', strtolower((string) $request->input('field_key', ''))) ?? '',
            'type' => (string) $request->input('type', 'text'),
            'label' => $label,
            'help' => $help,
            'placeholder' => $placeholder,
            'options' => $options,
            'validations' => $validations,
            'is_required' => $request->input('is_required') ? 1 : 0,
            'is_active' => $request->input('is_active') ? 1 : 0,
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
