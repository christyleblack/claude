<?php

namespace App\Models\Demandes;

use App\Models\BaseModel;

/**
 * Circuits de validation (DEM-03, ADM-02) : un par filière, plus un circuit par défaut.
 * Une modification ne touche que les demandes soumises ensuite : les étapes d'une demande
 * sont figées à sa soumission.
 */
class CircuitModel extends BaseModel
{
    public const MODES = [
        'logistique' => 'Échelon de la chaîne logistique du demandeur',
        'filiere'    => 'Service gestionnaire de la filière',
        'entite'     => 'Entité désignée',
    ];

    protected $table         = 'circuit_validation';
    protected $useTimestamps = true;
    protected $allowedFields = ['libelle', 'filiere_id', 'actif'];

    protected array $casts = ['id' => 'int', 'actif' => 'boolean'];

    protected $validationRules = [
        'libelle' => 'required|max_length[150]',
    ];

    protected $validationMessages = [
        'libelle' => ['required' => 'Le libellé est obligatoire.'],
    ];

    protected $beforeInsert = ['viderChampsVides'];
    protected $beforeUpdate = ['viderChampsVides'];

    protected function viderChampsVides(array $data): array
    {
        if (($data['data']['filiere_id'] ?? null) === '') {
            $data['data']['filiere_id'] = null;
        }

        return $data;
    }

    public function liste(): array
    {
        return $this->db->table('circuit_validation c')
            ->select('c.*, f.libelle AS filiere')
            ->join('filiere f', 'f.id = c.filiere_id', 'left')
            ->orderBy('c.filiere_id IS NOT NULL', '', false)->orderBy('f.libelle')
            ->get()->getResultArray();
    }

    public function etapes(int $circuit): array
    {
        return $this->db->table('circuit_etape ce')
            ->select('ce.*, entite_libelle(ce.entite_id) AS entite')
            ->where('ce.circuit_id', $circuit)->orderBy('ce.ordre')
            ->get()->getResultArray();
    }
}
