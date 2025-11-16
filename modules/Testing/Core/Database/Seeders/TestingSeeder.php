<?php
declare(strict_types=1);

namespace Modules\Testing\Core\Database\Seeders;

final class TestingSeeder
{
    public function run(): void
    {
        echo "[seed] Testing ok\n";
    }
}

return new TestingSeeder();