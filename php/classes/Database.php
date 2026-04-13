<?php
/**
 * Database — Singleton PDO (MySQL)
 * Fournit une connexion unique réutilisable dans toute l'application.
 */
class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host    = $_ENV['DB_HOST']    ?? 'localhost';
            $dbname  = $_ENV['DB_NAME']    ?? 'ecoride';
            $user    = $_ENV['DB_USER']    ?? 'root';
            $pass    = $_ENV['DB_PASS']    ?? '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Erreur base de données.']);
                exit;
            }
        }
        return self::$instance;
    }
}
