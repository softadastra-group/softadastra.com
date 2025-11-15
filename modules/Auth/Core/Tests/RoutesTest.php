<?php

use Modules\Auth\Core\Tests\Fakes\FakeRouter;
use PHPUnit\Framework\TestCase;

final class RoutesTest extends TestCase
{
    public function testRoutesFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../routes/web.php');
    }

    public function testRoutesCanBeLoaded(): void
    {
        // ✅ utiliser FakeRouter au lieu d'un mock impossible
        $router = new FakeRouter();

        // Charger le fichier de routes
        require __DIR__ . '/../routes/web.php';

        // Vérifier que le fichier est bien chargé sans erreur
        $this->assertNotEmpty(
            $router->registered,
            "Routes file should register at least one route"
        );
    }
}
