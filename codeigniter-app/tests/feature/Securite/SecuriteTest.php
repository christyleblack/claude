<?php

namespace Tests\Feature\Securite;

use App\Libraries\SaisieMouvements;
use App\Models\Droits\AffectationModel;
use App\Models\Droits\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use DomainException;
use Tests\Support\Stocks\JeuDeStocks;
use Tests\Support\TestCase;

/**
 * Sécurité du lot 1 : double facteur pour les rôles sensibles (section 4.3), droit
 * supplémentaire pour mouvementer le matériel sensible (ABAC, section 4.2-2) et
 * journal des consultations de données sensibles (section 5).
 *
 * @internal
 */
final class SecuriteTest extends TestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use JeuDeStocks;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    private const MOT_DE_PASSE = 'Tournesol-Bleu-2026';

    /** Rôle sensible, connexion confirmée par un code. */
    private int $roleAdmin;
    private User $admin;

    /** Magasinier sans le droit sur le matériel sensible. */
    private User $magCourant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creerJeuDeStocks();

        $roles           = new RoleModel();
        $this->roleAdmin = (int) $roles->insert(['code' => 'ADMIN', 'libelle' => 'Administrateur', 'portee' => 'nationale', 'double_facteur' => true]);
        $roles->definirPermissions($this->roleAdmin, ['audit.consulter']);
        $courant = (int) $roles->insert(['code' => 'MAG_COURANT', 'libelle' => 'Magasinier (matériel courant)', 'portee' => 'entite']);
        $roles->definirPermissions($courant, ['stocks.mouvementer']);

        $this->admin      = fake(UserModel::class, ['username' => 'admin', 'active' => 1]);
        $this->magCourant = fake(UserModel::class, ['username' => 'mag.courant', 'active' => 1]);
        $affectations     = new AffectationModel();
        $affectations->insert(['utilisateur_id' => $this->admin->id, 'role_id' => $this->roleAdmin, 'entite_id' => $this->id['NATIONAL'], 'date_debut' => date('Y-m-d')]);
        $affectations->insert(['utilisateur_id' => $this->magCourant->id, 'role_id' => $courant, 'entite_id' => $this->id['TERRE-D1'], 'date_debut' => date('Y-m-d')]);

        $this->admin->createEmailIdentity(['email' => 'admin@example.com', 'password' => self::MOT_DE_PASSE]);
        $this->magTerre->createEmailIdentity(['email' => 'mag.terre@example.com', 'password' => self::MOT_DE_PASSE]);
    }

    private function jeton(): array
    {
        return [csrf_token() => csrf_hash()];
    }

    private function seConnecter(string $email)
    {
        $resultat = $this->post('login', $this->jeton() + ['email' => $email, 'password' => self::MOT_DE_PASSE]);
        $this->redevenirProprietaire();

        return $resultat;
    }

    /**
     * Requête suivante dans la même session (le trait de test repart sinon d'une session vide).
     */
    private function etape(): self
    {
        return $this->withSession($_SESSION);
    }

    private function code(User $user): string
    {
        return $this->db->table('auth_identities')->where(['user_id' => $user->id, 'type' => 'email_2fa'])->get()->getRow()->secret;
    }

    /**
     * Saisie comme le ferait l'application, sous le rôle cloisonné de la base,
     * sans aucun contrôle PHP propre au matériel sensible.
     */
    private function saisirCommeApplication(User $user, array $saisie): void
    {
        $this->db->query('SET ROLE gestion_app');
        $this->db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) $user->id]);

        try {
            (new SaisieMouvements($this->db))->enregistrer($saisie);
        } finally {
            $this->redevenirProprietaire();
        }
    }

    private function entreeMunitions(User $user, string $quantite = '100'): void
    {
        $this->saisirCommeApplication($user, [
            'type' => 'entree', 'codification' => 'MUN', 'numero_lot' => 'LOT-1', 'date_peremption' => '2031-01-31',
            'quantite' => $quantite, 'destination' => (string) $this->id['T-R1'],
        ]);
    }

    private function consultations(): array
    {
        return $this->db->table('journal_audit')->where('action', 'CONSULTATION')->orderBy('id')->get()->getResultArray();
    }

    // Double facteur (section 4.3)

    public function testUnRoleSensibleConfirmeLaConnexionParUnCode(): void
    {
        $this->seConnecter('admin@example.com')->assertRedirectTo('/auth/a/show');
        $this->assertFalse(auth()->loggedIn(), 'pas connecté avant le code');
        $this->assertTrue(auth()->getAuthenticator()->isPending());

        $this->etape()->get('auth/a/show')->assertSee('Confirmer la connexion');
        $this->etape()->post('auth/a/handle', $this->jeton() + ['email' => 'admin@example.com'])->assertSee('Code de confirmation');

        // Un mauvais code est refusé, la connexion reste en attente
        $this->etape()->post('auth/a/verify', $this->jeton() + ['token' => '000000'])->assertSee('Code de confirmation');
        $this->assertFalse(auth()->loggedIn());

        $this->etape()->post('auth/a/verify', $this->jeton() + ['token' => $this->code($this->admin)])->assertRedirect();
        $this->redevenirProprietaire();
        $this->assertTrue(auth()->loggedIn());
        $this->assertSame((string) $this->admin->id, (string) auth()->id());
    }

    public function testLeCodeEstDemandePourLeBonUtilisateur(): void
    {
        $this->seConnecter('admin@example.com');
        $this->etape()->post('auth/a/handle', $this->jeton() + ['email' => 'mag.terre@example.com'])->assertRedirectTo('/auth/a/show');
        $this->assertFalse(auth()->loggedIn());
    }

    public function testUnRoleCourantSeConnecteSansCode(): void
    {
        $this->seConnecter('mag.terre@example.com')->assertRedirect();
        $this->assertTrue(auth()->loggedIn());
        $this->assertFalse(auth()->getAuthenticator()->isPending());
    }

    public function testLeDoubleFacteurSeParametreSurLeRole(): void
    {
        (new RoleModel())->update($this->roleAdmin, ['double_facteur' => false]);

        $this->seConnecter('admin@example.com');
        $this->assertTrue(auth()->loggedIn(), 'rôle non sensible : pas de code');
    }

    public function testUnRoleSensibleRecuParDelegationExigeAussiLeCode(): void
    {
        $affectation = (int) $this->db->table('affectation')->where('utilisateur_id', $this->admin->id)->get()->getRow()->id;
        $this->assertSame(0, (int) $this->db->query('SELECT securite_double_facteur_requis(?)::int AS r', [$this->magTerre->id])->getRow()->r);

        $this->db->table('delegation')->insert([
            'affectation_id' => $affectation, 'delegataire_id' => $this->magTerre->id,
            'date_debut' => date('Y-m-d'), 'date_fin' => date('Y-m-d', strtotime('+7 days')), 'motif' => 'Intérim fictif',
        ]);

        $this->seConnecter('mag.terre@example.com')->assertRedirectTo('/auth/a/show');
        $this->assertFalse(auth()->loggedIn());
    }

    public function testLeRoleSeDeclareSensibleDepuisLAdministration(): void
    {
        $roles = new RoleModel();
        $admin = (int) $roles->insert(['code' => 'ADMIN_ROLES', 'libelle' => 'Admin rôles', 'portee' => 'nationale']);
        $roles->definirPermissions($admin, ['administration.roles']);
        $gestion = fake(UserModel::class, ['username' => 'gestion.roles', 'active' => 1]);
        (new AffectationModel())->insert(['utilisateur_id' => $gestion->id, 'role_id' => $admin, 'entite_id' => $this->id['NATIONAL'], 'date_debut' => date('Y-m-d')]);
        $magasinier = (int) $this->db->table('role')->where('code', 'MAG')->get()->getRow()->id;

        $this->actingAs($gestion)->post("administration/roles/{$magasinier}", $this->jeton() + [
            '_soumis' => '1', 'code' => 'MAG', 'libelle' => 'Magasinier', 'portee' => 'entite', 'actif' => '1', 'double_facteur' => '1',
            'permissions' => ['stocks.mouvementer', 'stocks.sensibles'],
        ])->assertRedirectTo('/administration/roles');
        $this->redevenirProprietaire();

        $this->assertTrue((new RoleModel())->find($magasinier)['double_facteur']);
        $this->actingAs($gestion)->get('administration/roles')->assertSee('double facteur');
    }

    // Matériel sensible (ABAC, section 4.2-2)

    public function testLeMaterielSensibleExigeUnDroitSupplementaire(): void
    {
        try {
            $this->entreeMunitions($this->magCourant);
            $this->fail('L\'entrée de munitions aurait dû être refusée.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('matériel sensible', $e->getMessage());
        }
        $this->assertSame(0.0, $this->stock('MUN', 'T-R1'));

        // Le matériel courant reste permis, et le magasinier habilité saisit les munitions
        $this->saisirCommeApplication($this->magCourant, ['type' => 'entree', 'codification' => 'GASOIL', 'quantite' => '50', 'destination' => (string) $this->id['T-R1']]);
        $this->entreeMunitions($this->magTerre);
        $this->assertSame(50.0, $this->stock('GASOIL', 'T-R1'));
        $this->assertSame(100.0, $this->stock('MUN', 'T-R1'));

        // Ni sortie ni annulation de munitions sans le droit
        $this->expectException(DomainException::class);
        $this->saisirCommeApplication($this->magCourant, ['type' => 'sortie', 'codification' => 'MUN', 'numero_lot' => 'LOT-1', 'quantite' => '10', 'origine' => (string) $this->id['T-R1']]);
    }

    public function testLeRefusEstAfficheALaSaisie(): void
    {
        $this->actingAs($this->magCourant)->post('stocks/mouvements', $this->jeton() + [
            'type' => 'entree', 'codification' => 'MUN', 'numero_lot' => 'LOT-1', 'date_peremption' => '2031-01-31',
            'quantite' => '100', 'destination' => (string) $this->id['T-R1'],
        ])->assertSessionHas('erreurs', ['Mouvement refusé : cet article est du matériel sensible (armement, munitions…) ; il faut le droit « Mouvementer le matériel sensible » sur cette entité.']);
    }

    public function testLaRegularisationDInventaireNExigePasLeDroitSensible(): void
    {
        $this->entreeMunitions($this->magTerre);

        $roles = new RoleModel();
        $chef  = (int) $roles->insert(['code' => 'CHEF', 'libelle' => 'Chef de dépôt', 'portee' => 'entite']);
        $roles->definirPermissions($chef, ['inventaires.gerer', 'inventaires.valider']);
        $chefTerre = fake(UserModel::class, ['username' => 'chef.terre', 'active' => 1]);
        (new AffectationModel())->insert(['utilisateur_id' => $chefTerre->id, 'role_id' => $chef, 'entite_id' => $this->id['TERRE-D1'], 'date_debut' => date('Y-m-d')]);

        $this->executerCommeApplication($chefTerre->id, 'INSERT INTO inventaire (type, entite_id, emplacement_id, date_prevue, compteur_id, cree_par) VALUES (?, ?, ?, current_date, ?, ?)', [
            'tournant', $this->id['TERRE-D1'], $this->id['T-R1'], $this->magCourant->id, $chefTerre->id,
        ]);
        $inventaire = (int) $this->db->table('inventaire')->selectMax('id')->get()->getRow()->id;
        $this->executerCommeApplication($chefTerre->id, 'SELECT inventaire_lancer(?)', [$inventaire]);
        $ligne = (int) $this->db->table('ligne_inventaire')->where('inventaire_id', $inventaire)->get()->getRow()->id;
        $this->executerCommeApplication($this->magCourant->id, 'SELECT inventaire_compter(?, 90)', [$ligne]);
        $this->executerCommeApplication($this->magCourant->id, 'SELECT inventaire_terminer_comptage(?)', [$inventaire]);
        $this->executerCommeApplication($chefTerre->id, 'SELECT inventaire_valider(?)', [$inventaire]);

        $this->assertSame(90.0, $this->stock('MUN', 'T-R1'));
    }

    // Journal des consultations (section 5)

    public function testLaConsultationDuMaterielSensibleEstJournalisee(): void
    {
        $this->entreeMunitions($this->magTerre);
        $this->saisirCommeApplication($this->magTerre, ['type' => 'sortie', 'codification' => 'MUN', 'numero_lot' => 'LOT-1', 'quantite' => '10', 'origine' => (string) $this->id['T-R1']]);
        $this->saisirCommeApplication($this->magTerre, ['type' => 'entree', 'codification' => 'GASOIL', 'quantite' => '50', 'destination' => (string) $this->id['T-R1']]);

        $munitions = $this->db->table('mouvement')->where('article_id', $this->id['MUN'])->orderBy('id')->get()->getResultArray();
        $gasoil    = $this->db->table('mouvement')->where('article_id', $this->id['GASOIL'])->get()->getRow()->id;
        $bon       = $this->db->table('bon_mouvement')->where('mouvement_id', $munitions[1]['id'])->get()->getRow()->bon_id;

        $this->actingAs($this->magTerre)->get('stocks/mouvements/' . $gasoil);
        $this->assertSame([], $this->consultations(), 'matériel courant : pas de trace');

        $this->actingAs($this->magTerre)->get('stocks/mouvements/' . $munitions[0]['id']);
        $this->actingAs($this->magTerre)->get('stocks/bons/' . $bon . '.pdf');
        $this->redevenirProprietaire();

        $consultations = $this->consultations();
        $this->assertSame([['mouvement', (string) $munitions[0]['id']], ['bon', (string) $bon]], array_map(static fn ($c) => [$c['objet_type'], (string) $c['objet_id']], $consultations));
        $this->assertSame((string) $this->magTerre->id, (string) $consultations[0]['utilisateur_id']);
    }

    public function testLaConsultationDUnMaterielSerialiseSensibleEstJournalisee(): void
    {
        $this->db->table('article')->where('id', $this->id['VEH'])->update(['sensibilite' => 'sensible']);
        $this->saisirCommeApplication($this->magTerre, ['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => 'V-1', 'destination' => (string) $this->id['T-R1']]);
        $materiel = (int) $this->db->table('materiel_serialise')->where('numero_serie', 'V-1')->get()->getRow()->id;

        $this->actingAs($this->magTerre)->get('stocks/materiels/' . $materiel)->assertSee('V-1');
        $this->redevenirProprietaire();

        $this->assertSame(['materiel_serialise'], array_column($this->consultations(), 'objet_type'));
    }
}
