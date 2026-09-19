<?php
/**
 * VehicleController — véhicules, préférences et statut chauffeur / passager.
 */
class VehicleController extends Controller
{
    protected const ACTIONS = [
        'list'             => 'list',
        'add'              => 'add',
        'remove'           => 'remove',
        'get_preferences'  => 'getPreferences',
        'save_preferences' => 'savePreferences',
        'update_status'    => 'updateStatus',
    ];

    public function __construct(
        private Vehicle $vehicles = new Vehicle(),
        private User $users = new User(),
    ) {
        parent::__construct();
    }

    protected function list(): void
    {
        $this->json(true, '', ['vehicles' => $this->vehicles->forUser($this->requireAuth())]);
    }

    protected function add(): void
    {
        $vehicleId = $this->vehicles->add($this->requireAuth(), [
            'plate'              => strtoupper($this->text('plate')),
            'first_registration' => $this->raw('first_registration'),
            'brand'              => $this->text('brand'),
            'model'              => $this->text('model'),
            'color'              => $this->text('color'),
            'seats'              => $this->int('seats'),
            'energy'             => $this->raw('energy'),
        ]);
        $this->json(true, 'Véhicule ajouté avec succès !', ['vehicle_id' => $vehicleId]);
    }

    protected function remove(): void
    {
        $this->vehicles->remove($this->int('vehicle_id'), $this->requireAuth());
        $this->json(true, 'Véhicule supprimé.');
    }

    protected function getPreferences(): void
    {
        $this->json(true, '', ['preferences' => $this->users->preferences($this->requireAuth())]);
    }

    protected function savePreferences(): void
    {
        $this->users->savePreferences($this->requireAuth(), [
            'smoking'           => $this->checked('smoking'),
            'animals'           => $this->checked('animals'),
            'music'             => $this->checked('music'),
            'chat'              => $this->checked('chat'),
            'other_preferences' => $this->text('other_preferences'),
        ]);
        $this->json(true, 'Préférences enregistrées.');
    }

    protected function updateStatus(): void
    {
        $this->users->setRoles($this->requireAuth(), $this->checked('is_driver'), $this->checked('is_passenger'));
        $this->json(true, 'Statut mis à jour.');
    }
}
