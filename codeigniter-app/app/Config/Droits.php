<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cloisonnement des données (cahier des charges, section 4.2).
 */
class Droits extends BaseConfig
{
    /**
     * Rôle PostgreSQL sous lequel l'application interroge la base à chaque requête HTTP.
     * Les politiques de sécurité au niveau des lignes s'appliquent à ce rôle ; le propriétaire
     * des tables (migrations, commandes spark) n'y est pas soumis.
     *
     * En production, se connecter directement avec ce rôle (sans être membre du propriétaire),
     * et lancer les migrations avec un autre compte.
     *
     * Surcharge possible dans .env : droits.roleApplication
     */
    public string $roleApplication = 'gestion_app';
}
