<?php
// ============================================================
// EcoRide - Statistiques MongoDB (NoSQL)
// Point d'entrée de l'API : le contrôleur StatsController fait le reste.
// ============================================================
require_once __DIR__ . '/config.php';

(new StatsController())->handle($_GET['action'] ?? $_POST['action'] ?? '');
