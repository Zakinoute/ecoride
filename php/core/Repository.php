<?php
/**
 * Repository — base commune des repositories MySQL.
 *
 * Un repository par table : c'est le seul endroit où l'on écrit du SQL.
 * Toutes les requêtes sont préparées ; les valeurs ne sont jamais
 * collées dans le texte de la requête.
 */
abstract class Repository
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }
}
