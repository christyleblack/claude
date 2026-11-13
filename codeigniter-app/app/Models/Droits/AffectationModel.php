<?php

namespace App\Models\Droits;

use App\Models\BaseModel;

/**
 * Affectations : un rôle exercé sur une entité, pour une période (RG-04).
 * Une affectation n'est jamais supprimée : elle se termine (date de fin) ou se révoque.
 */
class AffectationModel extends BaseModel
{
    protected $table         = 'affectation';
    protected $useTimestamps = true;
    protected $allowedFields = ['utilisateur_id', 'role_id', 'entite_id', 'filiere_id', 'date_debut', 'date_fin', 'revoquee_le', 'revoquee_par', 'cree_par'];

    protected array $casts = ['id' => 'int', 'utilisateur_id' => 'int', 'role_id' => 'int', 'entite_id' => 'int'];

    protected $validationRules = [
        'utilisateur_id' => 'required|is_not_unique[users.id]',
        'role_id'        => 'required|is_not_unique[role.id]',
        'entite_id'      => 'required|is_natural_no_zero',
        'date_debut'     => 'required|valid_date[Y-m-d]',
        'date_fin'       => 'permit_empty|valid_date[Y-m-d]',
        'filiere_id'     => 'permit_empty|is_not_unique[filiere.id]',
    ];

    protected $validationMessages = [
        'role_id'    => ['required' => 'Le rôle est obligatoire.'],
        'entite_id'  => ['required' => 'L\'entité est obligatoire.', 'is_natural_no_zero' => 'L\'entité est obligatoire.'],
        'date_debut' => ['required' => 'La date de début est obligatoire.', 'valid_date' => 'Date de début invalide.'],
        'date_fin'   => ['valid_date' => 'Date de fin invalide.'],
    ];

    protected $beforeInsert = ['viderChampsVides'];

    protected function viderChampsVides(array $data): array
    {
        foreach (['date_fin', 'filiere_id'] as $champ) {
            if (($data['data'][$champ] ?? null) === '') {
                $data['data'][$champ] = null;
            }
        }

        return $data;
    }

    /**
     * Affectations d'un utilisateur avec rôle, entité et statut (active, à venir, terminée, révoquée).
     * Les entités hors du périmètre de la personne qui consulte apparaissent comme telles.
     */
    public function deUtilisateur(int $utilisateurId): array
    {
        return $this->db->query(
            "SELECT a.*, r.libelle AS role, r.portee, e.nom AS entite, f.libelle AS filiere,
                    CASE
                        WHEN a.revoquee_le IS NOT NULL THEN 'révoquée'
                        WHEN a.date_debut > current_date THEN 'à venir'
                        WHEN a.date_fin IS NOT NULL AND a.date_fin < current_date THEN 'terminée'
                        ELSE 'active'
                    END AS statut
             FROM affectation a
             JOIN role r ON r.id = a.role_id
             LEFT JOIN entite e ON e.id = a.entite_id
             LEFT JOIN filiere f ON f.id = a.filiere_id
             WHERE a.utilisateur_id = ?
             ORDER BY a.date_debut DESC, a.id DESC",
            [$utilisateurId],
        )->getResultArray();
    }
}
