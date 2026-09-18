<?php
// ============================================================
// EcoRide - Gestion des avis
// Contrôleur : la logique des avis est dans la classe Review.
// ============================================================
require_once 'config.php';
startSession();

header('Content-Type: application/json; charset=utf-8');
$action  = $_GET['action'] ?? $_POST['action'] ?? '';
$reviews = new Review();

switch ($action) {

    // ----------------------------------------------------------
    // Soumettre un avis (passager d'un trajet terminé)
    // ----------------------------------------------------------
    case 'submit':
        $session = requireAuth();
        $result  = $reviews->submit(
            (int)($_POST['trip_id'] ?? 0),
            (int)$session['user_id'],
            (int)($_POST['rating'] ?? 0),
            sanitize($_POST['comment'] ?? '')
        );
        jsonResponse($result['success'], $result['message']);
        break;

    // ----------------------------------------------------------
    // Avis en attente (employé)
    // ----------------------------------------------------------
    case 'pending':
        requireRole('employee');
        jsonResponse(true, '', ['reviews' => $reviews->getPending()]);
        break;

    // ----------------------------------------------------------
    // Valider ou refuser un avis (employé)
    // ----------------------------------------------------------
    case 'moderate':
        requireRole('employee');
        $decision = $_POST['decision'] ?? '';
        if (!$reviews->moderate((int)($_POST['review_id'] ?? 0), $decision)) {
            jsonResponse(false, 'Décision invalide.');
        }
        jsonResponse(true, 'Avis ' . ($decision === 'approved' ? 'validé' : 'refusé') . '.');
        break;

    // ----------------------------------------------------------
    // Avis d'un conducteur (public)
    // ----------------------------------------------------------
    case 'driver':
        $driverId = (int)($_GET['driver_id'] ?? 0);
        if (!$driverId) jsonResponse(false, 'ID manquant.');
        jsonResponse(true, '', ['reviews' => $reviews->getByDriver($driverId)]);
        break;

    default:
        jsonResponse(false, 'Action non reconnue.');
}
