<?php
declare(strict_types=1);

namespace Modules\Studio\Core\Database\Seeders;

final class StudioSeeder
{
    public function run(): void
    {
        echo "[seed] Studio ok\n";
    }
}

return new StudioSeeder();