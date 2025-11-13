<?php

namespace Modules\User\Core\Utils;

class FlashMessage
{
    public static function add($type, $message)
    {
        $_SESSION['flash_messages'][$type][] = $message;
    }

    public static function get()
    {
        $messages = [];

        if (isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
            foreach ($_SESSION['flash_messages'] as $type => $messagesArray) {
                foreach ($messagesArray as $message) {
                    $messages[] = [
                        'type' => $type,
                        'message' => $message
                    ];
                }
            }

            unset($_SESSION['flash_messages']);
        }

        return $messages;
    }
}
