<?php
// ============================================================
// EcoRide - Authentification (login / register / logout / me)
// Contrôleur : la logique des comptes est dans la classe User.
// ============================================================
require_once 'config.php';
startSession();

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    case 'register':
        $pseudo = sanitize($_POST['pseudo'] ?? '');
        $email  = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

        $result = (new User())->register($pseudo, $email, $_POST['password'] ?? '');
        if (!$result['success']) jsonResponse(false, $result['message']);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $result['id'];
        $_SESSION['pseudo']  = $pseudo;
        $_SESSION['role']    = 'user';
        $_SESSION['credits'] = 20;

        jsonResponse(true, $result['message'], ['redirect' => 'user/dashboard.html']);
        break;

    // ----------------------------------------------------------
    case 'login':
        $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            jsonResponse(false, 'Email et mot de passe requis.');
        }

        $result = (new User())->login($email, $password);
        if (!$result['success']) jsonResponse(false, $result['message']);
        $user = $result['user'];

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['pseudo']  = $user['pseudo'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['credits'] = $user['credits'];

        $redirect = match($user['role']) {
            'admin'    => 'admin/dashboard.html',
            'employee' => 'employee/dashboard.html',
            default    => 'user/dashboard.html',
        };

        jsonResponse(true, 'Connexion réussie.', ['redirect' => $redirect, 'user' => [
            'pseudo'  => $user['pseudo'],
            'role'    => $user['role'],
            'credits' => $user['credits'],
        ]]);
        break;

    // ----------------------------------------------------------
    case 'logout':
        session_destroy();
        jsonResponse(true, 'Déconnexion réussie.', ['redirect' => '../index.html']);
        break;

    // ----------------------------------------------------------
    case 'me':
        if (empty($_SESSION['user_id'])) {
            jsonResponse(false, 'Non connecté.');
        }
        jsonResponse(true, '', ['user' => (new User())->findById((int)$_SESSION['user_id'])]);
        break;

    // ----------------------------------------------------------
    default:
        jsonResponse(false, 'Action non reconnue.');
}
