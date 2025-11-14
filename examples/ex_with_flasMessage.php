<?php

use Ivi\Core\Utils\FlashMessage;

foreach (FlashMessage::get() as $flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>">
        <?= $flash['message'] ?>
    </div>
<?php endforeach ?>