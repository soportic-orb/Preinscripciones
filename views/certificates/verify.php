<?php /** Verificación pública de un certificado por código. */ ?>
<h1><?= e(__('certificates.verify_title')) ?></h1>

<form method="get" action="<?= e(url('/verificar-certificado')) ?>" class="card" style="max-width:480px">
    <div class="form-group">
        <label><?= e(__('certificates.verification_code')) ?></label>
        <input type="text" name="code" value="<?= e($code) ?>" placeholder="XXXXXXXXXXXX">
    </div>
    <button type="submit" class="btn btn-primary"><?= e(__('certificates.verify_cta')) ?></button>
</form>

<?php if ($code !== ''): ?>
    <div class="card" style="max-width:480px">
        <?php if ($cert !== null): ?>
            <div class="alert alert-ok" style="background:var(--color-success-bg);color:var(--color-success);border:1px solid var(--color-success);padding:12px;border-radius:5px">
                ✓ <?= e(__('certificates.valid')) ?>
            </div>
            <table class="info-table">
                <tr><th><?= e(__('certificates.student')) ?></th><td><?= e((string) $cert['student_name']) ?></td></tr>
                <tr><th><?= e(__('catalog.course')) ?></th><td><?= e((string) $cert['course_name']) ?></td></tr>
                <tr><th><?= e(__('catalog.edition')) ?></th><td><?= e((string) ($cert['edition_name'] ?? '')) ?></td></tr>
                <tr><th><?= e(__('certificates.issued_on')) ?></th><td><?= e(substr((string) $cert['issued_at'], 0, 10)) ?></td></tr>
            </table>
        <?php else: ?>
            <div class="alert" style="background:var(--color-error-bg);color:var(--color-error);border:1px solid var(--color-error);padding:12px;border-radius:5px">
                ✗ <?= e(__('certificates.invalid')) ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
