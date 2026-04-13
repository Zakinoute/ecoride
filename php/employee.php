<?php
// ============================================================
// EcoRide - Espace Employé
// ============================================================
require_once 'config.php';
startSession();

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // Trajets signalés comme "mal passés"
    // ----------------------------------------------------------
    case 'reported_trips':
        requireRole('employee');
        $pdo  = getPDO();
        $stmt = $pdo->prepare("
            SELECT t.id, t.departure_city, t.arrival_city, t.departure_datetime,
                   t.report_reason, t.status,
                   d.id AS driver_id, d.pseudo AS driver_pseudo, d.email AS driver_email,
                   GROUP_CONCAT(DISTINCT p.pseudo ORDER BY p.pseudo SEPARATOR ', ') AS passengers
            FROM trips t
            JOIN users d ON d.id = t.driver_id
            LEFT JOIN bookings b ON b.trip_id = t.id AND b.status = 'confirmed'
            LEFT JOIN users p ON p.id = b.passenger_id
            WHERE t.is_reported = 1
            GROUP BY t.id
            ORDER BY t.departure_datetime DESC
        ");
        $stmt->execute();
        jsonResponse(true, '', ['trips' => $stmt->fetchAll()]);
        break;

    // ----------------------------------------------------------
    // Statistiques rapides pour le dashboard employé
    // ----------------------------------------------------------
    case 'stats':
        requireRole('employee');
        $pdo = getPDO();

        $pending  = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
        $reported = $pdo->query("SELECT COUNT(*) FROM trips WHERE is_reported = 1")->fetchColumn();

        jsonResponse(true, '', ['pending_reviews' => (int)$pending, 'reported_trips' => (int)$reported]);
        break;

    default:
        jsonResponse(false, 'Action non reconnue.');
}
