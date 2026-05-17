<?php
require_once __DIR__ . '/../../core/api.php';
$user = api_authenticated_user();
api_json_response(true, 'Auth check', [
    'logged_in' => $user !== false,
    'user' => $user,
    'session_id' => session_id(),
    'session_data' => $_SESSION
]);
