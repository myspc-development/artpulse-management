<?php
namespace EAD;

if (!class_exists('EAD\\Roles\\RolesManager', false)) {
    require __DIR__ . '/Roles/RolesManager.php';
}

class_alias('EAD\\Roles\\RolesManager', __NAMESPACE__ . '\\RolesManager');
