<?php
// ============================================================
// EcoRide - Configuration et chargement des classes
// ============================================================

// Chargement automatique des classes métier : `new Booking()` charge
// php/classes/Booking.php la première fois que la classe est utilisée.
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Connexion MySQL : une seule connexion, fournie par la classe Database
// (les identifiants viennent des variables d'environnement).
function getPDO(): PDO {
    return Database::getInstance();
}

// ============================================================
// Démarrage de session sécurisé
// ============================================================
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => false, // mettre true en production (HTTPS)
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// ============================================================
// Helpers
// ============================================================
function jsonResponse(bool $success, string $message = '', array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireAuth(): array {
    startSession();
    if (empty($_SESSION['user_id'])) {
        jsonResponse(false, 'Non authentifié.', ['redirect' => '../login.html']);
    }
    return $_SESSION;
}

function requireRole(string $role): array {
    $session = requireAuth();
    if ($session['role'] !== $role && !($role === 'employee' && $session['role'] === 'admin')) {
        jsonResponse(false, 'Accès refusé.');
    }
    return $session;
}

function sanitize(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}
