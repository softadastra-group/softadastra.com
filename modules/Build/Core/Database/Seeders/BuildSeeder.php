<?php
declare(strict_types=1);

namespace Modules\Build\Core\Database\Seeders;

final class BuildSeeder
{
    public function run(): void
    {
        echo "[seed] Build ok\n";
    }
}

return new BuildSeeder();