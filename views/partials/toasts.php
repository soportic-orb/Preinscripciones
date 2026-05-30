<?php
/** Contenedor de toasts (zona superior central). Consume los mensajes flash. */
use App\Core\Flash;

$flashes = Flash::pull();
if ($flashes === []) {
    return;
}
?>
<div class="toast-container" role="status" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
        <div class="toast toast-<?= e($flash['type']) ?>">
            <span><?= e($flash['message']) ?></span>
            <button type="button" aria-label="×">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
