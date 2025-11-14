<?php

use Ivi\Core\Security\Csrf;

// Générer un token (dans un controller ou middleware)
$token = Csrf::generateToken();

// Vérifier un token en POST
Csrf::verifyToken($_POST['csrf'] ?? null);
