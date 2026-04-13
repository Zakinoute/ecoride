<?php
// ============================================================
// EcoRide - Gestion des véhicules & préférences
// ============================================================
require_once 'config.php';
startSession();

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // Lister les véhicules de l'utilisateur connecté
    // ----------------------------------------------------------
    case 'list':
        $session = requireAuth();
        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT * FROM vehicles WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC');
        $stmt->execute([$session['user_id']]);
        jsonResponse(true, '', ['vehicles' => $stmt->fetchAll()]);
        break;

    // ----------------------------------------------------------
    // Ajouter un véhicule
    // ----------------------------------------------------------
    case 'add':
        $session = requireAuth();
        $plate    = strtoupper(sanitize($_POST['plate'] ?? ''));
        $firstReg = $_POST['first_registration'] ?? '';
        $brand    = sanitize($_POST['brand'] ?? '');
        $model    = sanitize($_POST['model'] ?? '');
        $color    = sanitize($_POST['color'] ?? '');
        $seats    = (int)($_POST['seats'] ?? 0);
        $energy   = $_POST['energy'] ?? '';

        if (!$plate || !$firstReg || !$brand || !$model || !$color || $seats < 2 || !$energy) {
            jsonResponse(false, 'Tous les champs sont obligatoires.');
        }
        $allowed_energies = ['essence', 'diesel', 'electrique', 'hybride'];
        if (!in_array($energy, $allowed_energies)) jsonResponse(false, 'Type d\'énergie invalide.');

        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT id FROM vehicles WHERE plate = ?');
        $stmt->execute([$plate]);
        if ($stmt->fetch()) jsonResponse(false, 'Cette plaque est déjà enregistrée.');

        $pdo->prepare('INSERT INTO vehicles (user_id, plate, first_registration, brand, model, color, seats, energy) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$session['user_id'], $plate, $firstReg, $brand, $model, $color, $seats, $energy]);

        // S'assurer que l'utilisateur est marqué chauffeur
        $pdo->prepare('UPDATE users SET is_driver = 1 WHERE id = ?')->execute([$session['user_id']]);

        jsonResponse(true, 'Véhicule ajouté avec succès !', ['vehicle_id' => $pdo->lastInsertId()]);
        break;

    // ----------------------------------------------------------
    // Supprimer (désactiver) un véhicule
    // ----------------------------------------------------------
    case 'remove':
        $session   = requireAuth();
        $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
        $pdo       = getPDO();

        // Vérifier que le véhicule n'est pas lié à un trajet actif
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM trips WHERE vehicle_id = ? AND status IN ('active','started')");
        $stmt->execute([$vehicleId]);
        if ($stmt->fetchColumn() > 0) jsonResponse(false, 'Ce véhicule est associé à un trajet en cours. Annulez le trajet avant de supprimer le véhicule.');

        $pdo->prepare('UPDATE vehicles SET is_active = 0 WHERE id = ? AND user_id = ?')->execute([$vehicleId, $session['user_id']]);
        jsonResponse(true, 'Véhicule supprimé.');
        break;

    // ----------------------------------------------------------
    // Obtenir / mettre à jour les préférences chauffeur
    // ----------------------------------------------------------
    case 'get_preferences':
        $session = requireAuth();
        $pdo     = getPDO();
        $stmt    = $pdo->prepare('SELECT * FROM driver_preferences WHERE user_id = ?');
        $stmt->execute([$session['user_id']]);
        $prefs = $stmt->fetch() ?: ['smoking' => 0, 'animals' => 0, 'music' => 1, 'chat' => 1, 'other_preferences' => ''];
        jsonResponse(true, '', ['preferences' => $prefs]);
        break;

    case 'save_preferences':
        $session = requireAuth();
        $smoking = isset($_POST['smoking']) ? 1 : 0;
        $animals = isset($_POST['animals']) ? 1 : 0;
        $music   = isset($_POST['music'])   ? 1 : 0;
        $chat    = isset($_POST['chat'])    ? 1 : 0;
        $other   = sanitize($_POST['other_preferences'] ?? '');
        $pdo     = getPDO();

        $pdo->prepare("
            INSERT INTO driver_preferences (user_id, smoking, animals, music, chat, other_preferences)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE smoking=?, animals=?, music=?, chat=?, other_preferences=?
        ")->execute([$session['user_id'], $smoking, $animals, $music, $chat, $other,
                     $smoking, $animals, $music, $chat, $other]);
        jsonResponse(true, 'Préférences enregistrées.');
        break;

    // ----------------------------------------------------------
    // Mettre à jour le statut chauffeur/passager
    // ----------------------------------------------------------
    case 'update_status':
        $session    = requireAuth();
        $isDriver   = isset($_POST['is_driver'])    ? 1 : 0;
        $isPassenger= isset($_POST['is_passenger']) ? 1 : 0;
        if (!$isDriver && !$isPassenger) jsonResponse(false, 'Vous devez choisir au moins un statut.');
        $pdo = getPDO();
        $pdo->prepare('UPDATE users SET is_driver = ?, is_passenger = ? WHERE id = ?')->execute([$isDriver, $isPassenger, $session['user_id']]);
        jsonResponse(true, 'Statut mis à jour.');
        break;

    default:
        jsonResponse(false, 'Action non reconnue.');
}
