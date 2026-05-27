<?php
require_once __DIR__ . '/includes/config.php';

function redirect_legacy_entry($target) {
    $target_url = site_url($target);
    $query_string = $_SERVER['QUERY_STRING'] ?? '';

    if ($query_string !== '') {
        $target_url .= (str_contains($target_url, '?') ? '&' : '?') . $query_string;
    }

    header('Location: ' . $target_url, true, 302);
    exit;
}
