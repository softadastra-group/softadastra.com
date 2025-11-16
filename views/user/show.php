<?php

/** @var App\Models\User $user */ $u = $user->toArray(); ?>
<div class="users container py-4">
    <div class="card p-4 shadow-sm">
        <h1 class="text-center mb-4">User #<?= (int)$u['id'] ?></h1>

        <ul class="list-group mb-3">
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <strong>Name:</strong>
                <span><?= htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES) ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <strong>Email:</strong>
                <span><?= htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES) ?></span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <strong>Active:</strong>
                <span><?= !empty($u['active']) ? 'Yes' : 'No' ?></span>
            </li>
        </ul>

        <div class="d-flex gap-2 justify-content-center">
            <a href="/users/<?= (int)$u['id'] ?>/edit" class="btn btn-warning" data-spa>Edit</a>
            <a href="/users" class="btn btn-secondary" data-spa>Back</a>
        </div>
    </div>
</div>