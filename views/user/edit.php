<?php

/** @var App\Models\User $user */
/** @var \Ivi\Validation\ErrorBag|null $errors */
/** @var array<string,mixed>|null $old */

$u = $user->toArray();
$old = $old ?? [];
$errors = $errors ?? null;

// Préférence aux valeurs old (si validation échouée), sinon valeurs actuelles
$val = function (string $field, $fallback = '') use ($old, $u) {
    if (array_key_exists($field, $old)) return $old[$field];
    return $u[$field] ?? $fallback;
};
$firstError = function (string $field) use ($errors): ?string {
    return $errors ? $errors->first($field) : null;
};

// Checkbox 'active'
$activeChecked = array_key_exists('active', $old)
    ? true
    : (!empty($u['active']));
?>
<div class="users container py-4">
    <h1 class="text-center mb-4">Edit user #<?= (int)($u['id'] ?? 0) ?></h1>

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

    <form action="/users/<?= (int)($u['id'] ?? 0) ?>" method="post" novalidate class="needs-validation">

        <div class="mb-3">
            <label class="form-label">Name</label>
            <input
                type="text"
                name="name"
                class="form-control <?= $firstError('name') ? 'is-invalid' : '' ?>"
                value="<?= htmlspecialchars((string)$val('name', ''), ENT_QUOTES) ?>"
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
                value="<?= htmlspecialchars((string)$val('email', ''), ENT_QUOTES) ?>"
                required>
            <?php if ($e = $firstError('email')): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-3">
            <label class="form-label">New password (optional)</label>
            <input type="password"
                name="password"
                class="form-control <?= $firstError('password') ? 'is-invalid' : '' ?>"
                placeholder="Leave blank to keep current">
            <?php if ($e = $firstError('password')): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" name="active" value="1" class="form-check-input" id="activeCheck" <?= $activeChecked ? 'checked' : '' ?>>
            <label class="form-check-label" for="activeCheck">Active</label>
            <?php if ($e = $firstError('active')): ?>
                <div class="invalid-feedback d-block"><?= htmlspecialchars($e, ENT_QUOTES) ?></div>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Update</button>
            <a href="/users" class="btn btn-secondary" data-spa>Cancel</a>
        </div>
    </form>
</div>