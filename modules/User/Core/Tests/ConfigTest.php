<?php

use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testConfigFileReturnsArray(): void
    {
        $file = __DIR__ . '/../Config/user.php';

        $this->assertFileExists($file);

        $cfg = require $file;

        $this->assertIsArray($cfg);
    }
}
