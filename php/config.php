<?php
// ============================================================
// EcoRide - Chargement automatique des classes
// ============================================================
// Architecture MVC avec le pattern Repository :
//   controllers/   lisent la requête HTTP et renvoient du JSON (le « C »)
//   models/        contiennent les règles métier (le « M »)
//   repositories/  contiennent toutes les requêtes SQL et MongoDB
//   core/          connexion à la base et classes de base
// La vue (le « V ») est la partie front-end : les pages HTML et le
// JavaScript qui affichent les données JSON.
//
// `new TripController()` charge controllers/TripController.php
// la première fois que la classe est utilisée.
spl_autoload_register(function (string $class): void {
    foreach (['core', 'models', 'repositories', 'controllers'] as $folder) {
        $file = __DIR__ . "/{$folder}/{$class}.php";
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
