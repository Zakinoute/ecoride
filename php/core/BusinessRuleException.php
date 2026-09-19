<?php
/**
 * BusinessRuleException — une règle métier n'est pas respectée
 * (crédits insuffisants, trajet complet, mot de passe trop faible…).
 *
 * Le modèle lance l'exception avec un message pour l'utilisateur ;
 * le contrôleur la transforme en réponse JSON { success: false, message }.
 */
class BusinessRuleException extends RuntimeException
{
}
