<?php
// ============================================================
// EcoRide - Avis (dépôt, modération, affichage)
// Point d'entrée de l'API : le contrôleur ReviewController fait le reste.
// ============================================================
require_once __DIR__ . '/config.php';

(new ReviewController())->handle($_GET['action'] ?? $_POST['action'] ?? '');
