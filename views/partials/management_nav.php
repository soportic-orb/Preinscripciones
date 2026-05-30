<?php
/** Subnavegación del panel de gestión del proceso (staff). */
use App\Core\Auth;
use App\Core\Rbac;

$active = $active ?? '';
$items = [
    'management' => ['/gestion', __('nav.management')],
    'preinscriptions' => ['/gestion/preinscripciones', __('management.preinscriptions')],
    'courses' => ['/gestion/cursos', __('catalog.courses')],
];
$u = Auth::user();
if ($u !== null && Rbac::isAdmin($u)) {
    $items['system'] = ['/gestion/sistema', __('system.title')];
}
?>
<nav class="admin-nav">
    <?php foreach ($items as $key => [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $key === $active ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
