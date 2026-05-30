<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\FieldDefinition;

/**
 * Motor de campos dinámicos: renderiza definiciones a HTML, valida los valores
 * enviados y los persiste/recupera por entidad (preinscripción, ficha, etc.).
 *
 * El nombre del input es field[<field_key>] para agruparlos en el POST.
 */
final class FieldService
{
    /**
     * Renderiza un campo a HTML. $value es el valor actual (repintado).
     */
    public function renderField(FieldDefinition $f, string $value = ''): string
    {
        $name = 'field[' . $f->field_key . ']';
        $id = 'f_' . $f->field_key;
        $req = $f->is_required ? ' required' : '';
        $label = e($f->label()) . ($f->is_required ? ' <span style="color:var(--color-accent)">*</span>' : '');
        $help = $f->help() !== '' ? '<div class="field-hint">' . e($f->help()) . '</div>' : '';
        $ph = $f->placeholder() !== '' ? ' placeholder="' . e($f->placeholder()) . '"' : '';

        $control = match ($f->type) {
            'textarea' => sprintf('<textarea id="%s" name="%s"%s%s rows="4">%s</textarea>', $id, e($name), $req, $ph, e($value)),
            'select' => $this->renderSelect($f, $name, $id, $value, $req),
            'radio' => $this->renderChoices($f, $name, $value, 'radio'),
            'checkbox' => $this->renderCheckbox($f, $name, $id, $value),
            'number' => sprintf('<input type="number" id="%s" name="%s" value="%s"%s%s>', $id, e($name), e($value), $req, $ph),
            'date' => sprintf('<input type="date" id="%s" name="%s" value="%s"%s>', $id, e($name), e($value), $req),
            'email' => sprintf('<input type="email" id="%s" name="%s" value="%s"%s%s>', $id, e($name), e($value), $req, $ph),
            'tel' => sprintf('<input type="tel" id="%s" name="%s" value="%s"%s%s>', $id, e($name), e($value), $req, $ph),
            default => sprintf('<input type="text" id="%s" name="%s" value="%s"%s%s>', $id, e($name), e($value), $req, $ph),
        };

        // El checkbox lleva su propia etiqueta inline.
        if ($f->type === 'checkbox') {
            return '<div class="form-group">' . $control . $help . '</div>';
        }
        return sprintf('<div class="form-group"><label for="%s">%s</label>%s%s</div>', $id, $label, $control, $help);
    }

    private function renderSelect(FieldDefinition $f, string $name, string $id, string $value, string $req): string
    {
        $opts = '<option value="">—</option>';
        foreach ($f->options as $opt) {
            $ov = (string) ($opt['value'] ?? '');
            $ol = $f->localized($opt['label'] ?? []) ?: $ov;
            $sel = $ov === $value ? ' selected' : '';
            $opts .= sprintf('<option value="%s"%s>%s</option>', e($ov), $sel, e($ol));
        }
        return sprintf('<select id="%s" name="%s"%s>%s</select>', $id, e($name), $req, $opts);
    }

    private function renderChoices(FieldDefinition $f, string $name, string $value, string $type): string
    {
        $html = '<div>';
        foreach ($f->options as $i => $opt) {
            $ov = (string) ($opt['value'] ?? '');
            $ol = $f->localized($opt['label'] ?? []) ?: $ov;
            $checked = $ov === $value ? ' checked' : '';
            $rid = 'f_' . $f->field_key . '_' . $i;
            $html .= sprintf(
                '<div class="checkbox-row"><input type="%s" id="%s" name="%s" value="%s"%s><label for="%s" style="font-weight:400">%s</label></div>',
                $type, $rid, e($name), e($ov), $checked, $rid, e($ol),
            );
        }
        return $html . '</div>';
    }

    private function renderCheckbox(FieldDefinition $f, string $name, string $id, string $value): string
    {
        $checked = in_array($value, ['1', 'on', 'true'], true) ? ' checked' : '';
        return sprintf(
            '<div class="checkbox-row"><input type="checkbox" id="%s" name="%s" value="1"%s><label for="%s" style="font-weight:400">%s</label></div>',
            $id, e($name), $checked, $id, e($f->label()),
        );
    }

    /**
     * Valida los valores enviados para un formulario.
     *
     * @param array<int,FieldDefinition> $fields
     * @param array<string,mixed> $input  el array $_POST['field'] (field_key => value)
     * @return array<string,string> errores por field_key (vacío si todo OK)
     */
    public function validate(array $fields, array $input): array
    {
        $errors = [];
        foreach ($fields as $f) {
            $value = isset($input[$f->field_key]) ? trim((string) $input[$f->field_key]) : '';

            if ($f->is_required && $value === '') {
                $errors[$f->field_key] = __('validation.required', ['field' => $f->label()]);
                continue;
            }
            if ($value === '') {
                continue;
            }

            if ($f->type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$f->field_key] = __('validation.email', ['field' => $f->label()]);
            }
            if ($f->type === 'number' && !is_numeric($value)) {
                $errors[$f->field_key] = __('validation.numeric', ['field' => $f->label()]);
            }
            $v = $f->validations;
            if (isset($v['min']) && mb_strlen($value) < (int) $v['min']) {
                $errors[$f->field_key] = __('validation.min', ['field' => $f->label(), 'min' => (int) $v['min']]);
            }
            if (isset($v['max']) && mb_strlen($value) > (int) $v['max']) {
                $errors[$f->field_key] = __('validation.max', ['field' => $f->label(), 'max' => (int) $v['max']]);
            }
            if (!empty($v['regex']) && @preg_match('/' . str_replace('/', '\/', $v['regex']) . '/u', $value) === 0) {
                $errors[$f->field_key] = __('validation.invalid', ['field' => $f->label()]);
            }
            if (in_array($f->type, ['select', 'radio'], true) && $f->options !== []) {
                $allowed = array_map(fn ($o) => (string) ($o['value'] ?? ''), $f->options);
                if (!in_array($value, $allowed, true)) {
                    $errors[$f->field_key] = __('validation.invalid', ['field' => $f->label()]);
                }
            }
        }
        return $errors;
    }

    /**
     * Persiste los valores de los campos para una entidad (upsert por campo).
     *
     * @param array<int,FieldDefinition> $fields
     * @param array<string,mixed> $input
     */
    public function save(array $fields, array $input, string $entityType, int $entityId): void
    {
        $db = Database::instance();
        foreach ($fields as $f) {
            $value = isset($input[$f->field_key]) ? (string) $input[$f->field_key] : '';
            $existing = $db->scalar(
                'SELECT id FROM {field_values} WHERE field_id = ? AND entity_type = ? AND entity_id = ?',
                [$f->id, $entityType, $entityId],
            );
            if ($existing) {
                $db->update('field_values', ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')], ['id' => (int) $existing]);
            } else {
                $db->insert('field_values', [
                    'field_id' => $f->id,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'value' => $value,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * Recupera los valores guardados (field_key => value) para una entidad.
     *
     * @return array<string,string>
     */
    public function values(string $entityType, int $entityId): array
    {
        $rows = Database::instance()->fetchAll(
            'SELECT d.field_key, v.value FROM {field_values} v
             JOIN {field_definitions} d ON d.id = v.field_id
             WHERE v.entity_type = ? AND v.entity_id = ?',
            [$entityType, $entityId],
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['field_key']] = (string) ($row['value'] ?? '');
        }
        return $out;
    }
}
