<?php
/** Selector de idioma (es / ca / en / pt). */
use App\Core\Config;
use App\Core\I18n;

$current = I18n::locale();
?>
<span class="lang-switch" aria-label="<?= e(__('common.language')) ?>">
    <?php foreach (Config::locales() as $loc): ?>
        <a href="<?= e(url('/cambiar-idioma?lang=' . $loc)) ?>"
           class="<?= $loc === $current ? 'active' : '' ?>"
           title="<?= e(I18n::nativeName($loc)) ?>"><?= e($loc) ?></a>
    <?php endforeach; ?>
</span>
