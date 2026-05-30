<?php
/** Dashboard de informes y KPIs. */
use App\Models\Course;
use App\Services\PreinscriptionStatus;

$active = 'reports';
$k = $kpis;
$money = static fn ($n) => number_format((float) $n, 2, ',', '.') . ' €';
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<h1><?= e(__('reports.title')) ?></h1>

<div class="features">
    <div class="card"><div class="text-muted"><?= e(__('reports.total_preinscriptions')) ?></div><div style="font-size:2rem;font-weight:700;color:var(--color-accent)"><?= (int) $k['total_preinscriptions'] ?></div></div>
    <div class="card"><div class="text-muted"><?= e(__('reports.enrolled')) ?></div><div style="font-size:2rem;font-weight:700;color:var(--color-accent)"><?= (int) $k['enrolled'] ?></div></div>
    <div class="card"><div class="text-muted"><?= e(__('reports.conversion')) ?></div><div style="font-size:2rem;font-weight:700;color:var(--color-accent)"><?= e((string) $k['conversion_rate']) ?> %</div></div>
    <div class="card"><div class="text-muted"><?= e(__('reports.income')) ?></div><div style="font-size:2rem;font-weight:700;color:var(--color-accent)"><?= $money($k['income']) ?></div></div>
    <div class="card"><div class="text-muted"><?= e(__('reports.pending_payments')) ?></div><div style="font-size:1.6rem;font-weight:700"><?= $money($k['pending_payments']) ?></div></div>
    <div class="card"><div class="text-muted"><?= e(__('reports.pending_docs')) ?></div><div style="font-size:1.6rem;font-weight:700"><?= (int) $k['pending_docs'] ?></div></div>
    <div class="card"><div class="text-muted"><?= e(__('states.en_lista_de_espera')) ?></div><div style="font-size:1.6rem;font-weight:700"><?= (int) $k['in_waitlist'] ?></div></div>
</div>

<div class="card">
    <h3><?= e(__('reports.by_status')) ?></h3>
    <table class="data-table">
        <tbody>
            <?php foreach ($k['by_status'] as $status => $n): ?>
                <tr><td><?= e(PreinscriptionStatus::label((string) $status)) ?></td><td style="text-align:right"><?= (int) $n ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3><?= e(__('reports.occupancy')) ?></h3>
    <table class="data-table">
        <thead><tr><th><?= e(__('catalog.course')) ?></th><th><?= e(__('catalog.edition')) ?></th><th><?= e(__('reports.occupied')) ?></th><th><?= e(__('catalog.waitlist')) ?></th></tr></thead>
        <tbody>
            <?php foreach ($occupancy as $o): ?>
                <tr>
                    <td><?= e(Course::localized($o['course_title'] ?? '')) ?></td>
                    <td><?= e((string) $o['name']) ?></td>
                    <td><?= (int) $o['occupied'] ?><?= (int) $o['capacity'] > 0 ? ' / ' . (int) $o['capacity'] : '' ?></td>
                    <td><?= (int) $o['waitlist'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3><?= e(__('reports.export')) ?></h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn-outline" href="<?= e(url('/gestion/informes/export?type=students')) ?>"><?= e(__('reports.export_students')) ?></a>
        <a class="btn btn-outline" href="<?= e(url('/gestion/informes/export?type=payments')) ?>"><?= e(__('reports.export_payments')) ?></a>
        <a class="btn btn-outline" href="<?= e(url('/gestion/informes/export?type=pending_docs')) ?>"><?= e(__('reports.export_docs')) ?></a>
    </div>
</div>
