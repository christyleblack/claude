<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Droits de l'utilisateur connecté : RBAC (permission du rôle) et ABAC (périmètre de
 * l'affectation), calculés par la base (fonction droits_peut) à partir des affectations
 * et délégations actives.
 *
 * Le contexte (utilisateur, rôle PostgreSQL) est posé par le filtre ContexteDroits.
 */
class Droits
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * L'utilisateur a-t-il la permission :
     * - sans entité : sur au moins une partie de l'organisation ;
     * - avec une entité : sur cette entité (son périmètre la couvre) ;
     * - avec $nationale : au niveau national.
     */
    public function peut(string $permission, ?int $entiteId = null, bool $nationale = false): bool
    {
        if (! auth()->loggedIn()) {
            return false;
        }

        $cle = $permission . '|' . ($entiteId ?? '') . '|' . (int) $nationale;

        return $this->cache[$cle] ??= $this->db->query(
            'SELECT droits_peut(?, ?, ?) AS ok',
            [$permission, $entiteId, $nationale],
        )->getRow()->ok === 't';
    }

    /**
     * Permission au niveau national sur une filière : par une affectation non restreinte,
     * ou restreinte à cette filière (H-02). Sans filière : affectation non restreinte exigée.
     */
    public function peutFiliere(string $permission, ?int $filiereId = null): bool
    {
        if (! auth()->loggedIn()) {
            return false;
        }

        $cle = 'filiere|' . $permission . '|' . ($filiereId ?? '');

        return $this->cache[$cle] ??= $this->db->query(
            'SELECT droits_peut_filiere(?, ?) AS ok',
            [$permission, $filiereId],
        )->getRow()->ok === 't';
    }

    /**
     * Permission sur une force (sur son entité racine).
     */
    public function peutForce(string $permission, int $forceId): bool
    {
        $racine = $this->db->table('force')->select('entite_racine_id')->where('id', $forceId)->get()->getRow();

        return $racine !== null && $this->peut($permission, (int) $racine->entite_racine_id);
    }

    /**
     * Affectations actives de l'utilisateur connecté (directes et reçues par délégation).
     */
    public function affectationsActives(): array
    {
        if (! auth()->loggedIn()) {
            return [];
        }

        return $this->db->query(
            'SELECT a.affectation_id, a.par_delegation, r.libelle AS role, r.portee, e.nom AS entite
             FROM droits_affectations_actives(?) a
             JOIN role r ON r.id = a.role_id
             JOIN affectation af ON af.id = a.affectation_id
             JOIN entite e ON e.id = af.entite_id
             ORDER BY r.libelle',
            [auth()->id()],
        )->getResultArray();
    }

    public function oublier(): void
    {
        $this->cache = [];
    }
}
