<?php
/**
 * EcoRide — Statistiques MongoDB (NoSQL)
 * =========================================================
 * Endpoints :
 *   GET  ?action=stats_trips   → nb de covoiturages par jour (30 derniers jours)
 *   GET  ?action=stats_credits → crédits gagnés par la plateforme par jour
 *   POST ?action=log_booking   → enregistre un événement (appelé par booking.php)
 *   GET  ?action=total_credits → total crédits plateforme
 * =========================================================
 * Nécessite l'extension PHP MongoDB (pecl install mongodb)
 * et le package mongodb/mongodb (composer require mongodb/mongodb)
 * OU utilise l'extension native avec le driver de base.
 */

require_once __DIR__ . '/config.php';
startSession();
header('Content-Type: application/json; charset=utf-8');

// ── Connexion MongoDB ─────────────────────────────────────────
function getMongo(): MongoDB\Database
{
    static $db = null;
    if ($db === null) {
        $uri    = $_ENV['MONGO_URI'] ?? 'mongodb://localhost:27017';
        $dbName = $_ENV['MONGO_DB']  ?? 'ecoride_stats';

        // Si le package mongodb/mongodb est installé via Composer
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            $client = new MongoDB\Client($uri);
        } else {
            // Extension native PECL mongodb (driver bas niveau)
            $manager = new MongoDB\Driver\Manager($uri);
            // Encapsulation minimale pour compatibilité
            return new class($manager, $dbName) {
                public MongoDB\Driver\Manager $manager;
                public string $dbName;
                public function __construct($m, $d) { $this->manager = $m; $this->dbName = $d; }
                public function selectCollection(string $col): object {
                    return new class($this->manager, $this->dbName, $col) {
                        public function __construct(
                            public MongoDB\Driver\Manager $mgr,
                            public string $db,
                            public string $col
                        ) {}
                        public function insertOne(array $doc): void {
                            $bulk = new MongoDB\Driver\BulkWrite();
                            $bulk->insert($doc);
                            $this->mgr->executeBulkWrite("{$this->db}.{$this->col}", $bulk);
                        }
                        public function aggregate(array $pipeline): array {
                            $cmd = new MongoDB\Driver\Command(['aggregate' => $this->col, 'pipeline' => $pipeline, 'cursor' => new stdClass()]);
                            $cursor = $this->mgr->executeCommand($this->db, $cmd);
                            return iterator_to_array($cursor);
                        }
                    };
                }
            };
        }
        $db = $client->selectDatabase($dbName);
    }
    return $db;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ── Enregistrer un booking (appelé depuis Booking.php) ────
    case 'log_booking':
        requireRole('user'); // doit être connecté
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $tripId    = (int) ($data['trip_id']    ?? 0);
        $credits   = (int) ($data['credits']    ?? 0);
        $isEco     = (bool)($data['is_eco']     ?? false);

        if (!$tripId) {
            jsonResponse(false, 'trip_id manquant.');
        }

        $db = getMongo();
        $col = $db->selectCollection('bookings_log');
        $col->insertOne([
            'trip_id'    => $tripId,
            'credits'    => $credits,
            'is_eco'     => $isEco,
            'date'       => date('Y-m-d'),
            'created_at' => new MongoDB\BSON\UTCDateTime(),
        ]);
        jsonResponse(true, 'Log enregistré.');
        break;

    // ── Nb covoiturages par jour (30 derniers jours) ──────────
    case 'stats_trips':
        requireRole('admin');
        $db  = getMongo();
        $col = $db->selectCollection('bookings_log');

        $pipeline = [
            ['$match' => [
                'date' => ['$gte' => date('Y-m-d', strtotime('-30 days'))]
            ]],
            ['$group' => [
                '_id'   => '$date',
                'count' => ['$sum' => 1],
            ]],
            ['$sort' => ['_id' => 1]],
        ];

        $results = $col->aggregate($pipeline);
        $data = [];
        foreach ($results as $r) {
            $data[] = ['date' => (string)($r['_id'] ?? $r->_id), 'count' => (int)($r['count'] ?? $r->count)];
        }
        jsonResponse(true, '', ['stats' => $data]);
        break;

    // ── Crédits plateforme par jour ───────────────────────────
    case 'stats_credits':
        requireRole('admin');
        $db  = getMongo();
        $col = $db->selectCollection('bookings_log');

        $pipeline = [
            ['$match' => [
                'date' => ['$gte' => date('Y-m-d', strtotime('-30 days'))]
            ]],
            ['$group' => [
                '_id'            => '$date',
                'total_credits'  => ['$sum' => 2], // 2 crédits plateforme par réservation
            ]],
            ['$sort' => ['_id' => 1]],
        ];

        $results = $col->aggregate($pipeline);
        $data = [];
        foreach ($results as $r) {
            $data[] = ['date' => (string)($r['_id'] ?? $r->_id), 'credits' => (int)($r['total_credits'] ?? $r->total_credits)];
        }
        jsonResponse(true, '', ['stats' => $data]);
        break;

    // ── Total crédits plateforme ──────────────────────────────
    case 'total_credits':
        requireRole('admin');
        $pdo  = getPDO();
        $stmt = $pdo->query('SELECT COALESCE(SUM(amount), 0) AS total FROM platform_credits');
        $row  = $stmt->fetch();
        jsonResponse(true, '', ['total' => (float) $row['total']]);
        break;

    default:
        jsonResponse(false, 'Action non reconnue.');
}
