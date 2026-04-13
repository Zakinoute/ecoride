<?php
// ============================================================
// EcoRide - Authentification (login / register / logout / me)
// ============================================================
require_once 'config.php';
startSession();

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    case 'register':
        $pseudo   = sanitize($_POST['pseudo'] ?? '');
        $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$pseudo || !$email || !$password) {
            jsonResponse(false, 'Tous les champs sont obligatoires.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'Adresse email invalide.');
        }
        // Politique de mot de passe : 8 car., 1 maj, 1 chiffre, 1 spécial
        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
            jsonResponse(false, 'Mot de passe trop faible. Il doit contenir au moins 8 caractères, une majuscule, un chiffre et un caractère spécial.');
        }

        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? OR pseudo = ?');
        $stmt->execute([$email, $pseudo]);
        if ($stmt->fetch()) {
            jsonResponse(false, 'Ce pseudo ou email est déjà utilisé.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (pseudo, email, password) VALUES (?, ?, ?)');
        $stmt->execute([$pseudo, $email, $hash]);
        $userId = $pdo->lastInsertId();

        $_SESSION['user_id'] = $userId;
        $_SESSION['pseudo']  = $pseudo;
        $_SESSION['role']    = 'user';
        $_SESSION['credits'] = 20;

        jsonResponse(true, 'Compte créé avec succès ! 20 crédits offerts.', ['redirect' => 'user/dashboard.html']);
        break;

    // ----------------------------------------------------------
    case 'login':
        $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            jsonResponse(false, 'Email et mot de passe requis.');
        }

        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT id, pseudo, password, role, credits, status FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            jsonResponse(false, 'Identifiants incorrects.');
        }
        if ($user['status'] === 'suspended') {
            jsonResponse(false, 'Votre compte est suspendu. Contactez l\'administration.');
        }

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
        $pdo  = getPDO();
        $stmt = $pdo->prepare('SELECT id, pseudo, email, role, credits, photo, is_driver, is_passenger FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        jsonResponse(true, '', ['user' => $user]);
        break;

    // ----------------------------------------------------------
    default:
        jsonResponse(false, 'Action non reconnue.');
}
