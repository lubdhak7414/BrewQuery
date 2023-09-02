<?php

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function require_staff(): array {
    if (empty($_SESSION['staff_id'])) {
        redirect('login.php');
    }
    return $_SESSION['staff'] ?? [];
}

function require_role(string $role): array {
    $staff = require_staff();
    if (($staff['Role'] ?? '') !== $role) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><body>';
        echo '<p style="color:red;font-size:1.4em;padding:2rem">Access denied — ' . e($role) . ' role required.</p>';
        echo '<a href="index.php">Back to menu</a></body></html>';
        exit;
    }
    return $staff;
}

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
