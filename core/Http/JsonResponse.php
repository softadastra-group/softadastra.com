<?php

declare(strict_types=1);

namespace Ivi\Http;

final class JsonResponse extends Response
{
    private int $encodingOptions;
    private int $depth;

    /**
     * Callback pour override le send() dans les tests
     * @var null|callable
     */
    private static $overrideSendCallback = null;

    public function __construct(
        mixed $data = null,
        int $status = 200,
        array $headers = [],
        int $encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        int $depth = 512
    ) {
        parent::__construct('', $status, ['Content-Type' => 'application/json; charset=utf-8'] + $headers);
        $this->encodingOptions = $encodingOptions;
        $this->depth = $depth;

        if ($data !== null) {
            $this->setData($data);
        }
    }

    public function setData(mixed $data): self
    {
        $json = json_encode($data, $this->encodingOptions | JSON_THROW_ON_ERROR, $this->depth);
        $this->setContent($json);
        return $this;
    }

    public function setEncodingOptions(int $options): self
    {
        $this->encodingOptions = $options;
        return $this;
    }

    public function setDepth(int $depth): self
    {
        $this->depth = $depth;
        return $this;
    }

    /** Override le comportement de send() pour les tests */
    public static function overrideSend(?callable $callback): void
    {
        self::$overrideSendCallback = $callback;
    }

    public function send(): void
    {
        if (self::$overrideSendCallback) {
            ($this::$overrideSendCallback)($this);
            return;
        }

        parent::send();
    }

    /** Récupère les données JSON encodées */
    public function getData(): mixed
    {
        return json_decode($this->content(), true);
    }
}
