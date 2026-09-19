<?php
/**
 * Vehicle — règles des véhicules des chauffeurs.
 */
class Vehicle
{
    private const ENERGIES = ['essence', 'diesel', 'electrique', 'hybride'];

    public function __construct(
        private VehicleRepository $vehicles = new VehicleRepository(),
        private UserRepository $users = new UserRepository(),
        private TripRepository $trips = new TripRepository(),
    ) {}

    public function forUser(int $userId): array
    {
        return $this->vehicles->findActiveByUser($userId);
    }

    /** Ajoute un véhicule ; son propriétaire devient chauffeur. Renvoie l'id du véhicule. */
    public function add(int $userId, array $vehicle): int
    {
        if (!$vehicle['plate'] || !$vehicle['first_registration'] || !$vehicle['brand'] || !$vehicle['model']
            || !$vehicle['color'] || $vehicle['seats'] < 2 || !$vehicle['energy']) {
            throw new BusinessRuleException('Tous les champs sont obligatoires.');
        }
        if (!in_array($vehicle['energy'], self::ENERGIES, true)) {
            throw new BusinessRuleException('Type d\'énergie invalide.');
        }
        if ($this->vehicles->plateExists($vehicle['plate'])) {
            throw new BusinessRuleException('Cette plaque est déjà enregistrée.');
        }

        $vehicleId = $this->vehicles->create($userId, $vehicle);
        $this->users->markAsDriver($userId);
        return $vehicleId;
    }

    /** Un véhicule prévu pour un trajet à venir ou en cours ne peut pas être supprimé. */
    public function remove(int $vehicleId, int $userId): void
    {
        if ($this->trips->countActiveForVehicle($vehicleId) > 0) {
            throw new BusinessRuleException('Ce véhicule est associé à un trajet en cours. Annulez le trajet avant de supprimer le véhicule.');
        }
        $this->vehicles->deactivate($vehicleId, $userId);
    }
}
