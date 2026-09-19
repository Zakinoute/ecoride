<?php
// ============================================================
// EcoRide - Espace employé
// Point d'entrée de l'API : le contrôleur EmployeeController fait le reste.
// ============================================================
require_once __DIR__ . '/config.php';

(new EmployeeController())->handle($_GET['action'] ?? $_POST['action'] ?? '');
