<?php

namespace App\Models\Droits;

use App\Models\BaseModel;

/**
 * Rôles (RBAC) : paramétrables sans développement (ADM-02).
 */
class RoleModel extends BaseModel
{
    protected $table         = 'role';
    protected $useTimestamps = true;
    protected $allowedFields = ['code', 'libelle', 'portee', 'actif'];

    protected array $casts = ['id' => 'int', 'actif' => 'boolean'];

    protected $validationRules = [
        'id'      => 'permit_empty|is_natural_no_zero',
        'code'    => 'required|max_length[40]|regex_match[/^[A-Z0-9_]+$/]|is_unique[role.code,id,{id}]',
        'libelle' => 'required|max_length[150]',
        'portee'  => 'required|in_list[nationale,entite]',
    ];

    protected $validationMessages = [
        'code' => [
            'required'    => 'Le code est obligatoire.',
            'regex_match' => 'Le code ne peut contenir que des majuscules, des chiffres et « _ ».',
            'is_unique'   => 'Ce code est déjà utilisé.',
        ],
        'libelle' => ['required' => 'Le libellé est obligatoire.'],
        'portee'  => ['in_list' => 'Portée inconnue.'],
    ];

    /**
     * @return list<string>
     */
    public function permissions(int $roleId): array
    {
        return array_column(
            $this->db->table('role_permission')->select('permission')->where('role_id', $roleId)->get()->getResultArray(),
            'permission',
        );
    }

    /**
     * Remplace les permissions d'un rôle (dans une transaction).
     *
     * @param list<string> $permissions
     */
    public function definirPermissions(int $roleId, array $permissions): void
    {
        $this->db->transStart();
        $this->db->table('role_permission')->where('role_id', $roleId)->delete();
        foreach (array_unique($permissions) as $permission) {
            $this->db->table('role_permission')->insert(['role_id' => $roleId, 'permission' => $permission]);
        }
        $this->db->transComplete();
    }

    /**
     * @return array<string, string> code => libellé
     */
    public function toutesPermissions(): array
    {
        return array_column(
            $this->db->table('permission')->orderBy('code')->get()->getResultArray(),
            'libelle',
            'code',
        );
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->where('actif', true)->orderBy('libelle')->findAll() as $r) {
            $options[$r['id']] = $r['libelle'] . ($r['portee'] === 'nationale' ? ' (portée nationale)' : '');
        }

        return $options;
    }
}
