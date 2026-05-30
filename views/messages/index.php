<?php
/** Bandeja de mensajería. */
/** @var \App\Services\MessageService $service */
use App\Core\Auth;
?>
<?php if ($isStaff) {
    require VIEW_PATH . '/partials/management_nav.php';
} ?>

<div class="toolbar">
    <h1><?= e(__('messages.title')) ?></h1>
</div>

<div class="grid-2">
    <div>
        <div class="card">
            <?php if ($threads === []): ?>
                <p class="text-muted" style="margin:0"><?= e(__('messages.empty')) ?></p>
            <?php else: ?>
                <table class="data-table">
                    <tbody>
                        <?php foreach ($threads as $t): ?>
                            <?php $unread = $service->hasUnread((int) $t['id'], (int) Auth::id()); ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('/panel/mensajes/' . $t['id'])) ?>" style="font-weight:<?= $unread ? '700' : '400' ?>">
                                        <?= e((string) $t['subject']) ?>
                                    </a>
                                    <?php if ($isStaff): ?><div class="field-hint"><?= e((string) ($t['student_name'] ?? '')) ?></div><?php endif; ?>
                                </td>
                                <td style="text-align:right">
                                    <?php if ($unread): ?><span class="badge"><?= e(__('messages.new')) ?></span><?php endif; ?>
                                    <div class="field-hint"><?= e($t['last_message_at'] ? substr((string) $t['last_message_at'], 0, 16) : '') ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$isStaff): ?>
    <div>
        <div class="card">
            <h3><?= e(__('messages.new_thread')) ?></h3>
            <form method="post" action="<?= e(url('/panel/mensajes')) ?>">
                <?= csrf_field() ?>
                <div class="form-group"><label><?= e(__('messages.subject')) ?></label><input type="text" name="subject" required></div>
                <div class="form-group"><label><?= e(__('messages.message')) ?></label><textarea name="body" rows="5" required></textarea></div>
                <button type="submit" class="btn btn-primary"><?= e(__('messages.send')) ?></button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
