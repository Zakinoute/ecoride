<?php
// ============================================================
// EcoRide - Trajets (recherche, publication, réservation, cycle de vie)
// Point d'entrée de l'API : le contrôleur TripController fait le reste.
// ============================================================
require_once __DIR__ . '/config.php';

(new TripController())->handle($_GET['action'] ?? $_POST['action'] ?? '');
