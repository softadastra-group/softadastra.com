<?php
declare(strict_types=1);

namespace Modules\Chat\Core\Services;

final class ChatService
{
    public function info(): string
    {
        return 'Module Chat loaded successfully.';
    }
}