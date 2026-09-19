<?php
/**
 * AuthController — inscription, connexion, déconnexion, profil connecté.
 */
class AuthController extends Controller
{
    protected const ACTIONS = [
        'register' => 'register',
        'login'    => 'login',
        'logout'   => 'logout',
        'me'       => 'me',
    ];

    public function __construct(private User $users = new User())
    {
        parent::__construct();
    }

    protected function register(): void
    {
        $pseudo = $this->text('pseudo');
        $userId = $this->users->register($pseudo, $this->email('email'), $this->raw('password'));

        // Nouvel identifiant de session à chaque connexion : contre la fixation de session.
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['pseudo']  = $pseudo;
        $_SESSION['role']    = 'user';
        $_SESSION['credits'] = 20;

        $this->json(true, 'Compte créé avec succès ! 20 crédits offerts.', ['redirect' => 'user/dashboard.html']);
    }

    protected function login(): void
    {
        $user = $this->users->login($this->email('email'), $this->raw('password'));

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['pseudo']  = $user['pseudo'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['credits'] = $user['credits'];

        $redirect = match ($user['role']) {
            'admin'    => 'admin/dashboard.html',
            'employee' => 'employee/dashboard.html',
            default    => 'user/dashboard.html',
        };

        $this->json(true, 'Connexion réussie.', ['redirect' => $redirect, 'user' => [
            'pseudo'  => $user['pseudo'],
            'role'    => $user['role'],
            'credits' => $user['credits'],
        ]]);
    }

    protected function logout(): void
    {
        session_destroy();
        $this->json(true, 'Déconnexion réussie.', ['redirect' => '../index.html']);
    }

    protected function me(): void
    {
        if (empty($_SESSION['user_id'])) {
            $this->json(false, 'Non connecté.');
        }
        $this->json(true, '', ['user' => $this->users->profile((int) $_SESSION['user_id'])]);
    }
}
