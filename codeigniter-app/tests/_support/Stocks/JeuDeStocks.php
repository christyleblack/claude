<?php

namespace Tests\Support\Stocks;

use App\Models\Droits\AffectationModel;
use App\Models\Droits\RoleModel;
use App\Models\Organisation\EmplacementModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\SiteModel;
use App\Models\Organisation\TypeEntiteModel;
use App\Models\Referentiel\ArticleModel;
use App\Models\Referentiel\CategorieModel;
use App\Models\Referentiel\FiliereModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

/**
 * Jeu de données fictif commun aux tests des stocks :
 * TERRE › TERRE-D1 (emplacements T-R1, T-R2) ; AIR › AIR-D1 (emplacement A-R1) ;
 * articles GASOIL (quantité, L), VEH (série), MUN (lot) ; magasiniers de chaque dépôt.
 */
trait JeuDeStocks
{
    protected array $id = [];
    protected User $magTerre;
    protected User $magAir;
    protected User $lecteurTerre;

    protected function creerJeuDeStocks(): void
    {
        $type    = (new TypeEntiteModel())->insert(['code' => 'UNITE', 'libelle' => 'Unité']);
        $entites = new EntiteModel();
        foreach ([['NATIONAL', null], ['TERRE', null], ['TERRE-D1', 'TERRE'], ['AIR', null], ['AIR-D1', 'AIR']] as [$code, $parent]) {
            $this->id[$code] = (int) $entites->insert(['code' => $code, 'nom' => 'Entité ' . $code, 'type_entite_id' => $type, 'parent_hierarchique_id' => $parent === null ? null : $this->id[$parent]]);
        }
        $sites                = new SiteModel();
        $siteTerre            = (int) $sites->insert(['code' => 'S-TERRE', 'nom' => 'Site Terre', 'entite_id' => $this->id['TERRE-D1']]);
        $siteAir              = (int) $sites->insert(['code' => 'S-AIR', 'nom' => 'Site Air', 'entite_id' => $this->id['AIR-D1']]);
        $emplacements         = new EmplacementModel();
        $this->id['T-R1']     = (int) $emplacements->insert(['site_id' => $siteTerre, 'code' => 'T-R1', 'libelle' => 'Rayon Terre 1']);
        $this->id['T-R2']     = (int) $emplacements->insert(['site_id' => $siteTerre, 'code' => 'T-R2', 'libelle' => 'Rayon Terre 2']);
        $this->id['A-R1']     = (int) $emplacements->insert(['site_id' => $siteAir, 'code' => 'A-R1', 'libelle' => 'Rayon Air 1']);

        $filiere    = (int) (new FiliereModel())->insert(['code' => 'F', 'libelle' => 'Filière', 'service_gestionnaire_id' => $this->id['NATIONAL']]);
        $categories = new CategorieModel();
        $articles   = new ArticleModel();
        foreach ([['GASOIL', 'quantite', 'L', 'courant'], ['VEH', 'serie', 'U', 'courant'], ['MUN', 'lot', 'U', 'munition']] as [$code, $mode, $unite, $sensibilite]) {
            $categorie       = (int) $categories->insert(['filiere_id' => $filiere, 'code' => 'C-' . $code, 'libelle' => 'Catégorie ' . $code]);
            $this->id[$code] = (int) $articles->insert(['codification' => $code, 'designation' => 'Article ' . $code, 'unite_gestion' => $unite, 'filiere_id' => $filiere, 'categorie_id' => $categorie, 'mode_suivi' => $mode, 'sensibilite' => $sensibilite]);
        }

        $roles = new RoleModel();
        $mag   = (int) $roles->insert(['code' => 'MAG', 'libelle' => 'Magasinier', 'portee' => 'entite']);
        $roles->definirPermissions($mag, ['stocks.mouvementer', 'stocks.sensibles']);
        $lecture = (int) $roles->insert(['code' => 'LECTURE', 'libelle' => 'Lecture', 'portee' => 'entite']);

        $this->magTerre     = fake(UserModel::class, ['username' => 'mag.terre', 'active' => 1]);
        $this->magAir       = fake(UserModel::class, ['username' => 'mag.air', 'active' => 1]);
        $this->lecteurTerre = fake(UserModel::class, ['username' => 'lecteur.terre', 'active' => 1]);
        $affectations       = new AffectationModel();
        foreach ([[$this->magTerre, $mag, 'TERRE-D1'], [$this->magAir, $mag, 'AIR-D1'], [$this->lecteurTerre, $lecture, 'TERRE-D1']] as [$u, $role, $entite]) {
            $affectations->insert(['utilisateur_id' => $u->id, 'role_id' => $role, 'entite_id' => $this->id[$entite], 'date_debut' => date('Y-m-d')]);
        }
    }

    /**
     * Quantité en stock (vue propriétaire, sans cloisonnement).
     */
    protected function stock(string $article, string $emplacement): float
    {
        $ligne = db_connect()->query(
            'SELECT coalesce(sum(quantite), 0) AS q FROM stock WHERE article_id = ? AND emplacement_id = ?',
            [$this->id[$article], $this->id[$emplacement]],
        )->getRow();

        return (float) $ligne->q;
    }
}
