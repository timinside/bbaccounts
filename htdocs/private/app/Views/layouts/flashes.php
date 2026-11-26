<?php
use BBA\Core\Auth;
use BBA\Core\Session;

$account = $account ?? Auth::user();
$success = Session::pull('_success');
$error = Session::pull('_error');
?>
<?php if (is_string($success) && $success !== ''): ?>
<div class="alert alert-success" role="status" data-autohide>
    <span class="alert-icon"><?= icon('check') ?></span>
    <span><?= e($success) ?></span>
    <button type="button" class="alert-close" aria-label="Dismiss" data-alert-close><?= icon('x') ?></button>
</div>
<?php endif; ?>
<?php if (is_string($error) && $error !== ''): ?>
<div class="alert alert-error" role="alert" data-autohide>
    <span class="alert-icon"><?= icon('alert') ?></span>
    <span><?= e($error) ?></span>
    <button type="button" class="alert-close" aria-label="Dismiss" data-alert-close><?= icon('x') ?></button>
</div>
<?php endif; ?>
