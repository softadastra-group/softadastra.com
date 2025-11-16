<?php
declare(strict_types=1);

namespace Modules\Testing\Core\Services;

final class TestingService
{
    public function info(): string
    {
        return 'Module Testing loaded successfully.';
    }
}