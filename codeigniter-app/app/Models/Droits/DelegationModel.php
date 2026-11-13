<?php

namespace App\Models\Droits;

use App\Models\BaseModel;

/**
 * Délégations temporaires d'une affectation à un autre utilisateur (règle 4.2-5).
 * La base refuse l'auto-délégation et une période qui déborde celle de l'affectation.
 */
class DelegationModel extends BaseModel
{
    protected $table         = 'delegation';
    protected $useTimestamps = true;
    protected $allowedFields = ['affectation_id', 'delegataire_id', 'date_debut', 'date_fin', 'motif', 'revoquee_le', 'revoquee_par', 'cree_par'];

    protected array $casts = ['id' => 'int', 'affectation_id' => 'int', 'delegataire_id' => 'int'];

    protected $validationRules = [
        'affectation_id' => 'required|is_not_unique[affectation.id]',
        'delegataire_id' => 'required|is_not_unique[users.id]',
        'date_debut'     => 'required|valid_date[Y-m-d]',
        'date_fin'       => 'required|valid_date[Y-m-d]',
        'motif'          => 'required|max_length[500]',
    ];

    protected $validationMessages = [
        'delegataire_id' => ['required' => 'Le délégataire est obligatoire.', 'is_not_unique' => 'Utilisateur inconnu.'],
        'date_debut'     => ['required' => 'La date de début est obligatoire.', 'valid_date' => 'Date de début invalide.'],
        'date_fin'       => ['required' => 'La date de fin est obligatoire.', 'valid_date' => 'Date de fin invalide.'],
        'motif'          => ['required' => 'Le motif est obligatoire.'],
    ];

    /**
     * Délégations données (sur les affectations de l'utilisateur) ou reçues.
     */
    public function concernant(int $utilisateurId): array
    {
        return $this->db->query(
            "SELECT d.*, r.libelle AS role, e.nom AS entite,
                    titulaire.username AS titulaire, delegataire.username AS delegataire,
                    a.utilisateur_id AS titulaire_id,
                    CASE
                        WHEN d.revoquee_le IS NOT NULL THEN 'révoquée'
                        WHEN d.date_debut > current_date THEN 'à venir'
                        WHEN d.date_fin < current_date THEN 'terminée'
                        ELSE 'active'
                    END AS statut
             FROM delegation d
             JOIN affectation a ON a.id = d.affectation_id
             JOIN role r ON r.id = a.role_id
             LEFT JOIN entite e ON e.id = a.entite_id
             JOIN users titulaire ON titulaire.id = a.utilisateur_id
             JOIN users delegataire ON delegataire.id = d.delegataire_id
             WHERE a.utilisateur_id = ? OR d.delegataire_id = ?
             ORDER BY d.date_debut DESC, d.id DESC",
            [$utilisateurId, $utilisateurId],
        )->getResultArray();
    }
}
