<?php
// ============================================================
// EcoRide - Gestion des trajets
// Contrôleur : lit la requête, appelle les classes Trip et Booking,
// renvoie la réponse en JSON.
// ============================================================
require_once 'config.php';
startSession();

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$trips  = new Trip();

switch ($action) {

    // ----------------------------------------------------------
    // Recherche publique de trajets
    // ----------------------------------------------------------
    case 'search':
        $results = $trips->search([
            'departure'  => sanitize($_GET['departure'] ?? ''),
            'arrival'    => sanitize($_GET['arrival'] ?? ''),
            'date'       => $_GET['date'] ?? '',
            'max_price'  => isset($_GET['max_price'])  ? (float)$_GET['max_price']  : null,
            'min_rating' => isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : null,
            'eco'        => !empty($_GET['eco']),
            'sort'       => $_GET['sort'] ?? 'departure_datetime',
        ]);
        jsonResponse(true, '', ['trips' => $results]);
        break;

    // ----------------------------------------------------------
    // Détail d'un trajet et avis du conducteur
    // ----------------------------------------------------------
    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(false, 'ID manquant.');

        $trip = $trips->findById($id);
        if (!$trip) jsonResponse(false, 'Trajet introuvable.');

        $reviews = (new Review())->getByDriver((int)$trip['driver_id'], 10);
        jsonResponse(true, '', ['trip' => $trip, 'reviews' => $reviews]);
        break;

    // ----------------------------------------------------------
    // Publier un trajet (chauffeur connecté)
    // ----------------------------------------------------------
    case 'create':
        $session = requireAuth();
        $result  = $trips->create((int)$session['user_id'], [
            'departure_address'  => sanitize($_POST['departure_address'] ?? ''),
            'departure_city'     => sanitize($_POST['departure_city'] ?? ''),
            'arrival_address'    => sanitize($_POST['arrival_address'] ?? ''),
            'arrival_city'       => sanitize($_POST['arrival_city'] ?? ''),
            'departure_datetime' => $_POST['departure_datetime'] ?? '',
            'arrival_datetime'   => $_POST['arrival_datetime'] ?? '',
            'price'              => (int)($_POST['price'] ?? 0),   // les crédits sont des entiers
            'seats'              => (int)($_POST['seats'] ?? 1),
            'vehicle_id'         => (int)($_POST['vehicle_id'] ?? 0),
        ]);
        jsonResponse($result['success'], $result['message'], isset($result['trip_id']) ? ['trip_id' => $result['trip_id']] : []);
        break;

    // ----------------------------------------------------------
    // Réserver un trajet (passager connecté)
    // ----------------------------------------------------------
    case 'book':
        $session = requireAuth();
        $tripId  = (int)($_POST['trip_id'] ?? 0);
        if (!$tripId) jsonResponse(false, 'ID de trajet manquant.');

        $result = (new Booking())->book($tripId, (int)$session['user_id']);
        if (!$result['success']) jsonResponse(false, $result['message']);

        $_SESSION['credits'] = $result['new_credits'];
        jsonResponse(true, $result['message'], ['new_credits' => $result['new_credits']]);
        break;

    // ----------------------------------------------------------
    // Démarrer un trajet
    // ----------------------------------------------------------
    case 'start':
        $session = requireAuth();
        if (!$trips->start((int)($_POST['trip_id'] ?? 0), (int)$session['user_id'])) {
            jsonResponse(false, 'Impossible de démarrer ce trajet.');
        }
        jsonResponse(true, 'Trajet démarré !');
        break;

    // ----------------------------------------------------------
    // Terminer un trajet & créditer le chauffeur
    // ----------------------------------------------------------
    case 'complete':
        $session = requireAuth();
        $result  = $trips->complete((int)($_POST['trip_id'] ?? 0), (int)$session['user_id']);
        if (!$result['success']) jsonResponse(false, $result['message']);

        $_SESSION['credits'] = ($_SESSION['credits'] ?? 0) + $result['earned'];
        jsonResponse(true, $result['message'], ['earned' => $result['earned'], 'new_credits' => $_SESSION['credits']]);
        break;

    // ----------------------------------------------------------
    // Annuler un trajet (les passagers sont remboursés)
    // ----------------------------------------------------------
    case 'cancel':
        $session = requireAuth();
        $result  = $trips->cancel((int)($_POST['trip_id'] ?? 0), (int)$session['user_id']);
        jsonResponse($result['success'], $result['message']);
        break;

    // ----------------------------------------------------------
    // Mes trajets (conducteur + passager)
    // ----------------------------------------------------------
    case 'my_trips':
        $session = requireAuth();
        jsonResponse(true, '', ['trips' => $trips->findByUser((int)$session['user_id'])]);
        break;

    // ----------------------------------------------------------
    // Signaler un trajet mal passé
    // ----------------------------------------------------------
    case 'report':
        requireAuth();
        $tripId = (int)($_POST['trip_id'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        if (!$tripId || !$reason) jsonResponse(false, 'Données manquantes.');

        $trips->report($tripId, $reason);
        jsonResponse(true, 'Signalement envoyé.');
        break;

    default:
        jsonResponse(false, 'Action non reconnue.');
}
