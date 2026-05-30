<?php
/** Catálogo público de ediciones abiertas a preinscripción. */
use App\Core\Auth;
use App\Models\Course;
use App\Models\CourseEdition;

$editions = $editions ?? [];
?>
<h1><?= e(__('catalog.title')) ?></h1>
<p class="text-muted"><?= e(__('catalog.intro')) ?></p>

<?php if ($editions === []): ?>
    <div class="card"><p class="text-muted" style="margin:0"><?= e(__('catalog.no_open')) ?></p></div>
<?php else: ?>
    <div class="features">
        <?php foreach ($editions as $ed): ?>
            <?php
            $price = CourseEdition::effectivePrice($ed);
            $seats = (int) $ed['capacity'];
            $occupied = \App\Models\Preinscription::occupiedSeats((int) $ed['id']);
            $full = $seats > 0 && $occupied >= $seats;
            ?>
            <div class="card">
                <h3><?= e(Course::localized($ed['course_title'] ?? '')) ?></h3>
                <p class="text-muted" style="margin-bottom:8px"><strong><?= e((string) $ed['name']) ?></strong></p>
                <table class="info-table" style="margin-bottom:12px">
                    <tr><th><?= e(__('catalog.modality')) ?></th><td><?= e(__('catalog.modalities.' . $ed['modality'])) ?></td></tr>
                    <?php if (!empty($ed['start_date'])): ?><tr><th><?= e(__('catalog.start')) ?></th><td><?= e(substr((string) $ed['start_date'], 0, 10)) ?></td></tr><?php endif; ?>
                    <tr><th><?= e(__('catalog.price')) ?></th><td><?= $price !== null ? e(number_format($price, 2)) . ' €' : '—' ?></td></tr>
                    <tr><th><?= e(__('catalog.seats')) ?></th><td><?= $seats > 0 ? e((string) max(0, $seats - $occupied)) . ' / ' . e((string) $seats) : e(__('catalog.unlimited')) ?></td></tr>
                </table>
                <?php if (Auth::check()): ?>
                    <form method="post" action="<?= e(url('/preinscripcion/iniciar')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="edition_id" value="<?= (int) $ed['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-block">
                            <?= $full ? e(__('catalog.join_waitlist')) : e(__('catalog.preinscribe')) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-primary btn-block" href="<?= e(url('/login')) ?>"><?= e(__('catalog.login_to_preinscribe')) ?></a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
