<?php

namespace Tests\Feature\Organisation;

use App\Models\Droits\AffectationModel;
use App\Models\Droits\RoleModel;
use App\Models\Organisation\EmplacementModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\SiteModel;
use App\Models\Organisation\TypeEntiteModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use Tests\Support\TestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Pages du module Organisation : consultation dans le périmètre de l'utilisateur,
 * modification réservée à la permission organisation.gerer, portée par un rôle affecté.
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

    private User $admin;
    private User $lecteur;
    private int $type;
    private EntiteModel $entites;

    protected function setUp(): void
    {
        $this->resetServices();

        parent::setUp();

        $this->type    = (new TypeEntiteModel())->insert(['code' => 'UNITE', 'libelle' => 'Unité']);
        $this->entites = new EntiteModel();
        $national      = $this->creer('NATIONAL');

        // Rôles nationaux : l'un gère l'organisation, l'autre la consulte seulement
        $roles = new RoleModel();
        $gerer = (int) $roles->insert(['code' => 'GERER', 'libelle' => 'Gestion', 'portee' => 'nationale']);
        $roles->definirPermissions($gerer, ['organisation.gerer']);
        $lire = (int) $roles->insert(['code' => 'LIRE', 'libelle' => 'Lecture', 'portee' => 'nationale']);

        $this->admin   = fake(UserModel::class, ['username' => 'admin', 'active' => 1]);
        $this->lecteur = fake(UserModel::class, ['username' => 'lecteur', 'active' => 1]);
        $affectations  = new AffectationModel();
        $affectations->insert(['utilisateur_id' => $this->admin->id, 'role_id' => $gerer, 'entite_id' => $national, 'date_debut' => date('Y-m-d')]);
        $affectations->insert(['utilisateur_id' => $this->lecteur->id, 'role_id' => $lire, 'entite_id' => $national, 'date_debut' => date('Y-m-d')]);
    }

    private function jeton(): array
    {
        return [csrf_token() => csrf_hash()];
    }

    private function creer(string $code, ?int $parentH = null): int
    {
        return (int) $this->entites->insert(['code' => $code, 'nom' => 'Entité ' . $code, 'type_entite_id' => $this->type, 'parent_hierarchique_id' => $parentH]);
    }

    private function formulaireEntite(array $valeurs): array
    {
        // Union de tableaux : la première valeur d'une clé l'emporte, d'où $valeurs avant les valeurs par défaut
        return $this->jeton() + $valeurs + ['_soumis' => '1', 'type_entite_id' => (string) $this->type, 'parent_hierarchique_id' => '', 'parent_logistique_id' => '', 'actif' => '1'];
    }

    // Accès

    public function testUnVisiteurEstRedirigeVersLaConnexion(): void
    {
        $this->get('organisation/entites')->assertRedirectTo('/login');
    }

    public function testUnUtilisateurConnecteConsulteLArbre(): void
    {
        $racine = $this->creer('RACINE');
        $this->creer('FILLE', $racine);

        $result = $this->actingAs($this->lecteur)->get('organisation/entites');

        $result->assertOK();
        $result->assertSee('Entité RACINE');
        $result->assertSee('Entité FILLE');
        $result->assertDontSee('Nouvelle entité');
    }

    public function testUnUtilisateurSansPermissionNePeutRienModifier(): void
    {
        $result = $this->actingAs($this->lecteur)->get('organisation/entites/new');
        $result->assertRedirect();
        $result->assertSessionHas('erreur');

        $result = $this->actingAs($this->lecteur)->post('organisation/entites', $this->formulaireEntite(['code' => 'PIRATE', 'nom' => 'Pirate']));

        $result->assertRedirect();
        $result->assertSessionHas('erreur');
        $this->dontSeeInDatabase('entite', ['code' => 'PIRATE']);
    }

    public function testUneEntiteInexistanteRenvoie404(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->actingAs($this->lecteur)->get('organisation/entites/9999');
    }

    // Entités

    public function testUnAdministrateurCreeUneEntite(): void
    {
        $racine = $this->creer('RACINE');

        $result = $this->actingAs($this->admin)->post('organisation/entites', $this->formulaireEntite([
            'code' => 'nouvelle', 'nom' => 'Nouvelle entité', 'parent_hierarchique_id' => (string) $racine,
        ]));

        $entite = $this->entites->where('code', 'NOUVELLE')->first();
        $this->assertNotNull($entite, 'le code est mis en majuscules');
        $this->assertSame($racine, $entite['parent_hierarchique_id']);
        $this->assertNull($entite['parent_logistique_id'], 'un parent non choisi vaut NULL');
        $result->assertRedirectTo('/organisation/entites/' . $entite['id']);
    }

    public function testUnCodeEnDoubleEstRefuse(): void
    {
        $this->creer('DOUBLON');

        $result = $this->actingAs($this->admin)->post('organisation/entites', $this->formulaireEntite(['code' => 'DOUBLON', 'nom' => 'Autre']));

        $result->assertRedirect();
        $result->assertSessionHas('erreurs');
        $this->assertSame(1, $this->entites->where('code', 'DOUBLON')->countAllResults());
    }

    public function testUnCycleEstRefuseAvecUnMessageClair(): void
    {
        $a = $this->creer('A');
        $b = $this->creer('B', $a);

        $result = $this->actingAs($this->admin)->post("organisation/entites/{$a}", $this->formulaireEntite([
            'code' => 'A', 'nom' => 'Entité A', 'parent_hierarchique_id' => (string) $b,
        ]));

        $result->assertSessionHas('erreurs');
        $this->assertStringContainsString('cycle', implode(' ', session('erreurs')));
        $this->assertNull($this->entites->find($a)['parent_hierarchique_id']);
    }

    public function testDecocherActifDesactiveLEntite(): void
    {
        $a = $this->creer('A');

        $donnees = $this->formulaireEntite(['code' => 'A', 'nom' => 'Entité A']);
        unset($donnees['actif']);
        $this->actingAs($this->admin)->post("organisation/entites/{$a}", $donnees);

        $this->assertFalse($this->entites->find($a)['actif']);
    }

    public function testLaFicheAfficheLesDeuxChaines(): void
    {
        $dcma  = $this->creer('DCMA');
        $force = $this->creer('FORCE');
        $unite = (int) $this->entites->insert(['code' => 'U1', 'nom' => 'Unité 1', 'type_entite_id' => $this->type, 'parent_hierarchique_id' => $force, 'parent_logistique_id' => $dcma]);

        $result = $this->actingAs($this->lecteur)->get("organisation/entites/{$unite}");

        $result->assertOK();
        $result->assertSee('Entité FORCE');
        $result->assertSee('Entité DCMA');
        $result->assertSee('Niveau national (aucune force)');
    }

    // Forces

    public function testCreerUneForceYRattacheLesEntitesSubordonnees(): void
    {
        $racine = $this->creer('TERRE');
        $unite  = $this->creer('U1', $racine);

        $this->actingAs($this->admin)->post('organisation/forces', $this->jeton() + [
            'code' => 'TERRE', 'nom' => 'Armée de Terre (fictif)', 'entite_racine_id' => (string) $racine,
            'validation_finale_locale' => '1', 'seuil_quantite_dcma' => '100,5', 'seuil_montant_dcma' => '',
        ])->assertRedirectTo('/organisation/forces');

        $force = $this->db->table('force')->where('code', 'TERRE')->get()->getRowArray();
        $this->assertSame('100.500', $force['seuil_quantite_dcma'], 'la virgule décimale est acceptée');
        $this->assertNull($force['seuil_montant_dcma']);
        $this->assertSame((int) $force['id'], $this->entites->find($unite)['force_id']);
    }

    public function testUneEntiteNePeutEtreRacineQueDUneForce(): void
    {
        $racine = $this->creer('TERRE');
        $donnees = ['code' => 'F1', 'nom' => 'F1', 'entite_racine_id' => (string) $racine];
        $this->actingAs($this->admin)->post('organisation/forces', $this->jeton() + $donnees);

        $result = $this->actingAs($this->admin)->post('organisation/forces', $this->jeton() + ['code' => 'F2'] + $donnees);

        $result->assertSessionHas('erreurs');
        $this->dontSeeInDatabase('force', ['code' => 'F2']);
    }

    // Sites et emplacements

    public function testCreerUnSiteEtSesEmplacements(): void
    {
        $depot = $this->creer('DEPOT');

        $this->actingAs($this->admin)->post('organisation/sites', $this->jeton() + [
            'code' => 'S1', 'nom' => 'Site fictif', 'adresse' => '', 'entite_id' => (string) $depot, 'actif' => '1',
        ]);
        $site = (new SiteModel())->where('code', 'S1')->first();
        $this->assertNotNull($site);

        $this->actingAs($this->admin)->post('organisation/emplacements', $this->jeton() + [
            'site_id' => (string) $site['id'], 'parent_id' => '', 'code' => 'MAG', 'libelle' => 'Magasin', 'actif' => '1',
        ]);
        $magasin = (new EmplacementModel())->where('code', 'MAG')->first();

        $this->actingAs($this->admin)->post('organisation/emplacements', $this->jeton() + [
            'site_id' => (string) $site['id'], 'parent_id' => (string) $magasin['id'], 'code' => 'R1', 'libelle' => 'Rayon 1', 'actif' => '1',
        ])->assertRedirectTo('/organisation/sites/' . $site['id']);

        $result = $this->actingAs($this->lecteur)->get('organisation/sites/' . $site['id']);
        $result->assertOK();
        $result->assertSee('Magasin');
        $result->assertSee('Rayon 1');
    }

    public function testUnEmplacementDUnAutreSiteEstRefuse(): void
    {
        $depot   = $this->creer('DEPOT');
        $sites   = new SiteModel();
        $site1   = $sites->insert(['code' => 'S1', 'nom' => 'Site 1', 'entite_id' => $depot]);
        $site2   = $sites->insert(['code' => 'S2', 'nom' => 'Site 2', 'entite_id' => $depot]);
        $magasin = (new EmplacementModel())->insert(['site_id' => $site1, 'code' => 'MAG', 'libelle' => 'Magasin']);

        $result = $this->actingAs($this->admin)->post('organisation/emplacements', $this->jeton() + [
            'site_id' => (string) $site2, 'parent_id' => (string) $magasin, 'code' => 'X', 'libelle' => 'X', 'actif' => '1',
        ]);

        $result->assertSessionHas('erreurs');
        $this->dontSeeInDatabase('emplacement', ['code' => 'X']);
    }
}
