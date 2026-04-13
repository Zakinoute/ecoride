<?php
// ============================================================
// EcoRide - Gestion des avis
// ============================================================
require_once 'config.php';
startSession();

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // Soumettre un avis
    // ----------------------------------------------------------
    case 'submit':
        $session  = requireAuth();
        $tripId   = (int)($_POST['trip_id'] ?? 0);
        $rating   = (int)($_POST['rating'] ?? 0);
        $comment  = sanitize($_POST['comment'] ?? '');

        if (!$tripId || $rating < 1 || $rating > 5) {
            jsonResponse(false, 'Note invalide (1 à 5 requis).');
        }

        $pdo = getPDO();

        // Vérifier que le trajet est terminé et que l'utilisateur y a participé
        $stmt = $pdo->prepare("
            SELECT t.driver_id FROM trips t
            JOIN bookings b ON b.trip_id = t.id AND b.passenger_id = ?
            WHERE t.id = ? AND t.status = 'completed'
        ");
        $stmt->execute([$session['user_id'], $tripId]);
        $trip = $stmt->fetch();
        if (!$trip) jsonResponse(false, 'Vous ne pouvez noter que les trajets terminés auxquels vous avez participé.');

        // Éviter le doublon
        $stmt = $pdo->prepare('SELECT id FROM reviews WHERE trip_id = ? AND reviewer_id = ?');
        $stmt->execute([$tripId, $session['user_id']]);
        if ($stmt->fetch()) jsonResponse(false, 'Vous avez déjà laissé un avis pour ce trajet.');

        $pdo->prepare('INSERT INTO reviews (trip_id, reviewer_id, reviewed_id, rating, comment) VALUES (?, ?, ?, ?, ?)')
            ->execute([$tripId, $session['user_id'], $trip['driver_id'], $rating, $comment]);

        jsonResponse(true, 'Avis soumis. Il sera visible après validation par notre équipe.');
        break;

    // ----------------------------------------------------------
    // Avis en attente (employé)
    // ----------------------------------------------------------
    case 'pending':
        requireRole('employee');
        $pdo  = getPDO();
        $stmt = $pdo->prepare("
            SELECT r.*, u.pseudo AS reviewer_pseudo, d.pseudo AS driver_pseudo,
                   t.departure_city, t.arrival_city, t.departure_datetime
            FROM reviews r
            JOIN users u ON u.id = r.reviewer_id
            JOIN users d ON d.id = r.reviewed_id
            JOIN trips t ON t.id = r.trip_id
            WHERE r.status = 'pending'
            ORDER BY r.created_at ASC
        ");
        $stmt->execute();
        jsonResponse(true, '', ['reviews' => $stmt->fetchAll()]);
        break;

    // ----------------------------------------------------------
    // Valider ou refuser un avis (employé)
    // ----------------------------------------------------------
    case 'moderate':
        requireRole('employee');
        $reviewId = (int)($_POST['review_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        if (!in_array($decision, ['approved', 'rejected'])) jsonResponse(false, 'Décision invalide.');

        $pdo = getPDO();
        $pdo->prepare('UPDATE reviews SET status = ? WHERE id = ?')->execute([$decision, $reviewId]);
        jsonResponse(true, 'Avis ' . ($decision === 'approved' ? 'validé' : 'refusé') . '.');
        break;

    // ----------------------------------------------------------
    // Avis d'un conducteur (public)
    // ----------------------------------------------------------
    case 'driver':
        $driverId = (int)($_GET['driver_id'] ?? 0);
        if (!$driverId) jsonResponse(false, 'ID manquant.');
        $pdo  = getPDO();
        $stmt = $pdo->prepare("
            SELECT r.rating, r.comment, r.created_at, u.pseudo AS reviewer_pseudo, u.photo
            FROM reviews r
            JOIN users u ON u.id = r.reviewer_id
            WHERE r.reviewed_id = ? AND r.status = 'approved'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$driverId]);
        jsonResponse(true, '', ['reviews' => $stmt->fetchAll()]);
        break;

    default:
        jsonResponse(false, 'Action non reconnue.');
}
