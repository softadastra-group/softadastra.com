<?php

namespace Modules\User\Core\Utils;

class RedirectionHelper
{
    public static function redirect($url)
    {
        header("Location: /$url");
        exit();
    }

    public static function getUrl(string $path, ?int $id = null, ?string $param = '')
    {
        if (isset($id) && $id != 0) {
            return '/' . $path . '/' . $id;
        } else {
            if (isset($param) && $param != '') {
                return '/' . $path . '?' . $param;
            } else {
                return '/' . $path;
            }
        }
    }
}
