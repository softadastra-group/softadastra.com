<?php

use Ivi\Core\ORM\QueryBuilder;

$users = QueryBuilder::table('users u')
    ->select('u.id', 'u.fullname', 'r.name AS role_name')
    ->leftJoin('user_roles ur', 'ur.user_id = u.id')
    ->leftJoin('roles r', 'r.id = ur.role_id')
    ->where('u.status = ?', 'active')
    ->orderBy('u.id DESC')
    ->get();
