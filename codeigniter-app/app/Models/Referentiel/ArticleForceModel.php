<?php

namespace App\Models\Referentiel;

use App\Models\BaseModel;

/**
 * Fiche propre à une force pour un article national (REF-02) ; au plus une par force (RG-09).
 */
class ArticleForceModel extends BaseModel
{
    protected $table         = 'article_force';
    protected $useTimestamps = true;
    protected $allowedFields = ['article_id', 'force_id', 'code_local', 'designation_locale', 'attributs', 'actif'];

    protected array $casts = ['id' => 'int', 'article_id' => 'int', 'force_id' => 'int', 'actif' => 'boolean'];

    protected $validationRules = [
        'article_id'         => 'required|is_not_unique[article.id]',
        'force_id'           => 'required|is_not_unique[force.id]',
        'code_local'         => 'permit_empty|max_length[40]',
        'designation_locale' => 'permit_empty|max_length[250]',
    ];

    /**
     * Fiches d'un article, avec le nom de la force.
     */
    public function deLArticle(int $articleId): array
    {
        return $this->db->table('article_force af')
            ->select('af.*, f.nom AS force, f.code AS force_code')
            ->join('force f', 'f.id = af.force_id')
            ->where('af.article_id', $articleId)
            ->orderBy('f.nom')
            ->get()->getResultArray();
    }

    /**
     * « clé = valeur » par ligne ↔ objet JSON des attributs propres à la force.
     */
    public static function attributsDepuisTexte(string $texte): array
    {
        $attributs = [];
        foreach (preg_split('/\R/', $texte) as $ligne) {
            if (str_contains($ligne, '=')) {
                [$cle, $valeur] = array_map('trim', explode('=', $ligne, 2));
                if ($cle !== '') {
                    $attributs[$cle] = $valeur;
                }
            }
        }

        return $attributs;
    }

    public static function attributsEnTexte(?string $json): string
    {
        $lignes = [];
        foreach ((array) json_decode($json ?? '{}', true) as $cle => $valeur) {
            $lignes[] = $cle . ' = ' . (is_scalar($valeur) ? $valeur : json_encode($valeur, JSON_UNESCAPED_UNICODE));
        }

        return implode("\n", $lignes);
    }
}
