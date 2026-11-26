<?php
$accent = old('accent_color', '#5b6cff');
?>
<div class="page-head">
    <div>
        <p class="mono-label">Developer</p>
        <h1>New project</h1>
        <p class="page-sub">A project is an application that delegates sign-in to bbaccounts. You can change everything except the slug later.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/developer')) ?>"><?= icon('arrow-left') ?><span>Projects</span></a>
    </div>
</div>

<form method="post" action="<?= e(url('/developer/projects')) ?>" class="stack">
    <?= csrf_field() ?>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Identity</h2>
                <p>Shown on the sign-in and consent screens.</p>
            </div>
        </div>
        <div class="card-body form">
            <div class="field">
                <label for="name">Project name</label>
                <input id="name" name="name" type="text" value="<?= e(old('name')) ?>" maxlength="80" minlength="2" placeholder="Example App" required autofocus>
                <?php if ($error = form_error('name')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                <p class="field-hint">The public page and brand line use this name.</p>
            </div>

            <div class="field">
                <label for="description">Short description</label>
                <textarea id="description" name="description" maxlength="280" placeholder="What the project does, in one sentence."><?= e(old('description')) ?></textarea>
                <?php if ($error = form_error('description')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                <p class="field-hint">Optional. Up to 280 characters.</p>
            </div>

            <div class="field-grid">
                <div class="field">
                    <label for="domain">Primary domain</label>
                    <input id="domain" name="domain" type="text" value="<?= e(old('domain')) ?>" maxlength="190" placeholder="app.example.com or bbaccounts.unaux.com" inputmode="url" required>
                    <?php if ($error = form_error('domain')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                    <p class="field-hint">Domain or IPv4 address, with an optional port. A full http:// URL also works.</p>
                </div>

                <div class="field">
                    <label for="accent_color">Accent color</label>
                    <div class="color-row">
                        <input id="accent_color" name="accent_color" type="color" value="<?= e($accent) ?>" aria-describedby="accent-hint">
                        <span class="mono" id="accent-hint"><?= e($accent) ?></span>
                    </div>
                    <p class="field-hint">Used on your branded sign-in screen.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body">
            <div class="note">
                <?= icon('key') ?>
                <span>A client ID and client secret are generated with the project. The secret is displayed once, right after creation. Register redirect URIs before connecting an app.</span>
            </div>
            <div class="button-row">
                <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Create project</span></button>
                <a class="btn btn-ghost" href="<?= e(url('/developer')) ?>">Cancel</a>
            </div>
        </div>
    </section>
</form>
