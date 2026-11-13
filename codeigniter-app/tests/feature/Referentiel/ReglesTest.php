<?php

namespace Tests\Feature\Referentiel;

use App\Models\Referentiel\ArticleForceModel;
use App\Models\Referentiel\ArticleModel;
use App\Models\Referentiel\CategorieModel;
use App\Models\Referentiel\FiliereModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\ForceModel;
use App\Models\Organisation\TypeEntiteModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\TestCase;

/**
 * Règles de gestion du référentiel garanties par la base (RG-05 à RG-09).
 *
 * @internal
 */
final class ReglesTest extends TestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    private int $engins;
    private int $armement;
    private int $vehicules;
    private int $munitions;
    private ArticleModel $articles;

    protected function setUp(): void
    {
        parent::setUp();

        $type    = (new TypeEntiteModel())->insert(['code' => 'SERVICE', 'libelle' => 'Service']);
        $service = (int) (new EntiteModel())->insert(['code' => 'SERV', 'nom' => 'Service', 'type_entite_id' => $type]);

        $filieres        = new FiliereModel();
        $this->engins    = (int) $filieres->insert(['code' => 'ENGINS', 'libelle' => 'Engins', 'service_gestionnaire_id' => $service]);
        $this->armement  = (int) $filieres->insert(['code' => 'ARMEMENT', 'libelle' => 'Armement', 'service_gestionnaire_id' => $service]);
        $categories      = new CategorieModel();
        $this->vehicules = (int) $categories->insert(['filiere_id' => $this->engins, 'code' => 'VEH', 'libelle' => 'Véhicules', 'mode_suivi_impose' => 'serie']);
        $this->munitions = (int) $categories->insert(['filiere_id' => $this->armement, 'code' => 'MUN', 'libelle' => 'Munitions']);
        $this->articles  = new ArticleModel();
    }

    private function article(array $valeurs): int|false
    {
        return $this->articles->insert($valeurs + [
            'codification' => 'A-1', 'designation' => 'Article', 'unite_gestion' => 'U',
            'filiere_id' => $this->engins, 'categorie_id' => $this->vehicules, 'mode_suivi' => 'serie', 'sensibilite' => 'courant',
        ]);
    }

    public function testUnArticleCorrectEstAccepte(): void
    {
        $this->assertNotFalse($this->article([]));
    }

    public function testLaCategorieDoitAppartenirALaFiliereDeLArticle(): void
    {
        $this->expectException(DatabaseException::class);

        $this->article(['categorie_id' => $this->munitions, 'mode_suivi' => 'lot']);
    }

    public function testLaCategoriePeutImposerLeModeDeSuivi(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Mode de suivi refusé');

        $this->article(['mode_suivi' => 'quantite']);
    }

    public function testOnNePeutPasImposerUnModeContraireAuxArticlesExistants(): void
    {
        $this->article([]);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Mode de suivi refusé');

        (new CategorieModel())->update($this->vehicules, ['mode_suivi_impose' => 'lot']);
    }

    public function testUneArmeEstSuivieParNumeroDeSerie(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('armement_suivi_par_serie');

        $this->article(['filiere_id' => $this->armement, 'categorie_id' => $this->munitions, 'mode_suivi' => 'lot', 'sensibilite' => 'armement']);
    }

    public function testUneMunitionEstSuivieParLot(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('munition_suivie_par_lot');

        $this->article(['filiere_id' => $this->armement, 'categorie_id' => $this->munitions, 'mode_suivi' => 'quantite', 'sensibilite' => 'munition']);
    }

    public function testUnArticleSerialiseSeGereALUnite(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('serie_en_unites');

        $this->article(['unite_gestion' => 'L']);
    }

    public function testUneSousCategorieResteDansSaFiliere(): void
    {
        $this->expectException(DatabaseException::class);

        (new CategorieModel())->insert(['filiere_id' => $this->armement, 'parent_id' => $this->vehicules, 'code' => 'X', 'libelle' => 'X']);
    }

    public function testUnCycleDeCategoriesEstRefuse(): void
    {
        $categories = new CategorieModel();
        $sous       = (int) $categories->insert(['filiere_id' => $this->engins, 'parent_id' => $this->vehicules, 'code' => 'SOUS', 'libelle' => 'Sous']);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Cycle refusé');

        $categories->update($this->vehicules, ['parent_id' => $sous]);
    }

    public function testUneSeuleFicheParForce(): void
    {
        $type   = (new TypeEntiteModel())->insert(['code' => 'FORCE', 'libelle' => 'Force']);
        $racine = (int) (new EntiteModel())->insert(['code' => 'TERRE', 'nom' => 'Terre', 'type_entite_id' => $type]);
        $force  = (int) (new ForceModel())->insert(['code' => 'TERRE', 'nom' => 'Terre', 'entite_racine_id' => $racine]);
        $article = (int) $this->article([]);
        $fiches  = new ArticleForceModel();
        $fiches->insert(['article_id' => $article, 'force_id' => $force, 'code_local' => 'L1']);

        $this->expectException(DatabaseException::class);

        $fiches->insert(['article_id' => $article, 'force_id' => $force, 'code_local' => 'L2']);
    }

    public function testLesAttributsDeForceSontUnObjet(): void
    {
        $this->assertSame(['seuil' => '10', 'emplacement prefere' => 'R1'], ArticleForceModel::attributsDepuisTexte("seuil = 10\n\nemplacement prefere = R1\nligne sans signe égal"));
        $this->assertSame("a = 1\nb = x", ArticleForceModel::attributsEnTexte('{"a": 1, "b": "x"}'));
    }
}
