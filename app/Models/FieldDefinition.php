<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\I18n;

/**
 * Definición de un campo dinámico configurable desde el panel de gestión.
 *
 * Los textos (label, help, placeholder) y las opciones de select/radio se
 * almacenan como JSON multiidioma {es,ca,en,pt}. Las validaciones se guardan
 * como JSON ({min, max, regex}).
 */
final class FieldDefinition extends Model
{
    protected static string $table = 'field_definitions';

    public int $id = 0;
    public string $form_key = '';
    public string $section = 'general';
    public string $field_key = '';
    public string $type = 'text';
    /** @var array<string,string> */
    public array $label = [];
    /** @var array<string,string> */
    public array $help = [];
    /** @var array<string,string> */
    public array $placeholder = [];
    /** @var array<int,array{value:string,label:array<string,string>}> */
    public array $options = [];
    /** @var array<string,mixed> */
    public array $validations = [];
    public int $is_required = 0;
    public int $is_active = 1;
    public int $is_system = 0;
    public int $sort_order = 0;

    /** Tipos de campo soportados por el motor. */
    public const TYPES = ['text', 'textarea', 'email', 'tel', 'number', 'date', 'select', 'radio', 'checkbox'];

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $f = new self();
        $f->id = (int) $row['id'];
        $f->form_key = (string) $row['form_key'];
        $f->section = (string) $row['section'];
        $f->field_key = (string) $row['field_key'];
        $f->type = (string) $row['type'];
        $f->label = self::decode($row['label'] ?? null);
        $f->help = self::decode($row['help'] ?? null);
        $f->placeholder = self::decode($row['placeholder'] ?? null);
        $f->options = is_string($row['options'] ?? null) ? (json_decode((string) $row['options'], true) ?: []) : [];
        $f->validations = is_string($row['validations'] ?? null) ? (json_decode((string) $row['validations'], true) ?: []) : [];
        $f->is_required = (int) $row['is_required'];
        $f->is_active = (int) $row['is_active'];
        $f->is_system = (int) $row['is_system'];
        $f->sort_order = (int) $row['sort_order'];
        return $f;
    }

    /** @return array<string,string> */
    private static function decode(?string $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $d = json_decode($json, true);
        return is_array($d) ? $d : [];
    }

    /** Texto localizado con fallback al español y luego a la primera traducción. */
    public function label(): string
    {
        return $this->localized($this->label) ?: $this->field_key;
    }

    public function help(): string
    {
        return $this->localized($this->help);
    }

    public function placeholder(): string
    {
        return $this->localized($this->placeholder);
    }

    /** @param array<string,string> $bag */
    public function localized(array $bag): string
    {
        $loc = I18n::locale();
        if (!empty($bag[$loc])) {
            return $bag[$loc];
        }
        if (!empty($bag['es'])) {
            return $bag['es'];
        }
        foreach ($bag as $v) {
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    /** @return array<int,self> campos activos de un formulario, ordenados */
    public static function forForm(string $formKey, bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM {field_definitions} WHERE form_key = ?';
        if ($onlyActive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY section, sort_order, id';
        $rows = Database::instance()->fetchAll($sql, [$formKey]);
        return array_map(fn ($r) => self::fromRow($r), $rows);
    }

    public static function find(int $id): ?self
    {
        $row = self::findRow($id);
        return $row ? self::fromRow($row) : null;
    }

    /** @param array<string,mixed> $data */
    public static function createFromInput(array $data): self
    {
        $id = Database::instance()->insert('field_definitions', self::toColumns($data) + [
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return self::find($id) ?? throw new \RuntimeException('No se pudo crear el campo.');
    }

    /** @param array<string,mixed> $data */
    public static function updateFromInput(int $id, array $data): void
    {
        Database::instance()->update('field_definitions', self::toColumns($data) + [
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    /** Normaliza la entrada del formulario de administración a columnas. */
    private static function toColumns(array $data): array
    {
        return [
            'form_key' => (string) ($data['form_key'] ?? 'preinscription'),
            'section' => (string) ($data['section'] ?? 'general'),
            'field_key' => (string) ($data['field_key'] ?? ''),
            'type' => in_array($data['type'] ?? 'text', self::TYPES, true) ? $data['type'] : 'text',
            'label' => json_encode($data['label'] ?? [], JSON_UNESCAPED_UNICODE),
            'help' => json_encode($data['help'] ?? [], JSON_UNESCAPED_UNICODE),
            'placeholder' => json_encode($data['placeholder'] ?? [], JSON_UNESCAPED_UNICODE),
            'options' => json_encode($data['options'] ?? [], JSON_UNESCAPED_UNICODE),
            'validations' => json_encode($data['validations'] ?? [], JSON_UNESCAPED_UNICODE),
            'is_required' => !empty($data['is_required']) ? 1 : 0,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    public static function delete(int $id): void
    {
        $db = Database::instance();
        $db->run('DELETE FROM {field_values} WHERE field_id = ?', [$id]);
        $db->run('DELETE FROM {field_definitions} WHERE id = ? AND is_system = 0', [$id]);
    }
}
