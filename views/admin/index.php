<?php
if (!theme::render('head', get_defined_vars())) {
    require APPROOT . '/views/inc/head.php';
}
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$modules = $data['modules'] ?? [];
$report = $data['report'] ?? null;
?>
<main class="container py-4">
    <h1>CDM — Diagnostic Service</h1>
    <p>Read-only source and configuration inspection.</p>
    <?php if (!empty($data['error'])): ?>
        <div class="alert alert-danger"><?= $escape($data['error']); ?></div>
    <?php endif; ?>
    <form action="/admin/cdm" method="post">
        <?= $this->csrf_field(); ?>
        <label class="form-label" for="cdm-module">Installed module</label>
        <select id="cdm-module" name="module" class="form-select mb-3" required>
            <?php foreach ($modules as $name): ?>
                <option value="<?= $escape($name); ?>"><?= $escape($name); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary" <?= $modules === [] ? 'disabled' : ''; ?>>Diagnose</button>
    </form>
    <?php if (is_array($report)): ?>
        <section class="mt-4">
            <h2>Module: <?= $escape($report['module']); ?></h2>
            <p><?= $escape($report['note']); ?></p>
            <?php if ($report['findings'] === []): ?>
                <div class="alert alert-secondary">No findings from the currently implemented checks.</div>
            <?php endif; ?>
            <?php foreach ($report['findings'] as $finding): ?>
                <article class="card mb-3"><div class="card-body">
                    <h3 class="h5"><?= $escape($finding['summary']); ?></h3>
                    <p><strong>Route:</strong> <?= $escape($finding['route']); ?></p>
                    <p><strong>Code:</strong> <code><?= $escape($finding['code']); ?></code></p>
                    <p><strong>Classification:</strong> <?= $escape($finding['status']); ?></p>
                    <h4 class="h6">Evidence</h4><pre class="text-wrap"><?= $escape($finding['evidence']); ?></pre>
                    <h4 class="h6">Recommended repair</h4><p><?= $escape($finding['repair']); ?></p>
                </div></article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<?php
if (!theme::render('foot', get_defined_vars())) {
    require APPROOT . '/views/inc/foot.php';
}