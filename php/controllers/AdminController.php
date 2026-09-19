<?php
/**
 * AdminController — espace administrateur : comptes employés, comptes utilisateurs, statistiques MySQL.
 */
class AdminController extends Controller
{
    protected const ACTIONS = [
        'create_employee' => 'createEmployee',
        'stats'           => 'stats',
        'users'           => 'users',
        'toggle_status'   => 'toggleStatus',
    ];

    public function __construct(
        private User $users = new User(),
        private Statistics $statistics = new Statistics(),
    ) {
        parent::__construct();
    }

    protected function createEmployee(): void
    {
        $this->requireRole('admin');
        $employeeId = $this->users->createEmployee($this->text('pseudo'), $this->email('email'), $this->raw('password'));
        $this->json(true, 'Compte employé créé.', ['employee_id' => $employeeId]);
    }

    protected function stats(): void
    {
        $this->requireRole('admin');
        $this->json(true, '', $this->statistics->adminDashboard());
    }

    protected function users(): void
    {
        $this->requireRole('admin');
        $this->json(true, '', ['users' => $this->users->list($this->text('search'), $this->raw('role'))]);
    }

    protected function toggleStatus(): void
    {
        $this->requireRole('admin');
        $newStatus = $this->users->toggleStatus($this->int('user_id'));
        $this->json(true, 'Compte ' . ($newStatus === 'suspended' ? 'suspendu' : 'réactivé') . '.', ['new_status' => $newStatus]);
    }
}
