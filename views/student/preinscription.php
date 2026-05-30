<?php
/** Detalle de una preinscripción del estudiante (estado + documentos). */
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\DocumentRequirement;
use App\Services\PreinscriptionStatus;

$price = CourseEdition::effectivePrice($edition);
$byReq = [];
foreach ($documents as $d) {
    $byReq[(int) ($d['requirement_id'] ?? 0)] = $d;
}
?>
<p><a href="<?= e(url('/panel')) ?>">&larr; <?= e(__('nav.dashboard')) ?></a></p>
<div class="toolbar">
    <h1><?= e(Course::localized($edition['course_title'] ?? '')) ?></h1>
    <span class="badge"><?= e(PreinscriptionStatus::label((string) $pre['status'])) ?></span>
</div>

<div class="card">
    <table class="info-table">
        <tr><th><?= e(__('catalog.edition')) ?></th><td><?= e((string) $edition['name']) ?></td></tr>
        <tr><th><?= e(__('catalog.modality')) ?></th><td><?= e(__('catalog.modalities.' . $edition['modality'])) ?></td></tr>
        <tr><th><?= e(__('catalog.price')) ?></th><td><?= $price !== null ? e(number_format($price, 2)) . ' €' : '—' ?></td></tr>
        <?php if (!empty($pre['reject_reason'])): ?>
            <tr><th><?= e(__('management.reject_reason')) ?></th><td><?= e((string) $pre['reject_reason']) ?></td></tr>
        <?php endif; ?>
    </table>
</div>

<?php if ($pre['status'] === 'matriculado'): ?>
    <?php $cert = \App\Models\Certificate::forPreinscription((int) $pre['id']); ?>
    <div class="card">
        <h3><?= e(__('certificates.title')) ?></h3>
        <?php if ($cert !== null): ?>
            <a class="btn btn-primary" href="<?= e(url('/certificado/' . $cert['id'])) ?>"><?= e(__('certificates.download')) ?></a>
        <?php else: ?>
            <p class="text-muted" style="display:inline-block;margin-right:8px"><?= e(__('certificates.pending')) ?></p>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('/edicion/' . $pre['edition_id'] . '/ical')) ?>"><?= e(__('certificates.add_to_calendar')) ?></a>
    </div>
<?php endif; ?>

<?php if (in_array($pre['status'], ['pendiente_pago', 'pago_en_revision', 'matriculado'], true)): ?>
<div class="card">
    <h3><?= e(__('payments.title')) ?></h3>
    <p class="text-muted"><?= e(__('payments.access_intro')) ?></p>
    <a class="btn btn-primary" href="<?= e(url('/panel/preinscripcion/' . $pre['id'] . '/pago')) ?>"><?= e(__('payments.go_to_payment')) ?></a>
    <a class="btn btn-ghost" href="<?= e(url('/panel/facturacion')) ?>"><?= e(__('billing.invoices')) ?></a>
</div>
<?php endif; ?>

<div class="card">
    <h3><?= e(__('preinscription.step_documents')) ?></h3>
    <?php if ($requirements === []): ?>
        <p class="text-muted"><?= e(__('preinscription.no_documents')) ?></p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th><?= e(__('documents.document')) ?></th><th><?= e(__('documents.status')) ?></th><th></th></tr></thead>
            <tbody>
                <?php foreach ($requirements as $req): $doc = $byReq[(int) $req['id']] ?? null; ?>
                    <tr>
                        <td><?= e(DocumentRequirement::localized($req['name'])) ?><?php if ((int) $req['is_required'] === 1): ?> <span style="color:var(--color-accent)">*</span><?php endif; ?></td>
                        <td>
                            <?php if ($doc): ?>
                                <span class="badge"><?= e(__('documents.statuses.' . $doc['status'])) ?></span>
                                <?php if ($doc['status'] === 'rechazado' && !empty($doc['reject_reason'])): ?>
                                    <div class="field-hint"><?= e((string) $doc['reject_reason']) ?></div>
                                <?php endif; ?>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($doc): ?>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('/documento/' . $doc['id'])) ?>"><?= e(__('documents.download')) ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (in_array($pre['status'], [PreinscriptionStatus::PREINSCRITO, PreinscriptionStatus::DOC_EN_REVISION], true)): ?>
            <hr style="border:none;border-top:1px solid var(--color-border);margin:16px 0">
            <p class="text-muted"><?= e(__('documents.reupload_hint')) ?></p>
            <?php foreach ($requirements as $req): ?>
                <form method="post" action="<?= e(url('/panel/preinscripcion/' . $pre['id'] . '/documento')) ?>" enctype="multipart/form-data" style="margin-bottom:8px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="requirement_id" value="<?= (int) $req['id'] ?>">
                    <div class="row" style="align-items:flex-end">
                        <div class="form-group" style="flex:1">
                            <label><?= e(DocumentRequirement::localized($req['name'])) ?></label>
                            <input type="file" name="document" required>
                        </div>
                        <div class="form-group"><button type="submit" class="btn btn-outline btn-sm"><?= e(__('documents.upload')) ?></button></div>
                    </div>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
