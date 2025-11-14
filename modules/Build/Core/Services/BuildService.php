<?php
declare(strict_types=1);

namespace Modules\Build\Core\Services;

final class BuildService
{
    public function info(): string
    {
        return 'Module Build loaded successfully.';
    }
}