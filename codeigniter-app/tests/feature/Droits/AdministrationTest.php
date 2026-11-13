<?php

namespace Tests\Feature\Droits;

use App\Models\Droits\AffectationModel;
use App\Models\Droits\DelegationModel;
use App\Models\Droits\RoleModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\TypeEntiteModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\TestCase;

/**
 * Pages d'administration : affectations (ADM-01), rôles (ADM-02), journal (ADM-03),
 * « Mes droits » et délégations (règle 4.2-5).
 *
 * @internal
 */
final class AdministrationTest extends TestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    private int $national;
    private int $unite;
    private int $roleAdmin;
    private int $roleDemandeur;
    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $type           = (new TypeEntiteModel())->insert(['code' => 'UNITE', 'libelle' => 'Unité']);
        $entites        = new EntiteModel();
        $this->national = (int) $entites->insert(['code' => 'NATIONAL', 'nom' => 'National', 'type_entite_id' => $type]);
        $this->unite    = (int) $entites->insert(['code' => 'U1', 'nom' => 'Unité 1', 'type_entite_id' => $type, 'parent_hierarchique_id' => $this->national]);

        $roles           = new RoleModel();
        $this->roleAdmin = (int) $roles->insert(['code' => 'ADMIN', 'libelle' => 'Administrateur', 'portee' => 'nationale']);
        $roles->definirPermissions($this->roleAdmin, ['administration.utilisateurs', 'administration.roles', 'audit.consulter']);
        $this->roleDemandeur = (int) $roles->insert(['code' => 'DEMANDEUR', 'libelle' => 'Demandeur', 'portee' => 'entite']);

        $this->admin = fake(UserModel::class, ['username' => 'admin', 'active' => 1]);
        $this->agent = fake(UserModel::class, ['username' => 'agent', 'active' => 1]);
        (new AffectationModel())->insert(['utilisateur_id' => $this->admin->id, 'role_id' => $this->roleAdmin, 'entite_id' => $this->national, 'date_debut' => date('Y-m-d')]);
    }

    private function jeton(): array
    {
        return [csrf_token() => csrf_hash()];
    }

    public function testUnUtilisateurSansPermissionNAccedePasALAdministration(): void
    {
        foreach (['administration/utilisateurs', 'administration/roles', 'administration/journal'] as $page) {
            $result = $this->actingAs($this->agent)->get($page);
            $result->assertRedirect();
            $result->assertSessionHas('erreur');
        }
    }

    public function testLAdministrateurAffecteUnRoleDate(): void
    {
        $this->actingAs($this->admin)->get('administration/utilisateurs')->assertSee('agent');

        $this->actingAs($this->admin)->post("administration/utilisateurs/{$this->agent->id}/affectations", $this->jeton() + [
            'role_id' => (string) $this->roleDemandeur, 'entite_id' => (string) $this->unite, 'date_debut' => date('Y-m-d'), 'date_fin' => '',
        ])->assertRedirectTo("/administration/utilisateurs/{$this->agent->id}");

        $affectation = (new AffectationModel())->where('utilisateur_id', $this->agent->id)->first();
        $this->assertSame($this->unite, $affectation['entite_id']);
        $this->assertNull($affectation['date_fin']);
        $this->assertSame($this->admin->id, (int) $affectation['cree_par']);

        $this->actingAs($this->admin)->get("administration/utilisateurs/{$this->agent->id}")->assertSee('Demandeur');
    }

    public function testRevoquerUneAffectationRetireLesDroitsImmediatement(): void
    {
        $id = (int) (new AffectationModel())->insert(['utilisateur_id' => $this->agent->id, 'role_id' => $this->roleDemandeur, 'entite_id' => $this->unite, 'date_debut' => date('Y-m-d')]);
        $this->actingAs($this->agent)->get('organisation/entites')->assertSee('Unité 1');

        $this->actingAs($this->admin)->post("administration/affectations/{$id}/revoquer", $this->jeton());

        $this->assertNotNull((new AffectationModel())->find($id)['revoquee_le'], 'l\'affectation reste dans l\'historique');
        $this->actingAs($this->agent)->get('organisation/entites')->assertDontSee('Unité 1');
    }

    public function testLesPermissionsDUnRoleSeParametrent(): void
    {
        $this->actingAs($this->admin)->post("administration/roles/{$this->roleDemandeur}", $this->jeton() + [
            '_soumis' => '1', 'code' => 'DEMANDEUR', 'libelle' => 'Demandeur', 'portee' => 'entite', 'actif' => '1',
            'permissions' => ['organisation.gerer', 'permission.inventee'],
        ])->assertRedirectTo('/administration/roles');

        $this->assertSame(['organisation.gerer'], (new RoleModel())->permissions($this->roleDemandeur), 'une permission inconnue est ignorée');
    }

    public function testLeJournalTraceLesConnexions(): void
    {
        $this->agent->createEmailIdentity(['email' => 'agent@example.com', 'password' => 'Tournesol-Bleu-2026']);
        $this->post('login', $this->jeton() + ['email' => 'agent@example.com', 'password' => 'mauvais-mot-de-passe']);
        $this->post('login', $this->jeton() + ['email' => 'agent@example.com', 'password' => 'Tournesol-Bleu-2026']);
        $this->redevenirProprietaire();

        $this->seeInDatabase('journal_audit', ['action' => 'ECHEC_CONNEXION']);
        $this->seeInDatabase('journal_audit', ['action' => 'CONNEXION', 'utilisateur_id' => $this->agent->id]);

        // L'agent est maintenant connecté : on repart d'une session vide pour consulter en administrateur
        $_SESSION = [];
        $this->resetServices();
        $result = $this->actingAs($this->admin)->get('administration/journal?action=connexion');
        $result->assertOK();
        $result->assertSee('CONNEXION');
    }

    public function testLeJournalExigeUnePorteeNationale(): void
    {
        $auditeurLocal = (int) (new RoleModel())->insert(['code' => 'AUDIT_LOCAL', 'libelle' => 'Auditeur local', 'portee' => 'entite']);
        (new RoleModel())->definirPermissions($auditeurLocal, ['audit.consulter']);
        (new AffectationModel())->insert(['utilisateur_id' => $this->agent->id, 'role_id' => $auditeurLocal, 'entite_id' => $this->unite, 'date_debut' => date('Y-m-d')]);

        $result = $this->actingAs($this->agent)->get('administration/journal');

        $result->assertRedirect();
        $result->assertSessionHas('erreur');
    }

    public function testLeTitulaireDelegueEtRevoqueSonAffectation(): void
    {
        $affectation = (int) (new AffectationModel())->insert(['utilisateur_id' => $this->agent->id, 'role_id' => $this->roleDemandeur, 'entite_id' => $this->unite, 'date_debut' => date('Y-m-d')]);
        $adjoint     = fake(UserModel::class, ['username' => 'adjoint', 'active' => 1]);

        $this->actingAs($this->agent)->get('administration/mes-droits')->assertSee('Déléguer');
        $this->actingAs($this->agent)->post('administration/delegations', $this->jeton() + [
            'affectation_id' => (string) $affectation, 'delegataire_id' => (string) $adjoint->id,
            'date_debut' => date('Y-m-d'), 'date_fin' => date('Y-m-d', strtotime('+5 days')), 'motif' => 'Absence',
        ])->assertRedirectTo('/administration/mes-droits');

        $this->actingAs($adjoint)->get('administration/mes-droits')->assertSee('par délégation');

        $delegation = (new DelegationModel())->where('delegataire_id', $adjoint->id)->first();
        $this->actingAs($this->agent)->post("administration/delegations/{$delegation['id']}/revoquer", $this->jeton());
        $this->assertNotNull((new DelegationModel())->find($delegation['id'])['revoquee_le']);
    }

    public function testOnNeDeleguePasLAffectationDUnAutre(): void
    {
        $affectation = (int) (new AffectationModel())->insert(['utilisateur_id' => $this->agent->id, 'role_id' => $this->roleDemandeur, 'entite_id' => $this->unite, 'date_debut' => date('Y-m-d')]);
        $intrus      = fake(UserModel::class, ['username' => 'intrus', 'active' => 1]);

        $this->actingAs($intrus)->post('administration/delegations', $this->jeton() + [
            'affectation_id' => (string) $affectation, 'delegataire_id' => (string) $intrus->id,
            'date_debut' => date('Y-m-d'), 'date_fin' => date('Y-m-d'), 'motif' => 'Usurpation',
        ])->assertSessionHas('erreur');

        $this->assertSame(0, (new DelegationModel())->countAllResults());
    }
}
