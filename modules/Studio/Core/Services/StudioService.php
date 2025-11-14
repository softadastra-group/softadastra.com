<?php
declare(strict_types=1);

namespace Modules\Studio\Core\Services;

final class StudioService
{
    public function info(): string
    {
        return 'Module Studio loaded successfully.';
    }
}