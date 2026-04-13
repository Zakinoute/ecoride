<?php
// ============================================================
// EcoRide - Configuration base de données
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'ecoride');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('PLATFORM_FEE', 2); // crédits prélevés par EcoRide par trajet

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données.']);
            exit;
        }
    }
    return $pdo;
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
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
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
