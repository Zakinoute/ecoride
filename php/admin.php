<?php
// ============================================================
// EcoRide - Espace administrateur
// Point d'entrée de l'API : le contrôleur AdminController fait le reste.
// ============================================================
require_once __DIR__ . '/config.php';

(new AdminController())->handle($_GET['action'] ?? $_POST['action'] ?? '');
