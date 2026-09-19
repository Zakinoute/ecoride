<?php
// ============================================================
// EcoRide - Authentification (inscription, connexion, déconnexion, profil)
// Point d'entrée de l'API : le contrôleur AuthController fait le reste.
// ============================================================
require_once __DIR__ . '/config.php';

(new AuthController())->handle($_GET['action'] ?? $_POST['action'] ?? '');
