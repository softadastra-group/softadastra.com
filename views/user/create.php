<?php

/** @var \Ivi\Validation\ErrorBag|null $errors */
/** @var array<string,mixed>|null $old */
$old = $old ?? [];
$errors = $errors ?? null;

$firstError = function (string $field) use ($errors): ?string {
    return $errors ? $errors->first($field) : null;
};
?>
<div class="users container py-4">
    <div class="card p-4 shadow-sm">
        <h1 class="text-center mb-4">New User</h1>

        <?php if ($errors && !$errors->isEmpty()): ?>
            <div class="alert alert-danger">
                <strong>There were some problems with your input:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors->all() as $field => $messages): ?>
                        <?php foreach ($messages as $m): ?>
                            <li><?= htmlspecialchars("{$field}: {$m}", ENT_QUOTES) ?></li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="/users" method="post" novalidate class="needs-validation">

            <div class="mb-3">
                <label class="form-label">Name</label>
                <input
                    type="text"
                    name="name"
                    class="form-control <?= $firstError('name') ? 'is-invalid' : '' ?>"
                    value="<?= htmlspecialchars((string)($old['name'] ?? ''), ENT_QUOTES) ?>"
                    required>
                <?php if ($e = $firstError('name')): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    class="form-control <?= $firstError('email') ? 'is-invalid' : '' ?>"
                    value="<?= htmlspecialchars((string)($old['email'] ?? ''), ENT_QUOTES) ?>"
                    required>
                <?php if ($e = $firstError('email')): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input
                    type="password"
                    name="password"
                    class="form-control <?= $firstError('password') ? 'is-invalid' : '' ?>"
                    required>
                <?php if ($e = $firstError('password')): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox"
                    name="active"
                    value="1"
                    class="form-check-input"
                    id="activeCheck"
                    <?= array_key_exists('active', $old ?? []) ? 'checked' : '' ?>>
                <label class="form-check-label" for="activeCheck">Active</label>
                <?php if ($e = $firstError('active')): ?>
                    <div class="invalid-feedback d-block"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create</button>
                <a href="/users" class="btn btn-secondary" data-spa>Cancel</a>
            </div>
        </form>
    </div>
</div>