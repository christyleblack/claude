<?php

namespace Tests\Feature\Demandes;

use App\Libraries\SaisieMouvements;
use App\Models\Droits\AffectationModel;
use App\Models\Droits\RoleModel;
use App\Models\Organisation\EmplacementModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\SiteModel;
use App\Models\Organisation\TypeEntiteModel;
use App\Models\Referentiel\ArticleModel;
use App\Models\Referentiel\CategorieModel;
use App\Models\Referentiel\FiliereModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\TestCase;

/**
 * Demandes et validations (DEM-01 à DEM-04, RG-15 à RG-19) : routage selon la chaîne
 * logistique et la filière, circuit paramétrable avec seuil, séparation des tâches,
 * service par perceptions, clôture, annulation, notifications et cloisonnement.
 *
 * Organisation fictive : NATIONAL › SERV-ESS (service de la filière Carburants) ;
 * TERRE › TERRE-DM (logistique : NATIONAL) › TERRE-D1 (dépôt, emplacement D-R1) ;
 * TERRE › TERRE-U1 (unité, logistique : TERRE-DM, emplacement U-R1) ; AIR › AIR-DM › AIR-U1.
 *
 * @internal
 */
final class DemandesTest extends TestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    private array $id = [];

    /** @var array<string, User> */
    private array $u = [];

    protected function setUp(): void
    {
        parent::setUp();

        $type    = (new TypeEntiteModel())->insert(['code' => 'UNITE', 'libelle' => 'Unité']);
        $entites = new EntiteModel();
        foreach ([
            ['NATIONAL', null, null], ['SERV-ESS', 'NATIONAL', 'NATIONAL'],
            ['TERRE', null, null], ['TERRE-DM', 'TERRE', 'NATIONAL'], ['TERRE-D1', 'TERRE-DM', 'TERRE-DM'], ['TERRE-U1', 'TERRE', 'TERRE-DM'],
            ['AIR', null, null], ['AIR-DM', 'AIR', 'NATIONAL'], ['AIR-U1', 'AIR', 'AIR-DM'],
        ] as [$code, $hierarchique, $logistique]) {
            $this->id[$code] = (int) $entites->insert([
                'code' => $code, 'nom' => 'Entité ' . $code, 'type_entite_id' => $type,
                'parent_hierarchique_id' => $hierarchique === null ? null : $this->id[$hierarchique],
                'parent_logistique_id'   => $logistique === null ? null : $this->id[$logistique],
            ]);
        }
        foreach ([['D-R1', 'TERRE-D1'], ['U-R1', 'TERRE-U1']] as [$code, $entite]) {
            $site            = (int) (new SiteModel())->insert(['code' => 'S-' . $code, 'nom' => 'Site ' . $code, 'entite_id' => $this->id[$entite]]);
            $this->id[$code] = (int) (new EmplacementModel())->insert(['site_id' => $site, 'code' => $code, 'libelle' => 'Emplacement ' . $code]);
        }

        $filieres   = new FiliereModel();
        $categories = new CategorieModel();
        $articles   = new ArticleModel();
        foreach ([['CARBU', 'SERV-ESS', 'GASOIL', 'quantite', 'L', 'courant'], ['ARMES', 'NATIONAL', 'FUSIL', 'serie', 'U', 'armement']] as [$filiere, $service, $article, $mode, $unite, $sensibilite]) {
            $this->id[$filiere] = (int) $filieres->insert(['code' => $filiere, 'libelle' => 'Filière ' . $filiere, 'service_gestionnaire_id' => $this->id[$service]]);
            $categorie          = (int) $categories->insert(['filiere_id' => $this->id[$filiere], 'code' => 'C-' . $article, 'libelle' => 'Catégorie ' . $article]);
            $this->id[$article] = (int) $articles->insert(['codification' => $article, 'designation' => 'Article ' . $article, 'unite_gestion' => $unite, 'filiere_id' => $this->id[$filiere], 'categorie_id' => $categorie, 'mode_suivi' => $mode, 'sensibilite' => $sensibilite]);
        }

        $roles = new RoleModel();
        foreach ([
            'DEMANDEUR'    => ['entite', ['demandes.saisir']],
            'CHEF'         => ['entite', ['demandes.valider']],
            'FILIERE'      => ['nationale', ['demandes.valider']],
            'MAG'          => ['entite', ['stocks.mouvementer']],
            'SAISIE_VALID' => ['entite', ['demandes.saisir', 'demandes.valider']],
            'PARAMETRAGE'  => ['nationale', ['demandes.circuits']],
        ] as $code => [$portee, $permissions]) {
            $this->id[$code] = (int) $roles->insert(['code' => $code, 'libelle' => $code, 'portee' => $portee]);
            $roles->definirPermissions($this->id[$code], $permissions);
        }

        $affectations = new AffectationModel();
        foreach ([
            ['demandeur.u1', 'DEMANDEUR', 'TERRE-U1'], ['chef.dm', 'CHEF', 'TERRE-DM'], ['gest.ess', 'FILIERE', 'NATIONAL'],
            ['mag.d1', 'MAG', 'TERRE-D1'], ['demandeur.air', 'DEMANDEUR', 'AIR-U1'], ['chef.air', 'CHEF', 'AIR-DM'],
            ['cumul.terre', 'SAISIE_VALID', 'TERRE'], ['parametreur', 'PARAMETRAGE', 'NATIONAL'],
        ] as [$nom, $role, $entite]) {
            $this->u[$nom] = fake(UserModel::class, ['username' => $nom, 'active' => 1]);
            $affectations->insert(['utilisateur_id' => $this->u[$nom]->id, 'role_id' => $this->id[$role], 'entite_id' => $this->id[$entite], 'date_debut' => date('Y-m-d')]);
        }

        // 1 000 L de gasoil au dépôt
        $this->db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) $this->u['mag.d1']->id]);
        (new SaisieMouvements($this->db))->enregistrer(['type' => 'entree', 'codification' => 'GASOIL', 'quantite' => '1000', 'destination' => (string) $this->id['D-R1']]);
        $this->redevenirProprietaire();
    }

    private function jeton(): array
    {
        return [csrf_token() => csrf_hash()];
    }

    private function post_(string $qui, string $adresse, array $champs = [])
    {
        $resultat = $this->actingAs($this->u[$qui])->post($adresse, $this->jeton() + $champs);
        $this->redevenirProprietaire();

        return $resultat;
    }

    /**
     * Crée une demande de gasoil en brouillon par l'interface et renvoie son identifiant.
     *
     * @param array<string, string> $lignes codification => quantité
     */
    private function creer(string $qui = 'demandeur.u1', string $entite = 'TERRE-U1', array $lignes = ['GASOIL' => '50'], string $filiere = 'CARBU'): int
    {
        $this->post_($qui, 'demandes', ['entite_id' => (string) $this->id[$entite], 'filiere_id' => (string) $this->id[$filiere], 'objet' => 'Besoin fictif', 'priorite' => 'normale'])->assertRedirect();
        $id = (int) $this->db->table('demande')->selectMax('id')->get()->getRow()->id;

        foreach ($lignes as $codification => $quantite) {
            $this->post_($qui, "demandes/{$id}/lignes", ['codification' => $codification, 'quantite' => $quantite])->assertSessionHas('message');
        }

        return $id;
    }

    private function soumettre(int $id, string $qui = 'demandeur.u1')
    {
        return $this->post_($qui, "demandes/{$id}/soumettre");
    }

    private function demande(int $id): array
    {
        return $this->db->table('demande')->where('id', $id)->get()->getRowArray();
    }

    private function etapes(int $id): array
    {
        return $this->db->table('etape_demande')->where('demande_id', $id)->orderBy('ordre')->get()->getResultArray();
    }

    private function notifications(string $qui): array
    {
        return array_column($this->db->table('notification')->where('utilisateur_id', $this->u[$qui]->id)->orderBy('id')->get()->getResultArray(), 'message');
    }

    private function servir(int $id, string $quantite, string $destination = 'U-R1')
    {
        $ligne = (int) $this->db->table('ligne_demande')->where('demande_id', $id)->get()->getRow()->id;

        return $this->post_('mag.d1', "demandes/{$id}/servir", [
            'ligne_id' => (string) $ligne, 'origine' => (string) $this->id['D-R1'], 'destination' => (string) $this->id[$destination], 'quantite' => $quantite,
        ]);
    }

    private function stock(string $emplacement): float
    {
        return (float) $this->db->query('SELECT coalesce(sum(quantite), 0) AS q FROM stock WHERE emplacement_id = ?', [$this->id[$emplacement]])->getRow()->q;
    }

    // Saisie et routage (DEM-01, DEM-02, DEM-03)

    public function testLaDemandeEstRouteeVersLEchelonLogistiqueSuperieur(): void
    {
        $id = $this->creer();
        $this->assertSame('brouillon', $this->demande($id)['statut']);
        $this->assertMatchesRegularExpression('/^DEM-\d{4}-000001$/', $this->demande($id)['numero']);

        $this->soumettre($id)->assertSessionHas('message');

        $demande = $this->demande($id);
        $this->assertSame('soumise', $demande['statut']);
        $this->assertSame($this->id['TERRE-DM'], (int) $demande['entite_service_id'], 'servie par son ravitailleur');
        $this->assertSame([$this->id['TERRE-DM']], array_map('intval', array_column($this->etapes($id), 'entite_id')), 'sous le seuil : une seule étape');
        $this->assertSame(['Demande ' . $demande['numero'] . ' à valider : Besoin fictif'], $this->notifications('chef.dm'));
        $this->assertSame([], $this->notifications('chef.air'));
    }

    public function testAuDelaDuSeuilLeServiceDeLaFiliereValideAussi(): void
    {
        $id = $this->creer(lignes: ['GASOIL' => '500']);
        $this->soumettre($id);

        $this->assertSame([$this->id['TERRE-DM'], $this->id['SERV-ESS']], array_map('intval', array_column($this->etapes($id), 'entite_id')));
    }

    public function testUneDemandeNePorteQueSurUneFiliere(): void
    {
        $id = $this->creer();

        // RG-16 : un fusil n'appartient pas à la filière Carburants
        $this->post_('demandeur.u1', "demandes/{$id}/lignes", ['codification' => 'FUSIL', 'quantite' => '1'])
            ->assertSessionHas('erreur', 'Demande refusée : l\'article n\'appartient pas à la filière de la demande (RG-16)');
        // RG-15 : un article par ligne, une ligne par article
        $this->post_('demandeur.u1', "demandes/{$id}/lignes", ['codification' => 'GASOIL', 'quantite' => '10'])
            ->assertSessionHas('erreur', 'Cet article figure déjà dans la demande : modifiez sa quantité.');
        $this->assertSame(1, $this->db->table('ligne_demande')->where('demande_id', $id)->countAllResults());
    }

    public function testUneDemandeVideNeSeSoumetPas(): void
    {
        $id = $this->creer(lignes: []);
        $this->soumettre($id)->assertSessionHas('erreur', 'Demande refusée : ajoutez au moins une ligne');
        $this->assertSame('brouillon', $this->demande($id)['statut']);
    }

    public function testLesLignesSontFigeesALaSoumission(): void
    {
        $id = $this->creer();
        $this->soumettre($id);

        $this->post_('demandeur.u1', "demandes/{$id}/lignes", ['codification' => 'GASOIL', 'quantite' => '10'])->assertSessionHas('erreur');

        // Hors brouillon, la base ne laisse rien modifier : la mise à jour ne touche aucune ligne
        $this->executerCommeApplication($this->u['demandeur.u1']->id, 'UPDATE ligne_demande SET quantite = 1 WHERE demande_id = ?', [$id]);
        $this->assertSame(50.0, (float) $this->db->table('ligne_demande')->where('demande_id', $id)->get()->getRow()->quantite);
    }

    public function testLeStatutNeSeModifieQueParLesFonctionsDeLaBase(): void
    {
        $id = $this->creer();

        $this->expectException(DatabaseException::class);
        $this->executerCommeApplication($this->u['demandeur.u1']->id, "UPDATE demande SET statut = 'validee' WHERE id = ?", [$id]);
    }

    // Validation (RG-19, règle 4.2-4)

    public function testLeDemandeurNeValidePasSaPropreDemande(): void
    {
        $id = $this->creer('cumul.terre');
        $this->soumettre($id, 'cumul.terre');

        $this->post_('cumul.terre', "demandes/{$id}/decider", ['decision' => 'valider'])
            ->assertSessionHas('erreur', 'Demande refusée : le demandeur ne peut pas valider sa propre demande (RG-19)');
        $this->assertSame('soumise', $this->demande($id)['statut']);

        $page = $this->actingAs($this->u['cumul.terre'])->get('demandes', ['vue' => 'a_valider']);
        $page->assertDontSee($this->demande($id)['numero']);
    }

    public function testSeulUnValideurDeLEtapeEnCoursDecide(): void
    {
        $id = $this->creer(lignes: ['GASOIL' => '500']);
        $this->soumettre($id);

        $this->post_('chef.air', "demandes/{$id}/decider", ['decision' => 'valider'])
            ->assertSessionHas('erreur', 'Demande refusée : cette étape relève d\'un valideur de l\'entité Entité TERRE-DM');

        $this->post_('chef.dm', "demandes/{$id}/decider", ['decision' => 'valider'])->assertSessionHas('message', 'Étape validée : la demande passe à l\'étape suivante.');
        $this->assertSame('en_validation', $this->demande($id)['statut']);
        // L'étape suivante relève du service de la filière, pas de l'échelon logistique
        $this->post_('chef.dm', "demandes/{$id}/decider", ['decision' => 'valider'])->assertSessionHas('erreur');

        $this->post_('gest.ess', "demandes/{$id}/decider", ['decision' => 'valider'])->assertSessionHas('message', 'Demande validée : elle peut être servie.');
        $demande = $this->demande($id);
        $this->assertSame('validee', $demande['statut']);
        $this->assertSame(['validee', 'validee'], array_column($this->etapes($id), 'statut'));
        $this->assertContains('Demande ' . $demande['numero'] . ' validée, à servir : Besoin fictif', $this->notifications('mag.d1'));
        $this->assertContains('Demande ' . $demande['numero'] . ' validée', $this->notifications('demandeur.u1'));
    }

    public function testUnRefusExigeUnMotifEtTermineLaDemande(): void
    {
        $id = $this->creer();
        $this->soumettre($id);

        $this->post_('chef.dm', "demandes/{$id}/decider", ['decision' => 'refuser'])->assertSessionHas('erreur', 'Demande refusée : indiquez le motif du refus');
        $this->post_('chef.dm', "demandes/{$id}/decider", ['decision' => 'refuser', 'commentaire' => 'Dotation déjà perçue'])->assertSessionHas('message', 'Demande refusée.');

        $this->assertSame('refusee', $this->demande($id)['statut']);
        $this->assertStringEndsWith('refusée : Dotation déjà perçue', $this->notifications('demandeur.u1')[0]);
    }

    // Service (RG-17)

    private function demandeValidee(string $quantite = '50'): int
    {
        $id = $this->creer(lignes: ['GASOIL' => $quantite]);
        $this->soumettre($id);
        $this->post_('chef.dm', "demandes/{$id}/decider", ['decision' => 'valider']);
        $this->assertSame('validee', $this->demande($id)['statut']);

        return $id;
    }

    public function testLaDemandeEstServieParDesPerceptions(): void
    {
        $id = $this->demandeValidee();

        // Le dépôt voit la demande, l'entité demandeuse et ses emplacements de livraison, hors de son périmètre
        $page = $this->actingAs($this->u['mag.d1'])->get("demandes/{$id}");
        $page->assertSee('Servir la demande');
        $page->assertSee('TERRE-U1 – Entité TERRE-U1');
        $page->assertSee('U-R1 – Emplacement U-R1');
        $this->redevenirProprietaire();

        $this->servir($id, '30')->assertSessionHas('message');
        $this->assertSame('en_service', $this->demande($id)['statut']);
        $this->assertSame(970.0, $this->stock('D-R1'));
        $this->assertSame(30.0, $this->stock('U-R1'));
        $this->assertSame(1, $this->db->table('bon')->where('type', 'perception')->countAllResults(), 'bon de perception émis');

        // Au-delà du reste à servir : tout est annulé, y compris la perception et son bon
        $this->servir($id, '30')->assertSessionHas('erreur', 'Demande refusée : quantité supérieure au reste à servir (20)');
        $this->assertSame(970.0, $this->stock('D-R1'));
        $this->assertSame(1, $this->db->table('bon')->countAllResults());

        $this->servir($id, '20')->assertSessionHas('message');
        $this->assertSame('servie', $this->demande($id)['statut']);
        $this->assertSame(50.0, (float) $this->db->table('ligne_demande')->where('demande_id', $id)->get()->getRow()->quantite_servie);
        $this->assertContains('Demande ' . $this->demande($id)['numero'] . ' entièrement servie', $this->notifications('demandeur.u1'));
    }

    public function testLeMaterielEstLivreALEntiteDemandeuse(): void
    {
        $id = $this->demandeValidee();

        $this->servir($id, '10', 'D-R1')->assertSessionHas('erreur');
        $this->assertSame('validee', $this->demande($id)['statut']);
        $this->assertSame(1000.0, $this->stock('D-R1'));
    }

    public function testLAnnulationDUnePerceptionRouvreLeService(): void
    {
        $id = $this->demandeValidee();
        $this->servir($id, '50');
        $this->assertSame('servie', $this->demande($id)['statut']);

        $mouvement = (int) $this->db->table('service_demande')->get()->getRow()->mouvement_id;
        $this->db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) $this->u['mag.d1']->id]);
        (new SaisieMouvements($this->db))->annuler($mouvement, 'Perception saisie par erreur');
        $this->redevenirProprietaire();

        $this->assertSame('en_service', $this->demande($id)['statut']);
        $this->assertSame(0.0, (float) $this->db->table('ligne_demande')->where('demande_id', $id)->get()->getRow()->quantite_servie);
    }

    public function testUneDemandeServieEnPartieSeClotureMaisNeSAnnulePas(): void
    {
        $id = $this->demandeValidee();
        $this->servir($id, '10');

        $this->post_('demandeur.u1', "demandes/{$id}/annuler", ['motif' => 'Plus besoin'])->assertSessionHas('erreur');
        $this->post_('mag.d1', "demandes/{$id}/cloturer")->assertSessionHas('erreur', 'Demande refusée : indiquez le motif de la clôture');
        $this->post_('mag.d1', "demandes/{$id}/cloturer", ['motif' => 'Reliquat non disponible'])->assertSessionHas('message', 'Demande clôturée.');

        $this->assertSame('cloturee', $this->demande($id)['statut']);
    }

    public function testUneDemandeSAnnuleTantQueRienNEstServi(): void
    {
        $id = $this->creer();
        $this->soumettre($id);

        $this->post_('demandeur.u1', "demandes/{$id}/annuler")->assertSessionHas('erreur', 'Demande refusée : indiquez le motif de l\'annulation');
        $this->post_('demandeur.u1', "demandes/{$id}/annuler", ['motif' => 'Exercice reporté'])->assertSessionHas('message', 'Demande annulée.');
        $this->assertSame('annulee', $this->demande($id)['statut']);

        $this->post_('chef.dm', "demandes/{$id}/decider", ['decision' => 'valider'])->assertSessionHas('erreur');
    }

    // Circuits paramétrables (DEM-03, ADM-02)

    public function testLeCircuitDUneFiliereSeParametreSansDeveloppement(): void
    {
        $this->post_('parametreur', 'demandes/circuits', ['filiere_id' => (string) $this->id['CARBU'], 'libelle' => 'Circuit carburants'])->assertSessionHas('message');
        $circuit = (int) $this->db->table('circuit_validation')->where('filiere_id', $this->id['CARBU'])->get()->getRow()->id;
        $this->post_('parametreur', "demandes/circuits/{$circuit}/etapes", [
            'ordre' => '1', 'libelle' => 'Visa du service des essences', 'mode' => 'entite', 'entite_id' => (string) $this->id['SERV-ESS'],
        ])->assertSessionHas('message');

        $id = $this->creer();
        $this->soumettre($id);

        $etapes = $this->etapes($id);
        $this->assertSame(['Visa du service des essences'], array_column($etapes, 'libelle'));
        $this->assertSame($this->id['SERV-ESS'], (int) $etapes[0]['entite_id']);

        // Sans le droit, pas d'accès au paramétrage
        $this->actingAs($this->u['chef.dm'])->get('demandes/circuits')->assertSessionHas('erreur');
    }

    public function testSansEtapeApplicableLaDemandeEstValideeDOffice(): void
    {
        $this->db->table('circuit_etape')->delete(['ordre >' => 0]);

        $id = $this->creer();
        $this->soumettre($id)->assertSessionHas('message', 'Demande soumise et validée d\'office (aucune étape de validation ne s\'applique).');
        $this->assertSame('validee', $this->demande($id)['statut']);
    }

    // Cloisonnement et suivi (DEM-04)

    public function testLesDemandesSontCloisonnees(): void
    {
        $id = $this->creer();
        $numero = $this->demande($id)['numero'];

        $this->actingAs($this->u['demandeur.air'])->get('demandes', ['vue' => 'perimetre'])->assertDontSee($numero);
        try {
            $this->actingAs($this->u['demandeur.air'])->get("demandes/{$id}");
            $this->fail('La demande aurait dû être introuvable.');
        } catch (PageNotFoundException) {
            $this->redevenirProprietaire();
        }

        // Le valideur de l'échelon logistique la voit une fois soumise
        $this->soumettre($id);
        $this->actingAs($this->u['chef.dm'])->get('demandes', ['vue' => 'a_valider'])->assertSee($numero);
        $this->actingAs($this->u['demandeur.air'])->post('demandes', $this->jeton() + [
            'entite_id' => (string) $this->id['TERRE-U1'], 'filiere_id' => (string) $this->id['CARBU'], 'objet' => 'Intrusion', 'priorite' => 'normale',
        ])->assertSessionHas('erreurs');
    }

    public function testLeSuiviMontreLHistoriqueEtLesNotifications(): void
    {
        $id = $this->demandeValidee();
        $numero = $this->demande($id)['numero'];

        $page = $this->actingAs($this->u['demandeur.u1'])->get("demandes/{$id}");
        $page->assertSee('soumission');
        $page->assertSee('validation par chef.dm');

        $page = $this->actingAs($this->u['mag.d1'])->get('notifications');
        $page->assertSee("Demande {$numero} validée, à servir");
        $notification = (int) $this->db->table('notification')->where('utilisateur_id', $this->u['mag.d1']->id)->get()->getRow()->id;
        $this->post_('mag.d1', "notifications/{$notification}/lire")->assertRedirectTo("/demandes/{$id}");
        $this->assertNotNull($this->db->table('notification')->where('id', $notification)->get()->getRow()->lue_le);

        // Chacun ne voit que ses notifications
        $this->assertSame([], $this->executerCommeApplication($this->u['demandeur.air']->id, 'SELECT * FROM notification'));
    }
}
