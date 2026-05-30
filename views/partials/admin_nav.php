<?php
/** Subnavegación de la configuración de plataforma (solo admin). */
$active = $active ?? '';
$items = [
    'system' => ['/gestion/sistema', __('system.title')],
    'settings' => ['/gestion/sistema/ajustes', __('settings.general_title')],
    'integrations' => ['/gestion/sistema/integraciones', __('settings.integrations_title')],
    'billing' => ['/gestion/sistema/facturacion', __('billing.settings_title')],
    'fields' => ['/gestion/sistema/campos', __('fields.title')],
    'legal' => ['/gestion/sistema/legales', __('legal.title')],
    'templates' => ['/gestion/sistema/plantillas', __('templates.title')],
];
?>
<nav class="admin-nav">
    <?php foreach ($items as $key => [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $key === $active ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
