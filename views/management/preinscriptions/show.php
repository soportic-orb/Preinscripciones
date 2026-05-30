<?php
/** Detalle de preinscripción para gestión: datos, documentos y acciones. */
use App\Models\Course;
use App\Models\DocumentRequirement;
use App\Models\FieldDefinition;
use App\Services\PreinscriptionStatus;

$active = 'preinscriptions';
$byReq = [];
foreach ($documents as $d) {
    $byReq[(int) ($d['requirement_id'] ?? 0)] = $d;
}
$studentFields = FieldDefinition::forForm('preinscription');
$academicFields = FieldDefinition::forForm('academic');
?>
<?php require VIEW_PATH . '/partials/management_nav.php'; ?>

<p><a href="<?= e(url('/gestion/preinscripciones')) ?>">&larr; <?= e(__('management.preinscriptions')) ?></a></p>

<div class="toolbar">
    <div>
        <h1><?= e((string) $pre['student_name']) ?></h1>
        <p class="text-muted" style="margin:0"><?= e((string) $pre['student_email']) ?></p>
    </div>
    <span class="badge"><?= e(PreinscriptionStatus::label((string) $pre['status'])) ?></span>
</div>

<div class="grid-2">
    <div class="card">
        <h3><?= e(__('catalog.course')) ?></h3>
        <table class="info-table">
            <tr><th><?= e(__('catalog.course')) ?></th><td><?= e(Course::localized($pre['course_title'] ?? '')) ?></td></tr>
            <tr><th><?= e(__('catalog.edition')) ?></th><td><?= e((string) $pre['edition_name']) ?></td></tr>
            <tr><th><?= e(__('preinscription.is_minor')) ?></th><td><?= (int) $pre['is_minor'] === 1 ? e(__('common.yes')) : e(__('common.no')) ?></td></tr>
        </table>
    </div>
    <div class="card">
        <h3><?= e(__('preinscription.step_student')) ?></h3>
        <table class="info-table">
            <?php foreach ($studentFields as $f): ?>
                <tr><th><?= e($f->label()) ?></th><td><?= e($values[$f->field_key] ?? '—') ?></td></tr>
            <?php endforeach; ?>
            <?php foreach ($academicFields as $f): ?>
                <tr><th><?= e($f->label()) ?></th><td><?= e($academic[$f->field_key] ?? '—') ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<?php if ($guardian !== null): ?>
<div class="card">
    <h3><?= e(__('preinscription.step_guardian')) ?></h3>
    <table class="info-table">
        <tr><th><?= e(__('preinscription.guardian_name')) ?></th><td><?= e((string) $guardian['name']) ?></td></tr>
        <tr><th><?= e(__('preinscription.guardian_email')) ?></th><td><?= e((string) ($guardian['email'] ?? '')) ?></td></tr>
        <tr><th><?= e(__('preinscription.guardian_phone')) ?></th><td><?= e((string) ($guardian['phone'] ?? '')) ?></td></tr>
        <tr><th><?= e(__('preinscription.guardian_consents')) ?></th><td><?= ((int) $guardian['consent_1'] === 1 && (int) $guardian['consent_2'] === 1) ? '✓✓' : '—' ?></td></tr>
    </table>
</div>
<?php endif; ?>

<!-- Documentación -->
<div class="card">
    <h3><?= e(__('preinscription.step_documents')) ?>
        <?php if ($allValidated): ?><span class="badge" style="background:var(--color-success-bg);color:var(--color-success)"><?= e(__('management.docs_ok')) ?></span><?php endif; ?>
    </h3>
    <?php if ($requirements === []): ?>
        <p class="text-muted"><?= e(__('preinscription.no_documents')) ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th><?= e(__('documents.document')) ?></th><th><?= e(__('documents.status')) ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($requirements as $req): $doc = $byReq[(int) $req['id']] ?? null; ?>
                    <tr>
                        <td><?= e(DocumentRequirement::localized($req['name'])) ?><?php if ((int) $req['is_required'] === 1): ?> <span style="color:var(--color-accent)">*</span><?php endif; ?></td>
                        <td><?php if ($doc): ?><span class="badge"><?= e(__('documents.statuses.' . $doc['status'])) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                        <td>
                            <?php if ($doc): ?>
                                <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                                    <a class="btn btn-ghost btn-sm" href="<?= e(url('/documento/' . $doc['id'])) ?>"><?= e(__('documents.download')) ?></a>
                                    <form method="post" action="<?= e(url('/gestion/documentos/' . $doc['id'] . '/validar')) ?>" style="display:inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="decision" value="approve">
                                        <button class="btn btn-outline btn-sm"><?= e(__('documents.validate')) ?></button>
                                    </form>
                                    <form method="post" action="<?= e(url('/gestion/documentos/' . $doc['id'] . '/validar')) ?>" style="display:flex;gap:4px">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="decision" value="reject">
                                        <input type="text" name="reason" placeholder="<?= e(__('documents.reason')) ?>" style="width:140px">
                                        <button class="btn btn-ghost btn-sm" style="color:var(--color-accent)"><?= e(__('documents.reject')) ?></button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Acciones de estado -->
<div class="card">
    <h3><?= e(__('management.actions')) ?></h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start">
        <?php if (PreinscriptionStatus::canTransition((string) $pre['status'], PreinscriptionStatus::ACEPTADO)
                  || PreinscriptionStatus::canTransition((string) $pre['status'], PreinscriptionStatus::EN_LISTA_ESPERA)): ?>
            <form method="post" action="<?= e(url('/gestion/preinscripciones/' . $pre['id'] . '/aceptar')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-primary"><?= e(__('management.accept')) ?></button>
            </form>
        <?php endif; ?>
        <?php if (PreinscriptionStatus::canTransition((string) $pre['status'], PreinscriptionStatus::RECHAZADO)): ?>
            <form method="post" action="<?= e(url('/gestion/preinscripciones/' . $pre['id'] . '/rechazar')) ?>" style="display:flex;gap:6px">
                <?= csrf_field() ?>
                <input type="text" name="reason" placeholder="<?= e(__('documents.reason')) ?>">
                <button class="btn btn-ghost" style="color:var(--color-accent)"><?= e(__('management.reject')) ?></button>
            </form>
        <?php endif; ?>
        <?php if (PreinscriptionStatus::canTransition((string) $pre['status'], PreinscriptionStatus::DOC_EN_REVISION)): ?>
            <form method="post" action="<?= e(url('/gestion/preinscripciones/' . $pre['id'] . '/transicion')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="to" value="<?= e(PreinscriptionStatus::DOC_EN_REVISION) ?>">
                <button class="btn btn-ghost"><?= e(__('states.documentacion_en_revision')) ?></button>
            </form>
        <?php endif; ?>
        <?php if (PreinscriptionStatus::canTransition((string) $pre['status'], PreinscriptionStatus::PENDIENTE_PAGO)): ?>
            <form method="post" action="<?= e(url('/gestion/preinscripciones/' . $pre['id'] . '/transicion')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="to" value="<?= e(PreinscriptionStatus::PENDIENTE_PAGO) ?>">
                <button class="btn btn-outline"><?= e(__('management.to_payment')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Historial -->
<div class="card">
    <h3><?= e(__('management.history')) ?></h3>
    <table class="data-table">
        <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e((string) $h['created_at']) ?></td>
                    <td><?= e($h['from_status'] ? PreinscriptionStatus::label((string) $h['from_status']) : '—') ?> → <?= e(PreinscriptionStatus::label((string) $h['to_status'])) ?></td>
                    <td><?= e((string) ($h['note'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
