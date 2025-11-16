<?php

/** @var \Ivi\Core\ORM\Pagination $page */ ?>
<div class="users container py-4">

    <h1 class="mb-4">Users</h1>

    <p>
        <a href="/users/create" class="btn btn-success">+ New user</a>
    </p>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($page->items as $u): $arr = $u->toArray(); ?>
                    <tr>
                        <td><?= (int)($arr['id'] ?? 0) ?></td>
                        <td><?= htmlspecialchars((string)($arr['name'] ?? ''), ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars((string)($arr['email'] ?? ''), ENT_QUOTES) ?></td>
                        <td><?= !empty($arr['active']) ? 'Yes' : 'No' ?></td>
                        <td>
                            <a href="/users/<?= (int)$arr['id'] ?>" class="btn btn-sm btn-primary">Show</a>
                            <a href="/users/<?= (int)$arr['id'] ?>/edit" class="btn btn-sm btn-warning">Edit</a>
                            <form action="/users/<?= (int)$arr['id'] ?>/delete" method="post" class="d-inline" onsubmit="return confirm('Delete user?');">
                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="mt-3">
        Total: <?= $page->total ?> — Page <?= $page->currentPage ?> / <?= $page->lastPage ?>
    </p>

    <?php if ($page->hasPrev() || $page->hasNext()): ?>
        <nav>
            <ul class="pagination">
                <?php if ($page->hasPrev()): ?>
                    <li class="page-item"><a class="page-link" href="?page=1&per_page=<?= $page->perPage ?>" data-spa>« First</a></li>
                    <li class="page-item"><a class="page-link" href="?page=<?= $page->prevPage() ?>&per_page=<?= $page->perPage ?>" data-spa>‹ Prev</a></li>
                <?php endif; ?>
                <?php if ($page->hasNext()): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?= $page->nextPage() ?>&per_page=<?= $page->perPage ?>" data-spa>Next ›</a></li>
                    <li class="page-item"><a class="page-link" href="?page=<?= $page->lastPage ?>&per_page=<?= $page->perPage ?>" data-spa>Last »</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>