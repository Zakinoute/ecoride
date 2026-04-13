<?php
// ============================================================
// EcoRide - Gestion des trajets
// ============================================================
require_once 'config.php';
startSession();

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // Recherche publique de trajets
    // ----------------------------------------------------------
    case 'search':
        $departure = sanitize($_GET['departure'] ?? '');
        $arrival   = sanitize($_GET['arrival']   ?? '');
        $date      = $_GET['date'] ?? '';
        $maxPrice  = isset($_GET['max_price'])  ? (float)$_GET['max_price']  : null;
        $minRating = isset($_GET['min_rating']) ? (float)$_GET['min_rating'] : null;
        $ecoOnly   = isset($_GET['eco'])        ? (bool)$_GET['eco']         : false;
        $sortBy    = $_GET['sort'] ?? 'departure_datetime';

        $allowed_sorts = ['departure_datetime', 'price', 'available_seats'];
        if (!in_array($sortBy, $allowed_sorts)) $sortBy = 'departure_datetime';

        $sql = "
            SELECT t.*, u.pseudo AS driver_pseudo, u.photo AS driver_photo,
                   COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS review_count,
                   v.brand, v.model, v.energy, v.color
            FROM trips t
            JOIN users u ON u.id = t.driver_id
            JOIN vehicles v ON v.id = t.vehicle_id
            LEFT JOIN reviews r ON r.reviewed_id = t.driver_id AND r.status = 'approved'
            WHERE t.status = 'active'
              AND t.available_seats > 0
              AND t.departure_datetime >= NOW()
        ";
        $params = [];

        if ($departure) {
            $sql .= " AND t.departure_city LIKE ?";
            $params[] = "%$departure%";
        }
        if ($arrival) {
            $sql .= " AND t.arrival_city LIKE ?";
            $params[] = "%$arrival%";
        }
        if ($date) {
            $sql .= " AND DATE(t.departure_datetime) = ?";
            $params[] = $date;
        }
        if ($maxPrice !== null) {
            $sql .= " AND t.price <= ?";
            $params[] = $maxPrice;
        }
        if ($ecoOnly) {
            $sql .= " AND v.energy = 'electrique'";
        }

        $sql .= " GROUP BY t.id";

        if ($minRating !== null) {
            $sql .= " HAVING avg_rating >= ?";
            $params[] = $minRating;
        }

        $sql .= " ORDER BY $sortBy ASC";

        $pdo  = getPDO();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trips = $stmt->fetchAll();

        foreach ($trips as &$trip) {
            $trip['is_eco']     = $trip['energy'] === 'electrique';
            $trip['avg_rating'] = round((float)$trip['avg_rating'], 1);
        }

        jsonResponse(true, '', ['trips' => $trips]);
        break;

    // ----------------------------------------------------------
    // Détail d'un trajet
    // ----------------------------------------------------------
    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(false, 'ID manquant.');

        $pdo  = getPDO();
        $stmt = $pdo->prepare("
            SELECT t.*, u.pseudo AS driver_pseudo, u.photo AS driver_photo,
                   COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS review_count,
                   v.brand, v.model, v.energy, v.color, v.seats AS total_vehicle_seats,
                   dp.smoking, dp.animals, dp.music, dp.chat, dp.other_preferences
            FROM trips t
            JOIN users u ON u.id = t.driver_id
            JOIN vehicles v ON v.id = t.vehicle_id
            LEFT JOIN driver_preferences dp ON dp.user_id = t.driver_id
            LEFT JOIN reviews r ON r.reviewed_id = t.driver_id AND r.status = 'approved'
            WHERE t.id = ?
            GROUP BY t.id
        ");
        $stmt->execute([$id]);
        $trip = $stmt->fetch();
        if (!$trip) jsonResponse(false, 'Trajet introuvable.');

        // Récupérer les avis du conducteur
        $stmt2 = $pdo->prepare("
            SELECT r.rating, r.comment, r.created_at, u.pseudo AS reviewer_pseudo, u.photo AS reviewer_photo
            FROM reviews r
            JOIN users u ON u.id = r.reviewer_id
            WHERE r.reviewed_id = ? AND r.status = 'approved'
            ORDER BY r.created_at DESC LIMIT 10
        ");
        $stmt2->execute([$trip['driver_id']]);
        $reviews = $stmt2->fetchAll();

        $trip['is_eco']     = $trip['energy'] === 'electrique';
        $trip['avg_rating'] = round((float)$trip['avg_rating'], 1);

        jsonResponse(true, '', ['trip' => $trip, 'reviews' => $reviews]);
        break;

    // ----------------------------------------------------------
    // Publier un trajet (chauffeur connecté)
    // ----------------------------------------------------------
    case 'create':
        $session = requireAuth();
        $pdo     = getPDO();

        // Vérifier que l'utilisateur est chauffeur
        $stmt = $pdo->prepare('SELECT is_driver FROM users WHERE id = ?');
        $stmt->execute([$session['user_id']]);
        $u = $stmt->fetch();
        if (!$u['is_driver']) jsonResponse(false, 'Vous devez être enregistré comme chauffeur.');

        $depAddr  = sanitize($_POST['departure_address'] ?? '');
        $depCity  = sanitize($_POST['departure_city'] ?? '');
        $arrAddr  = sanitize($_POST['arrival_address'] ?? '');
        $arrCity  = sanitize($_POST['arrival_city'] ?? '');
        $depDt    = $_POST['departure_datetime'] ?? '';
        $arrDt    = $_POST['arrival_datetime'] ?? '';
        $price    = (float)($_POST['price'] ?? 0);
        $seats    = (int)($_POST['seats'] ?? 1);
        $vehicleId = (int)($_POST['vehicle_id'] ?? 0);

        if (!$depAddr || !$depCity || !$arrAddr || !$arrCity || !$depDt || !$vehicleId || $price <= 0 || $seats < 1) {
            jsonResponse(false, 'Tous les champs sont obligatoires.');
        }

        // Vérifier que le véhicule appartient au chauffeur
        $stmt = $pdo->prepare('SELECT id, seats FROM vehicles WHERE id = ? AND user_id = ? AND is_active = 1');
        $stmt->execute([$vehicleId, $session['user_id']]);
        $vehicle = $stmt->fetch();
        if (!$vehicle) jsonResponse(false, 'Véhicule invalide.');
        if ($seats > $vehicle['seats'] - 1) jsonResponse(false, 'Nombre de places trop élevé pour ce véhicule.');

        $stmt = $pdo->prepare("
            INSERT INTO trips (driver_id, vehicle_id, departure_address, departure_city, arrival_address, arrival_city, departure_datetime, arrival_datetime, price, available_seats, total_seats)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$session['user_id'], $vehicleId, $depAddr, $depCity, $arrAddr, $arrCity, $depDt, $arrDt ?: null, $price, $seats, $seats]);

        jsonResponse(true, 'Trajet publié avec succès !', ['trip_id' => $pdo->lastInsertId()]);
        break;

    // ----------------------------------------------------------
    // Réserver un trajet (passager connecté)
    // ----------------------------------------------------------
    case 'book':
        $session = requireAuth();
        $tripId  = (int)($_POST['trip_id'] ?? 0);
        if (!$tripId) jsonResponse(false, 'ID de trajet manquant.');

        $pdo  = getPDO();
        $pdo->beginTransaction();
        try {
            // Verrouiller la ligne du trajet
            $stmt = $pdo->prepare('SELECT * FROM trips WHERE id = ? AND status = "active" AND available_seats > 0 FOR UPDATE');
            $stmt->execute([$tripId]);
            $trip = $stmt->fetch();
            if (!$trip) {
                $pdo->rollBack();
                jsonResponse(false, 'Trajet indisponible.');
            }
            if ($trip['driver_id'] === $session['user_id']) {
                $pdo->rollBack();
                jsonResponse(false, 'Vous ne pouvez pas réserver votre propre trajet.');
            }

            // Vérifier crédits passager
            $stmt = $pdo->prepare('SELECT credits FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$session['user_id']]);
            $passenger = $stmt->fetch();
            $cost = (int)$trip['price'];
            if ($passenger['credits'] < $cost) {
                $pdo->rollBack();
                jsonResponse(false, 'Crédits insuffisants.');
            }

            // Vérifier doublon
            $stmt = $pdo->prepare('SELECT id FROM bookings WHERE trip_id = ? AND passenger_id = ?');
            $stmt->execute([$tripId, $session['user_id']]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                jsonResponse(false, 'Vous avez déjà réservé ce trajet.');
            }

            // Débiter passager
            $pdo->prepare('UPDATE users SET credits = credits - ? WHERE id = ?')->execute([$cost, $session['user_id']]);
            // Créer réservation
            $pdo->prepare('INSERT INTO bookings (trip_id, passenger_id, credits_used) VALUES (?, ?, ?)')->execute([$tripId, $session['user_id'], $cost]);
            $bookingId = $pdo->lastInsertId();
            // Diminuer places
            $pdo->prepare('UPDATE trips SET available_seats = available_seats - 1 WHERE id = ?')->execute([$tripId]);
            // Commission plateforme (2 crédits)
            $pdo->prepare('INSERT INTO platform_credits (trip_id, booking_id, amount) VALUES (?, ?, ?)')->execute([$tripId, $bookingId, PLATFORM_FEE]);

            $pdo->commit();
            $_SESSION['credits'] = $passenger['credits'] - $cost;

            jsonResponse(true, 'Réservation confirmée !', ['new_credits' => $_SESSION['credits']]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Erreur lors de la réservation.');
        }
        break;

    // ----------------------------------------------------------
    // Démarrer un trajet
    // ----------------------------------------------------------
    case 'start':
        $session = requireAuth();
        $tripId  = (int)($_POST['trip_id'] ?? 0);
        $pdo     = getPDO();
        $stmt    = $pdo->prepare('UPDATE trips SET status = "started" WHERE id = ? AND driver_id = ? AND status = "active"');
        $stmt->execute([$tripId, $session['user_id']]);
        if ($stmt->rowCount() === 0) jsonResponse(false, 'Impossible de démarrer ce trajet.');
        jsonResponse(true, 'Trajet démarré !');
        break;

    // ----------------------------------------------------------
    // Terminer un trajet & créditer le chauffeur
    // ----------------------------------------------------------
    case 'complete':
        $session = requireAuth();
        $tripId  = (int)($_POST['trip_id'] ?? 0);
        $pdo     = getPDO();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM trips WHERE id = ? AND driver_id = ? AND status = "started" FOR UPDATE');
            $stmt->execute([$tripId, $session['user_id']]);
            $trip = $stmt->fetch();
            if (!$trip) { $pdo->rollBack(); jsonResponse(false, 'Trajet non trouvé.'); }

            // Calculer gain chauffeur = total réservations - commission plateforme par réservation
            $stmt = $pdo->prepare('SELECT SUM(credits_used) AS total FROM bookings WHERE trip_id = ? AND status = "confirmed"');
            $stmt->execute([$tripId]);
            $totals = $stmt->fetch();
            $nbBookings = $trip['total_seats'] - $trip['available_seats'];
            $driverEarning = ($totals['total'] ?? 0) - (PLATFORM_FEE * $nbBookings);

            $pdo->prepare('UPDATE trips SET status = "completed" WHERE id = ?')->execute([$tripId]);
            if ($driverEarning > 0) {
                $pdo->prepare('UPDATE users SET credits = credits + ? WHERE id = ?')->execute([$driverEarning, $session['user_id']]);
                $_SESSION['credits'] = ($_SESSION['credits'] ?? 0) + $driverEarning;
            }
            $pdo->commit();
            jsonResponse(true, 'Trajet terminé ! Crédits crédités.', ['earned' => $driverEarning, 'new_credits' => $_SESSION['credits']]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Erreur.');
        }
        break;

    // ----------------------------------------------------------
    // Annuler un trajet
    // ----------------------------------------------------------
    case 'cancel':
        $session = requireAuth();
        $tripId  = (int)($_POST['trip_id'] ?? 0);
        $pdo     = getPDO();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM trips WHERE id = ? AND driver_id = ? AND status = "active" FOR UPDATE');
            $stmt->execute([$tripId, $session['user_id']]);
            $trip = $stmt->fetch();
            if (!$trip) { $pdo->rollBack(); jsonResponse(false, 'Trajet introuvable ou déjà commencé.'); }

            // Rembourser les passagers
            $stmt = $pdo->prepare('SELECT * FROM bookings WHERE trip_id = ? AND status = "confirmed"');
            $stmt->execute([$tripId]);
            $bookings = $stmt->fetchAll();
            foreach ($bookings as $b) {
                $pdo->prepare('UPDATE users SET credits = credits + ? WHERE id = ?')->execute([$b['credits_used'], $b['passenger_id']]);
                $pdo->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ?')->execute([$b['id']]);
            }
            $pdo->prepare('UPDATE trips SET status = "cancelled" WHERE id = ?')->execute([$tripId]);
            $pdo->commit();
            jsonResponse(true, 'Trajet annulé. Les passagers ont été remboursés.');
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Erreur lors de l\'annulation.');
        }
        break;

    // ----------------------------------------------------------
    // Mes trajets (conducteur + passager)
    // ----------------------------------------------------------
    case 'my_trips':
        $session = requireAuth();
        $pdo     = getPDO();

        $stmt = $pdo->prepare("
            SELECT t.*, 'driver' AS my_role,
                   v.brand, v.model, v.energy,
                   (SELECT COUNT(*) FROM bookings WHERE trip_id = t.id AND status = 'confirmed') AS booked_count
            FROM trips t
            JOIN vehicles v ON v.id = t.vehicle_id
            WHERE t.driver_id = ?
            UNION ALL
            SELECT t.*, 'passenger' AS my_role,
                   v.brand, v.model, v.energy,
                   (SELECT COUNT(*) FROM bookings WHERE trip_id = t.id AND status = 'confirmed') AS booked_count
            FROM trips t
            JOIN vehicles v ON v.id = t.vehicle_id
            JOIN bookings b ON b.trip_id = t.id AND b.passenger_id = ?
            ORDER BY departure_datetime DESC
        ");
        $stmt->execute([$session['user_id'], $session['user_id']]);
        $trips = $stmt->fetchAll();
        jsonResponse(true, '', ['trips' => $trips]);
        break;

    // ----------------------------------------------------------
    // Signaler un trajet mal passé
    // ----------------------------------------------------------
    case 'report':
        $session = requireAuth();
        $tripId  = (int)($_POST['trip_id'] ?? 0);
        $reason  = sanitize($_POST['reason'] ?? '');
        if (!$tripId || !$reason) jsonResponse(false, 'Données manquantes.');
        $pdo = getPDO();
        $pdo->prepare('UPDATE trips SET is_reported = 1, report_reason = ? WHERE id = ?')->execute([$reason, $tripId]);
        jsonResponse(true, 'Signalement envoyé.');
        break;

    default:
        jsonResponse(false, 'Action non reconnue.');
}
