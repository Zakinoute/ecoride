<?php
// ============================================================
// EcoRide - Véhicules et préférences du chauffeur
// Point d'entrée de l'API : le contrôleur VehicleController fait le reste.
// ============================================================
require_once __DIR__ . '/config.php';

(new VehicleController())->handle($_GET['action'] ?? $_POST['action'] ?? '');
