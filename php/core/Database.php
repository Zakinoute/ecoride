<?php
/**
 * Database — Singleton PDO (MySQL)
 * Fournit une connexion unique réutilisable dans toute l'application.
 *
 * Les identifiants viennent des variables d'environnement (docker-compose.yml,
 * hébergeur). Les valeurs par défaut servent au développement local (XAMPP).
 */
class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host    = getenv('DB_HOST') ?: 'localhost';
            $dbname  = getenv('DB_NAME') ?: 'ecoride';
            $user    = getenv('DB_USER') ?: 'root';
            $pass    = getenv('DB_PASS') ?: '';
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
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données.']);
                exit;
            }
        }
        return self::$instance;
    }

    /**
     * Exécute $work dans une transaction : tout est enregistré, ou rien.
     *
     * Tous les repositories utilisent la même connexion (singleton) :
     * leurs requêtes font donc partie de la même transaction.
     */
    public static function transaction(callable $work): mixed
    {
        $pdo = self::getInstance();
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
