<?php
/** Asistente de preinscripción multipaso. */
/** @var \App\Services\FieldService $service */
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\DocumentRequirement;

$price = CourseEdition::effectivePrice($edition);
$stepNames = [
    1 => __('preinscription.step_student'),
    2 => __('preinscription.step_academic'),
    3 => __('preinscription.step_guardian'),
    4 => __('preinscription.step_documents'),
    5 => __('preinscription.step_review'),
];
$base = '/preinscripcion/' . $pre['id'] . '/paso/';
?>
<h1><?= e(__('preinscription.title')) ?></h1>
<div class="card" style="margin-bottom:16px">
    <strong><?= e(Course::localized($edition['course_title'] ?? '')) ?></strong> — <?= e((string) $edition['name']) ?>
    <?php if ($price !== null): ?><span class="badge" style="margin-left:8px"><?= e(number_format($price, 2)) ?> €</span><?php endif; ?>
</div>

<div class="steps" style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
    <?php foreach ($stepNames as $i => $name): ?>
        <?php if ($i === 3 && (int) $pre['is_minor'] !== 1) {
            continue;
        } ?>
        <div class="badge" style="<?= $i === $step ? 'background:var(--color-accent);color:#fff' : '' ?>"><?= $i ?>. <?= e($name) ?></div>
    <?php endforeach; ?>
</div>

<form method="post" action="<?= e(url($base . $step)) ?>" class="card" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <?php if ($step === 1): ?>
        <h3><?= e(__('preinscription.step_student')) ?></h3>
        <?php foreach ($studentFields as $f): ?>
            <?= $service->renderField($f, $values[$f->field_key] ?? '') ?>
        <?php endforeach; ?>
        <?php if ($studentFields === []): ?><p class="text-muted"><?= e(__('preinscription.no_fields')) ?></p><?php endif; ?>

    <?php elseif ($step === 2): ?>
        <h3><?= e(__('preinscription.step_academic')) ?></h3>
        <?php $acadValues = (new \App\Services\FieldService())->values('academic', (int) $pre['id']); ?>
        <?php foreach ($academicFields as $f): ?>
            <?= $service->renderField($f, $acadValues[$f->field_key] ?? '') ?>
        <?php endforeach; ?>
        <?php if ($academicFields === []): ?><p class="text-muted"><?= e(__('preinscription.no_fields')) ?></p><?php endif; ?>

    <?php elseif ($step === 3): ?>
        <h3><?= e(__('preinscription.step_guardian')) ?></h3>
        <p class="text-muted"><?= e(__('preinscription.guardian_intro')) ?></p>
        <div class="row">
            <div class="form-group"><label><?= e(__('preinscription.guardian_name')) ?></label><input type="text" name="guardian_name" value="<?= e($guardian['name'] ?? '') ?>" required></div>
            <div class="form-group"><label><?= e(__('preinscription.guardian_dni')) ?></label><input type="text" name="guardian_dni" value="<?= e($guardian['dni'] ?? '') ?>"></div>
        </div>
        <div class="row">
            <div class="form-group"><label><?= e(__('preinscription.guardian_email')) ?></label><input type="email" name="guardian_email" value="<?= e($guardian['email'] ?? '') ?>"></div>
            <div class="form-group"><label><?= e(__('preinscription.guardian_phone')) ?></label><input type="tel" name="guardian_phone" value="<?= e($guardian['phone'] ?? '') ?>"></div>
            <div class="form-group"><label><?= e(__('preinscription.guardian_relationship')) ?></label><input type="text" name="guardian_relationship" value="<?= e($guardian['relationship'] ?? '') ?>"></div>
        </div>
        <div class="form-group checkbox-row">
            <input type="checkbox" id="consent_1" name="consent_1" value="1" <?= !empty($guardian['consent_1']) ? 'checked' : '' ?>>
            <label for="consent_1" style="font-weight:400"><?= e(__('preinscription.guardian_consent_1')) ?></label>
        </div>
        <div class="form-group checkbox-row">
            <input type="checkbox" id="consent_2" name="consent_2" value="1" <?= !empty($guardian['consent_2']) ? 'checked' : '' ?>>
            <label for="consent_2" style="font-weight:400"><?= e(__('preinscription.guardian_consent_2')) ?></label>
        </div>

    <?php elseif ($step === 4): ?>
        <h3><?= e(__('preinscription.step_documents')) ?></h3>
        <?php $byReq = [];
        foreach ($documents as $d) {
            $byReq[(int) ($d['requirement_id'] ?? 0)] = $d;
        } ?>
        <?php if ($requirements === []): ?>
            <p class="text-muted"><?= e(__('preinscription.no_documents')) ?></p>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th><?= e(__('documents.document')) ?></th><th><?= e(__('documents.status')) ?></th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($requirements as $req): $doc = $byReq[(int) $req['id']] ?? null; ?>
                        <tr>
                            <td>
                                <?= e(DocumentRequirement::localized($req['name'])) ?>
                                <?php if ((int) $req['is_required'] === 1): ?><span style="color:var(--color-accent)">*</span><?php endif; ?>
                            </td>
                            <td><?php if ($doc): ?><span class="badge"><?= e(__('documents.statuses.' . $doc['status'])) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php elseif ($step === 5): ?>
        <h3><?= e(__('preinscription.step_review')) ?></h3>
        <p><?= e(__('preinscription.review_intro')) ?></p>
        <div class="form-group checkbox-row">
            <input type="checkbox" id="final_consent" name="final_consent" value="1" required>
            <label for="final_consent" style="font-weight:400"><?= e(__('preinscription.final_consent')) ?></label>
        </div>
    <?php endif; ?>

    <div class="actions" style="display:flex;justify-content:space-between;margin-top:16px;gap:12px">
        <?php $prev = $step - 1; if ($prev === 3 && (int) $pre['is_minor'] !== 1) {
            $prev = 2;
        } ?>
        <?php if ($step > 1): ?>
            <a class="btn btn-ghost" href="<?= e(url($base . $prev)) ?>"><?= e(__('common.back')) ?></a>
        <?php else: ?><span></span><?php endif; ?>
        <div style="display:flex;gap:8px">
            <?php if ($step < 5): ?>
                <button type="submit" name="save_exit" value="1" class="btn btn-ghost"><?= e(__('preinscription.save_exit')) ?></button>
                <button type="submit" class="btn btn-primary"><?= e(__('common.continue')) ?></button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary"><?= e(__('preinscription.submit_cta')) ?></button>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($step === 4 && $requirements !== []): ?>
    <!-- Subida de documentos (formularios independientes por requisito) -->
    <?php foreach ($requirements as $req): ?>
        <div class="card">
            <form method="post" action="<?= e(url('/preinscripcion/' . $pre['id'] . '/documento')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="requirement_id" value="<?= (int) $req['id'] ?>">
                <label><?= e(DocumentRequirement::localized($req['name'])) ?></label>
                <div class="row" style="align-items:flex-end">
                    <div class="form-group" style="flex:1"><input type="file" name="document" required></div>
                    <?php if ((int) $req['has_expiry'] === 1): ?>
                        <div class="form-group"><label><?= e(__('documents.expires_at')) ?></label><input type="date" name="expires_at"></div>
                    <?php endif; ?>
                    <div class="form-group"><button type="submit" class="btn btn-outline"><?= e(__('documents.upload')) ?></button></div>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
