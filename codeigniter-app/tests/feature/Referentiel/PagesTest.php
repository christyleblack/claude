<?php

namespace Tests\Feature\Referentiel;

use App\Models\Droits\AffectationModel;
use App\Models\Droits\RoleModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\ForceModel;
use App\Models\Organisation\TypeEntiteModel;
use App\Models\Referentiel\ArticleForceModel;
use App\Models\Referentiel\ArticleModel;
use App\Models\Referentiel\CategorieModel;
use App\Models\Referentiel\FiliereModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\TestCase;

/**
 * Pages du référentiel et droits : lecture par tous, gestion par filière (H-02),
 * fiches propres gérées par chaque force.
 *
 * @internal
 */
final class PagesTest extends TestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    private array $id = [];
    private User $lecteur;
    private User $gestionnaire;
    private User $gestionnaireEngins;
    private User $chefTerre;

    protected function setUp(): void
    {
        parent::setUp();

        $type    = (new TypeEntiteModel())->insert(['code' => 'UNITE', 'libelle' => 'Unité']);
        $entites = new EntiteModel();
        foreach (['DCMA', 'SERV', 'TERRE', 'AIR'] as $code) {
            $this->id[$code] = (int) $entites->insert(['code' => $code, 'nom' => 'Entité ' . $code, 'type_entite_id' => $type]);
        }
        $forces            = new ForceModel();
        $this->id['F-TERRE'] = (int) $forces->insert(['code' => 'TERRE', 'nom' => 'Armée de Terre (fictif)', 'entite_racine_id' => $this->id['TERRE']]);
        $this->id['F-AIR']   = (int) $forces->insert(['code' => 'AIR', 'nom' => 'Armée de l\'Air (fictif)', 'entite_racine_id' => $this->id['AIR']]);

        $filieres               = new FiliereModel();
        $categories             = new CategorieModel();
        $this->id['ENGINS']     = (int) $filieres->insert(['code' => 'ENGINS', 'libelle' => 'Engins roulants', 'service_gestionnaire_id' => $this->id['SERV']]);
        $this->id['ARMEMENT']   = (int) $filieres->insert(['code' => 'ARMEMENT', 'libelle' => 'Armement', 'service_gestionnaire_id' => $this->id['SERV']]);
        $this->id['VEH']        = (int) $categories->insert(['filiere_id' => $this->id['ENGINS'], 'code' => 'VEH', 'libelle' => 'Véhicules', 'mode_suivi_impose' => 'serie']);
        $this->id['ARMES']      = (int) $categories->insert(['filiere_id' => $this->id['ARMEMENT'], 'code' => 'ARMES', 'libelle' => 'Armes', 'mode_suivi_impose' => 'serie']);
        $this->id['MUN']        = (int) $categories->insert(['filiere_id' => $this->id['ARMEMENT'], 'code' => 'MUN', 'libelle' => 'Munitions', 'mode_suivi_impose' => 'lot']);
        $this->id['ART-VEH']    = (int) (new ArticleModel())->insert(['codification' => 'VEH-1', 'designation' => 'Véhicule de liaison (exemple)', 'unite_gestion' => 'U', 'filiere_id' => $this->id['ENGINS'], 'categorie_id' => $this->id['VEH'], 'mode_suivi' => 'serie', 'sensibilite' => 'courant']);
        $this->id['ART-ARME']   = (int) (new ArticleModel())->insert(['codification' => 'ARM-1', 'designation' => 'Arme individuelle (exemple)', 'unite_gestion' => 'U', 'filiere_id' => $this->id['ARMEMENT'], 'categorie_id' => $this->id['ARMES'], 'mode_suivi' => 'serie', 'sensibilite' => 'armement']);

        $roles   = new RoleModel();
        $gestion = (int) $roles->insert(['code' => 'GEST', 'libelle' => 'Gestionnaire de filière', 'portee' => 'nationale']);
        $roles->definirPermissions($gestion, ['referentiel.gerer']);
        $chef = (int) $roles->insert(['code' => 'CHEF', 'libelle' => 'Chef logistique', 'portee' => 'entite']);
        $roles->definirPermissions($chef, ['referentiel.fiche_force']);

        $this->lecteur            = fake(UserModel::class, ['username' => 'lecteur', 'active' => 1]);
        $this->gestionnaire       = fake(UserModel::class, ['username' => 'gestionnaire', 'active' => 1]);
        $this->gestionnaireEngins = fake(UserModel::class, ['username' => 'engins', 'active' => 1]);
        $this->chefTerre          = fake(UserModel::class, ['username' => 'chef.terre', 'active' => 1]);
        $affectations             = new AffectationModel();
        $aujourdhui               = date('Y-m-d');
        $affectations->insert(['utilisateur_id' => $this->gestionnaire->id, 'role_id' => $gestion, 'entite_id' => $this->id['DCMA'], 'date_debut' => $aujourdhui]);
        $affectations->insert(['utilisateur_id' => $this->gestionnaireEngins->id, 'role_id' => $gestion, 'entite_id' => $this->id['DCMA'], 'filiere_id' => $this->id['ENGINS'], 'date_debut' => $aujourdhui]);
        $affectations->insert(['utilisateur_id' => $this->chefTerre->id, 'role_id' => $chef, 'entite_id' => $this->id['TERRE'], 'date_debut' => $aujourdhui]);
    }

    private function jeton(): array
    {
        return [csrf_token() => csrf_hash()];
    }

    private function article(array $valeurs): array
    {
        return $this->jeton() + $valeurs + ['_soumis' => '1', 'designation' => 'Nouvel article (exemple)', 'unite_gestion' => 'U', 'mode_suivi' => 'serie', 'sensibilite' => 'courant', 'actif' => '1'];
    }

    // Lecture

    public function testLeReferentielEstLisibleParTousLesUtilisateursConnectes(): void
    {
        $result = $this->actingAs($this->lecteur)->get('referentiel/articles');
        $result->assertOK();
        $result->assertSee('Véhicule de liaison (exemple)');
        $result->assertSee('Arme individuelle (exemple)');
        $result->assertDontSee('Nouvel article');

        $this->actingAs($this->lecteur)->get('referentiel/articles/' . $this->id['ART-ARME'])->assertSee('ARM-1');
    }

    public function testLaRechercheFiltreLesArticles(): void
    {
        $result = $this->actingAs($this->lecteur)->get('referentiel/articles?q=liaison');
        $result->assertSee('Véhicule de liaison');
        $result->assertDontSee('Arme individuelle');

        $result = $this->actingAs($this->lecteur)->get('referentiel/articles?filiere=' . $this->id['ARMEMENT']);
        $result->assertSee('Arme individuelle');
        $result->assertDontSee('Véhicule de liaison');
    }

    public function testUnVisiteurEstRedirigeVersLaConnexion(): void
    {
        $this->get('referentiel/articles')->assertRedirectTo('/login');
    }

    // Gestion par filière

    public function testUnGestionnaireCreeUnArticleDontLaFiliereVientDeLaCategorie(): void
    {
        $this->actingAs($this->gestionnaire)->post('referentiel/articles', $this->article(['codification' => 'mun-9', 'categorie_id' => (string) $this->id['MUN'], 'mode_suivi' => 'lot', 'sensibilite' => 'munition']));

        $article = (new ArticleModel())->where('codification', 'MUN-9')->first();
        $this->assertNotNull($article, 'la codification est mise en majuscules');
        $this->assertSame($this->id['ARMEMENT'], $article['filiere_id']);
    }

    public function testUnGestionnaireLimiteAUneFiliereNeGerePasLesAutres(): void
    {
        $this->actingAs($this->gestionnaireEngins)->post('referentiel/articles', $this->article(['codification' => 'VEH-2', 'categorie_id' => (string) $this->id['VEH']]));
        $this->seeInDatabase('article', ['codification' => 'VEH-2']);

        $result = $this->actingAs($this->gestionnaireEngins)->post('referentiel/articles', $this->article(['codification' => 'ARM-2', 'categorie_id' => (string) $this->id['ARMES'], 'sensibilite' => 'armement']));
        $result->assertSessionHas('erreurs');
        $this->dontSeeInDatabase('article', ['codification' => 'ARM-2']);

        $this->actingAs($this->gestionnaireEngins)->get('referentiel/articles/' . $this->id['ART-ARME'] . '/edit')->assertSessionHas('erreur');
        $this->actingAs($this->gestionnaireEngins)->get('referentiel/filieres/new')->assertSessionHas('erreur');
    }

    public function testLaBaseRefuseLEcritureHorsDeSaFiliere(): void
    {
        $this->executerCommeApplication($this->gestionnaireEngins->id, "UPDATE article SET designation = 'Modifié' WHERE codification = 'VEH-1'");
        $this->assertSame('Modifié', (new ArticleModel())->find($this->id['ART-VEH'])['designation']);

        $this->executerCommeApplication($this->gestionnaireEngins->id, "UPDATE article SET designation = 'Piraté' WHERE codification = 'ARM-1'");
        $this->assertSame('Arme individuelle (exemple)', (new ArticleModel())->find($this->id['ART-ARME'])['designation'], 'la ligne est hors de ses droits d\'écriture');

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('row-level security');
        $this->executerCommeApplication($this->gestionnaireEngins->id, "INSERT INTO filiere (code, libelle, service_gestionnaire_id) VALUES ('NOUV', 'Nouvelle', {$this->id['SERV']})");
    }

    public function testSansDroitOnNeGereRienMemeEnBase(): void
    {
        $this->actingAs($this->lecteur)->post('referentiel/articles', $this->article(['codification' => 'X-1', 'categorie_id' => (string) $this->id['VEH']]))->assertSessionHas('erreur');
        $this->dontSeeInDatabase('article', ['codification' => 'X-1']);

        $this->executerCommeApplication($this->lecteur->id, "UPDATE article SET designation = 'Piraté'");
        $this->dontSeeInDatabase('article', ['designation' => 'Piraté']);
    }

    public function testUnArticleNeSeSupprimePas(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('permission denied');

        $this->executerCommeApplication($this->gestionnaire->id, 'DELETE FROM article');
    }

    public function testUneRegleDeLaBaseEstExpliqueeEnClair(): void
    {
        // La catégorie Armes impose la série : on désactive la contrainte de catégorie
        // pour atteindre la règle « armement suivi par numéro de série »
        (new CategorieModel())->update($this->id['ARMES'], ['mode_suivi_impose' => null]);

        $result = $this->actingAs($this->gestionnaire)->post('referentiel/articles', $this->article(['codification' => 'ARM-3', 'categorie_id' => (string) $this->id['ARMES'], 'mode_suivi' => 'quantite', 'sensibilite' => 'armement']));

        $this->assertStringContainsString('numéro de série (RG-07)', implode(' ', session('erreurs')));
    }

    // Fiches propres aux forces

    public function testUneForceGereSaFicheEtPasCelleDesAutres(): void
    {
        $this->actingAs($this->chefTerre)->get('referentiel/articles/' . $this->id['ART-VEH'])->assertSee('Ajouter la fiche « Armée de Terre (fictif) »');

        $this->actingAs($this->chefTerre)->post('referentiel/fiches', $this->jeton() + [
            '_soumis' => '1', 'article_id' => (string) $this->id['ART-VEH'], 'force_id' => (string) $this->id['F-TERRE'],
            'code_local' => 'VL-T', 'designation_locale' => 'VL de liaison', 'attributs' => "seuil_alerte = 5\ncouleur = sable", 'actif' => '1',
        ])->assertRedirectTo('/referentiel/articles/' . $this->id['ART-VEH']);

        $fiche = (new ArticleForceModel())->where('force_id', $this->id['F-TERRE'])->first();
        $this->assertSame(['couleur' => 'sable', 'seuil_alerte' => '5'], json_decode($fiche['attributs'], true));

        $result = $this->actingAs($this->chefTerre)->post('referentiel/fiches', $this->jeton() + [
            '_soumis' => '1', 'article_id' => (string) $this->id['ART-VEH'], 'force_id' => (string) $this->id['F-AIR'], 'code_local' => 'PIRATE', 'attributs' => '', 'actif' => '1',
        ]);
        $result->assertSessionHas('erreurs');
        $this->dontSeeInDatabase('article_force', ['code_local' => 'PIRATE']);
    }

    public function testUneDeuxiemeFicheDeLaMemeForceEstRefuseeAvecUnMessage(): void
    {
        (new ArticleForceModel())->insert(['article_id' => $this->id['ART-VEH'], 'force_id' => $this->id['F-TERRE'], 'code_local' => 'L1']);

        $result = $this->actingAs($this->chefTerre)->post('referentiel/fiches', $this->jeton() + [
            '_soumis' => '1', 'article_id' => (string) $this->id['ART-VEH'], 'force_id' => (string) $this->id['F-TERRE'], 'code_local' => 'L2', 'attributs' => '', 'actif' => '1',
        ]);

        $this->assertStringContainsString('RG-09', implode(' ', session('erreurs')));
    }

    // Affectation limitée à une filière

    public function testUneAffectationPeutEtreLimiteeAUneFiliere(): void
    {
        $admin = fake(UserModel::class, ['username' => 'admin', 'active' => 1]);
        $role  = (int) (new RoleModel())->insert(['code' => 'ADMIN', 'libelle' => 'Admin', 'portee' => 'nationale']);
        (new RoleModel())->definirPermissions($role, ['administration.utilisateurs']);
        (new AffectationModel())->insert(['utilisateur_id' => $admin->id, 'role_id' => $role, 'entite_id' => $this->id['DCMA'], 'date_debut' => date('Y-m-d')]);

        $this->actingAs($admin)->post("administration/utilisateurs/{$this->lecteur->id}/affectations", $this->jeton() + [
            'role_id' => (string) (new RoleModel())->where('code', 'GEST')->first()['id'], 'entite_id' => (string) $this->id['DCMA'],
            'filiere_id' => (string) $this->id['ARMEMENT'], 'date_debut' => date('Y-m-d'), 'date_fin' => '',
        ]);

        $this->assertSame((string) $this->id['ARMEMENT'], (string) (new AffectationModel())->where('utilisateur_id', $this->lecteur->id)->first()['filiere_id']);
    }
}
