<?php
/** One row of the Teams showcase. Expects $teamRow from Team::forProfile(). */
?>
<div class="list-row">
    <span class="list-icon"><?= icon('users') ?></span>
    <div class="list-main">
        <span class="list-title"><?= e((string) ($teamRow['name'] ?? 'Team')) ?></span>
        <span class="list-sub"><?= e((string) ($teamRow['description'] ?? '')) ?><?= (int) ($teamRow['subscribers'] ?? 0) > 0 ? ' · ' . (int) $teamRow['subscribers'] . ' subscriber(s)' : '' ?></span>
    </div>
    <span class="badge badge-accent"><?= e((string) ($teamRow['role'] ?? 'subscriber')) ?></span>
</div>
