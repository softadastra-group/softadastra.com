<?php

/** views/home.php */

?>
<div class="user-home-container">
    <header>
        <h1><?= htmlspecialchars($message) ?></h1>
    </header>

    <section class="user-info">
        <h2>User Information</h2>
        <?php if (!empty($user)): ?>
            <ul>
                <li><strong>ID:</strong> <?= (int)$user['id'] ?></li>
                <li><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></li>
                <li><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></li>
                <li><strong>Roles:</strong> <?= htmlspecialchars(implode(', ', $user['roles'] ?? [])) ?></li>
            </ul>
        <?php else: ?>
            <p>No user data available.</p>
        <?php endif; ?>
    </section>

    <section class="user-actions">
        <button class="btn-logout">Logout</button>
    </section>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Attache l'événement logout à tous les boutons avec la classe .btn-logout
        document.querySelectorAll('.btn-logout').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault(); // empêche le comportement par défaut
                try {
                    const res = await fetch('/auth/logout', {
                        method: 'POST', // ou GET selon ta route
                        credentials: 'include',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    const data = await res.json();

                    if (data.success) {
                        // Nettoyage côté frontend si tu as un store ou localStorage
                        if (window.userStore) window.userStore.reset();
                        localStorage.removeItem('user'); // si tu stockes l'user
                        // Redirection vers la page de login
                        window.location.href = '/auth/login';
                    } else {
                        console.warn('Logout failed', data);
                    }
                } catch (err) {
                    console.error('Logout request failed', err);
                }
            });
        });
    });
</script>