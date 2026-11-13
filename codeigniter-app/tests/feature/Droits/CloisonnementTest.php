<?php

namespace Tests\Feature\Droits;

use App\Models\Droits\AffectationModel;
use App\Models\Droits\DelegationModel;
use App\Models\Droits\RoleModel;
use App\Models\Organisation\EmplacementModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\SiteModel;
use App\Models\Organisation\TypeEntiteModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\TestCase;

/**
 * Critère de recette 7.3-2 : un utilisateur ne voit ni ne modifie aucune donnée hors
 * de son périmètre. Vérifié par l'interface et directement en base (règle 4.2-6).
 *
 * Organisation fictive : NATIONAL ; TERRE › TERRE-U1 (site, emplacement) ; AIR › AIR-U1 (site).
 *
 * @internal
 */
final class CloisonnementTest extends TestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    private array $id = [];
    private int $roleGestion;
    private User $terre;
    private EntiteModel $entites;

    protected function setUp(): void
    {
        parent::setUp();

        $type          = (new TypeEntiteModel())->insert(['code' => 'UNITE', 'libelle' => 'Unité']);
        $this->entites = new EntiteModel();
        foreach ([['NATIONAL', null], ['TERRE', null], ['TERRE-U1', 'TERRE'], ['AIR', null], ['AIR-U1', 'AIR']] as [$code, $parent]) {
            $this->id[$code] = (int) $this->entites->insert([
                'code' => $code, 'nom' => 'Entité ' . $code, 'type_entite_id' => $type,
                'parent_hierarchique_id' => $parent === null ? null : $this->id[$parent],
            ]);
        }
        $sites = new SiteModel();
        $this->id['SITE-TERRE'] = (int) $sites->insert(['code' => 'SITE-TERRE', 'nom' => 'Site Terre', 'entite_id' => $this->id['TERRE-U1']]);
        $this->id['SITE-AIR']   = (int) $sites->insert(['code' => 'SITE-AIR', 'nom' => 'Site Air', 'entite_id' => $this->id['AIR-U1']]);
        (new EmplacementModel())->insert(['site_id' => $this->id['SITE-TERRE'], 'code' => 'MAG', 'libelle' => 'Magasin Terre']);
        (new EmplacementModel())->insert(['site_id' => $this->id['SITE-AIR'], 'code' => 'MAG', 'libelle' => 'Magasin Air']);

        $roles             = new RoleModel();
        $this->roleGestion = (int) $roles->insert(['code' => 'GEST', 'libelle' => 'Gestionnaire', 'portee' => 'entite']);
        $roles->definirPermissions($this->roleGestion, ['organisation.gerer']);

        $this->terre = $this->utilisateur('terre');
        $this->affecter($this->terre, 'TERRE');
    }

    private function utilisateur(string $nom): User
    {
        return fake(UserModel::class, ['username' => $nom, 'active' => 1]);
    }

    private function affecter(User $u, string $entite, array $dates = []): int
    {
        return (int) (new AffectationModel())->insert($dates + [
            'utilisateur_id' => $u->id, 'role_id' => $this->roleGestion, 'entite_id' => $this->id[$entite], 'date_debut' => date('Y-m-d'),
        ]);
    }

    private function jeton(): array
    {
        return [csrf_token() => csrf_hash()];
    }

    /**
     * Exécute une requête SQL comme le ferait l'application pour cet utilisateur,
     * sans aucun filtre PHP : seul le cloisonnement de la base s'applique.
     */
    private function commeApplication(User $u, string $sql): array
    {
        $this->db->query('SET ROLE gestion_app');
        $this->db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) $u->id]);

        try {
            $resultat = $this->db->query($sql);

            // Une requête d'écriture renvoie un booléen, pas un jeu de résultats
            return is_bool($resultat) ? [] : $resultat->getResultArray();
        } finally {
            $this->db->query('RESET ROLE');
            $this->db->query("SELECT set_config('app.utilisateur_id', '', false)");
        }
    }

    private function codesVisibles(User $u): array
    {
        return array_column($this->commeApplication($u, 'SELECT code FROM entite ORDER BY code'), 'code');
    }

    // Lecture

    public function testLArbreNeMontreQueLePerimetre(): void
    {
        $result = $this->actingAs($this->terre)->get('organisation/entites');

        $result->assertSee('Entité TERRE-U1');
        $result->assertDontSee('Entité AIR');
        $result->assertDontSee('Entité NATIONAL');
    }

    public function testLaBaseNeRenvoieQueLePerimetreMemeSansFiltreApplicatif(): void
    {
        $this->assertSame(['TERRE', 'TERRE-U1'], $this->codesVisibles($this->terre));
        $this->assertSame(['SITE-TERRE'], array_column($this->commeApplication($this->terre, 'SELECT code FROM site'), 'code'));
        $this->assertSame(['Magasin Terre'], array_column($this->commeApplication($this->terre, 'SELECT libelle FROM emplacement'), 'libelle'));
    }

    public function testUneEntiteHorsPerimetreEstIntrouvable(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->actingAs($this->terre)->get('organisation/entites/' . $this->id['AIR-U1']);
    }

    public function testUnSiteHorsPerimetreEstIntrouvable(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->actingAs($this->terre)->get('organisation/sites/' . $this->id['SITE-AIR']);
    }

    // Écriture

    public function testModifierUneEntiteHorsPerimetreEchoue(): void
    {
        try {
            $this->actingAs($this->terre)->post('organisation/entites/' . $this->id['AIR-U1'], $this->jeton() + [
                'code' => 'AIR-U1', 'nom' => 'Piraté', 'type_entite_id' => '1', 'parent_hierarchique_id' => '', 'parent_logistique_id' => '',
            ]);
            $this->fail('La modification aurait dû être refusée.');
        } catch (PageNotFoundException) {
            // L'entité est introuvable pour cet utilisateur
            $this->redevenirProprietaire();
        }

        $this->assertSame('Entité AIR-U1', $this->entites->find($this->id['AIR-U1'])['nom']);
    }

    public function testLaBaseRefuseUneModificationHorsPerimetre(): void
    {
        $this->commeApplication($this->terre, "UPDATE entite SET nom = 'Piraté' WHERE code = 'AIR-U1'");

        $this->assertSame('Entité AIR-U1', $this->entites->find($this->id['AIR-U1'])['nom'], '0 ligne modifiée : la ligne est invisible');
    }

    public function testCreerSousUneEntiteHorsPerimetreEstRefuse(): void
    {
        $result = $this->actingAs($this->terre)->post('organisation/entites', $this->jeton() + [
            '_soumis' => '1', 'code' => 'INTRUS', 'nom' => 'Intrus', 'type_entite_id' => '1',
            'parent_hierarchique_id' => (string) $this->id['AIR-U1'], 'parent_logistique_id' => '', 'actif' => '1',
        ]);

        $result->assertSessionHas('erreurs');
        $this->dontSeeInDatabase('entite', ['code' => 'INTRUS']);
    }

    public function testSortirUneEntiteDeSonPerimetreEstRefuse(): void
    {
        $result = $this->actingAs($this->terre)->post('organisation/entites/' . $this->id['TERRE-U1'], $this->jeton() + [
            '_soumis' => '1', 'code' => 'TERRE-U1', 'nom' => 'Entité TERRE-U1', 'type_entite_id' => '1',
            'parent_hierarchique_id' => (string) $this->id['NATIONAL'], 'parent_logistique_id' => '', 'actif' => '1',
        ]);

        $result->assertSessionHas('erreurs');
        $this->assertSame($this->id['TERRE'], $this->entites->find($this->id['TERRE-U1'])['parent_hierarchique_id']);
    }

    public function testLaBaseRefuseDeSortirUneEntiteDuPerimetre(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('row-level security');

        $this->commeApplication($this->terre, "UPDATE entite SET parent_hierarchique_id = {$this->id['NATIONAL']} WHERE code = 'TERRE-U1'");
    }

    public function testCreerUnSiteHorsPerimetreEstRefuse(): void
    {
        $result = $this->actingAs($this->terre)->post('organisation/sites', $this->jeton() + [
            'code' => 'S-INTRUS', 'nom' => 'Intrus', 'adresse' => '', 'entite_id' => (string) $this->id['AIR-U1'], 'actif' => '1',
        ]);

        $result->assertSessionHas('erreurs');
        $this->dontSeeInDatabase('site', ['code' => 'S-INTRUS']);
    }

    public function testRienNeSeSupprimeMemeDansLePerimetre(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('permission denied');

        $this->commeApplication($this->terre, "DELETE FROM entite WHERE code = 'TERRE-U1'");
    }

    // Validité des affectations

    public function testSansAffectationRienNEstVisible(): void
    {
        $sans = $this->utilisateur('sans');

        $this->assertSame([], $this->codesVisibles($sans));
        $this->actingAs($sans)->get('organisation/entites')->assertSee('Aucune entité dans votre périmètre');
    }

    public function testUneAffectationTermineeAVenirOuRevoqueeNeDonneRien(): void
    {
        $terminee = $this->utilisateur('terminee');
        $this->affecter($terminee, 'AIR', ['date_debut' => date('Y-m-d', strtotime('-10 days')), 'date_fin' => date('Y-m-d', strtotime('-1 day'))]);
        $avenir = $this->utilisateur('avenir');
        $this->affecter($avenir, 'AIR', ['date_debut' => date('Y-m-d', strtotime('+1 day'))]);
        $revoquee = $this->utilisateur('revoquee');
        $id       = $this->affecter($revoquee, 'AIR');
        (new AffectationModel())->update($id, ['revoquee_le' => date('Y-m-d H:i:s')]);

        foreach ([$terminee, $avenir, $revoquee] as $u) {
            $this->assertSame([], $this->codesVisibles($u), $u->username);
        }
    }

    public function testUnRoleNationalVoitToutLePerimetre(): void
    {
        $directeur = $this->utilisateur('directeur');
        $role      = (int) (new RoleModel())->insert(['code' => 'DIR', 'libelle' => 'Directeur', 'portee' => 'nationale']);
        (new AffectationModel())->insert(['utilisateur_id' => $directeur->id, 'role_id' => $role, 'entite_id' => $this->id['NATIONAL'], 'date_debut' => date('Y-m-d')]);

        $this->assertSame(['AIR', 'AIR-U1', 'NATIONAL', 'TERRE', 'TERRE-U1'], $this->codesVisibles($directeur));
    }

    // Délégations (règle 4.2-5)

    public function testUneDelegationDonneTemporairementLePerimetre(): void
    {
        $adjoint     = $this->utilisateur('adjoint');
        $affectation = (int) (new AffectationModel())->where('utilisateur_id', $this->terre->id)->first()['id'];
        $delegations = new DelegationModel();
        $delegation  = $delegations->insert(['affectation_id' => $affectation, 'delegataire_id' => $adjoint->id, 'date_debut' => date('Y-m-d'), 'date_fin' => date('Y-m-d', strtotime('+7 days')), 'motif' => 'Congés']);

        $this->assertSame(['TERRE', 'TERRE-U1'], $this->codesVisibles($adjoint));

        $delegations->update($delegation, ['revoquee_le' => date('Y-m-d H:i:s')]);
        $this->assertSame([], $this->codesVisibles($adjoint));
    }

    public function testOnNePeutPasSeDeleguerSesPropresDroits(): void
    {
        $affectation = (int) (new AffectationModel())->where('utilisateur_id', $this->terre->id)->first()['id'];

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Délégation refusée');

        (new DelegationModel())->insert(['affectation_id' => $affectation, 'delegataire_id' => $this->terre->id, 'date_debut' => date('Y-m-d'), 'date_fin' => date('Y-m-d'), 'motif' => 'Test']);
    }

    public function testUneDelegationNePeutPasDepasserLAffectation(): void
    {
        $adjoint     = $this->utilisateur('adjoint');
        $affectation = $this->affecter($this->terre, 'AIR', ['date_fin' => date('Y-m-d', strtotime('+3 days'))]);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Délégation refusée');

        (new DelegationModel())->insert(['affectation_id' => $affectation, 'delegataire_id' => $adjoint->id, 'date_debut' => date('Y-m-d'), 'date_fin' => date('Y-m-d', strtotime('+10 days')), 'motif' => 'Trop long']);
    }

    // Journal d'audit

    public function testChaqueModificationEstJournaliseeAvecSonAuteur(): void
    {
        $this->actingAs($this->terre)->post('organisation/entites/' . $this->id['TERRE-U1'], $this->jeton() + [
            '_soumis' => '1', 'code' => 'TERRE-U1', 'nom' => 'Nouveau nom', 'type_entite_id' => '1',
            'parent_hierarchique_id' => (string) $this->id['TERRE'], 'parent_logistique_id' => '', 'actif' => '1',
        ]);

        $ligne = $this->db->table('journal_audit')->where(['objet_type' => 'entite', 'action' => 'UPDATE', 'objet_id' => $this->id['TERRE-U1']])->get()->getRowArray();
        $this->assertNotNull($ligne);
        $this->assertSame($this->terre->id, (int) $ligne['utilisateur_id']);
        $this->assertSame('Nouveau nom', json_decode($ligne['details'], true)['apres']['nom']);
    }

    public function testLeJournalEstImmuablePourLApplication(): void
    {
        $this->expectException(DatabaseException::class);

        $this->commeApplication($this->terre, "UPDATE journal_audit SET action = 'EFFACE'");
    }

    public function testLeJournalEstImmuableMemePourLeProprietaire(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('immuable');

        $this->db->query('DELETE FROM journal_audit');
    }
}
