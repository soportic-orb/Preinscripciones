<?php
/** Detalle de un hilo de mensajería. */
use App\Core\Auth;

$myId = (int) Auth::id();
?>
<?php if ($isStaff) {
    require VIEW_PATH . '/partials/management_nav.php';
} ?>

<p><a href="<?= e(url('/panel/mensajes')) ?>">&larr; <?= e(__('messages.title')) ?></a></p>
<h1><?= e((string) $thread['subject']) ?></h1>

<div class="card">
    <?php foreach ($messages as $m): ?>
        <?php $mine = (int) $m['sender_id'] === $myId; ?>
        <div style="margin-bottom:12px;padding:10px 12px;border-radius:6px;background:<?= $mine ? 'var(--color-secondary-soft)' : 'var(--color-surface-alt)' ?>">
            <div style="font-size:.82rem;color:var(--color-muted)">
                <strong><?= e((string) $m['sender_name']) ?></strong>
                <?php if ((int) $m['is_staff'] === 1): ?><span class="badge"><?= e(__('messages.staff')) ?></span><?php endif; ?>
                · <?= e(substr((string) $m['created_at'], 0, 16)) ?>
            </div>
            <div><?= nl2br(e((string) $m['body'])) ?></div>
        </div>
    <?php endforeach; ?>

    <form method="post" action="<?= e(url('/panel/mensajes/' . $thread['id'])) ?>" style="margin-top:12px">
        <?= csrf_field() ?>
        <div class="form-group"><textarea name="body" rows="3" placeholder="<?= e(__('messages.reply_ph')) ?>" required></textarea></div>
        <button type="submit" class="btn btn-primary"><?= e(__('messages.send')) ?></button>
    </form>
</div>
