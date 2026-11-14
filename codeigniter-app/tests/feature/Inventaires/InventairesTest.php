<?php

namespace Tests\Feature\Inventaires;

use App\Libraries\SaisieMouvements;
use App\Models\Droits\AffectationModel;
use App\Models\Droits\RoleModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use DomainException;
use Tests\Support\Stocks\JeuDeStocks;
use Tests\Support\TestCase;

/**
 * Inventaires (INV-01 à INV-03) : quantités théoriques figées au lancement, gel des
 * emplacements, comptage réservé au compteur, validation par une autre personne (RG-32),
 * régularisation des écarts par des mouvements (RG-31), signatures contradictoires.
 *
 * @internal
 */
final class InventairesTest extends TestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use JeuDeStocks;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    /** Chef de dépôt Terre : planifie, lance et valide. */
    private User $chefTerre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creerJeuDeStocks();

        $roles = new RoleModel();
        $chef  = (int) $roles->insert(['code' => 'CHEF', 'libelle' => 'Chef de dépôt', 'portee' => 'entite']);
        $roles->definirPermissions($chef, ['inventaires.gerer', 'inventaires.valider']);
        $this->chefTerre = fake(UserModel::class, ['username' => 'chef.terre', 'active' => 1]);
        (new AffectationModel())->insert(['utilisateur_id' => $this->chefTerre->id, 'role_id' => $chef, 'entite_id' => $this->id['TERRE-D1'], 'date_debut' => date('Y-m-d')]);

        // Stock initial : 1 000 L de gasoil et deux véhicules en T-R1, 200 L en T-R2
        $this->db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) $this->magTerre->id]);
        $saisie = new SaisieMouvements($this->db);
        $saisie->enregistrer(['type' => 'entree', 'codification' => 'GASOIL', 'quantite' => '1000', 'destination' => (string) $this->id['T-R1']]);
        $saisie->enregistrer(['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => "V-1\nV-2", 'destination' => (string) $this->id['T-R1']]);
        $saisie->enregistrer(['type' => 'entree', 'codification' => 'GASOIL', 'quantite' => '200', 'destination' => (string) $this->id['T-R2']]);
        $this->redevenirProprietaire();
    }

    private function jeton(): array
    {
        return [csrf_token() => csrf_hash()];
    }

    /**
     * Planifie un inventaire par l'interface et renvoie son identifiant.
     */
    private function planifier(array $champs = []): int
    {
        $this->actingAs($this->chefTerre)->post('inventaires', $this->jeton() + $champs + [
            'type'        => 'tournant',
            'entite_id'   => (string) $this->id['TERRE-D1'],
            'date_prevue' => date('Y-m-d'),
            'compteur_id' => (string) $this->magTerre->id,
        ])->assertRedirect();

        return (int) $this->db->table('inventaire')->selectMax('id')->get()->getRow()->id;
    }

    private function action(User $qui, int $inventaire, string $action, array $champs = [])
    {
        return $this->actingAs($qui)->post("inventaires/{$inventaire}/{$action}", $this->jeton() + $champs);
    }

    private function inventaire(int $id): array
    {
        return $this->db->table('inventaire')->where('id', $id)->get()->getRowArray();
    }

    /**
     * Lignes de l'inventaire indexées par « codification/emplacement/numéro de série ».
     */
    private function lignes(int $inventaire): array
    {
        $lignes = [];
        foreach ($this->db->query(
            'SELECT l.*, a.codification, e.code AS emplacement, m.numero_serie
               FROM ligne_inventaire l JOIN article a ON a.id = l.article_id JOIN emplacement e ON e.id = l.emplacement_id
               LEFT JOIN materiel_serialise m ON m.id = l.materiel_id
              WHERE l.inventaire_id = ?',
            [$inventaire],
        )->getResultArray() as $l) {
            $lignes[$l['codification'] . '/' . $l['emplacement'] . ($l['numero_serie'] !== null ? '/' . $l['numero_serie'] : '')] = $l;
        }

        return $lignes;
    }

    /**
     * Saisit un comptage par l'interface ; $comptages est indexé comme lignes().
     */
    private function compter(User $qui, int $inventaire, array $comptages)
    {
        $lignes = $this->lignes($inventaire);
        $champ  = [];
        foreach ($comptages as $cle => $quantite) {
            $champ[$lignes[$cle]['id']] = $quantite;
        }

        return $this->action($qui, $inventaire, 'comptage', ['comptage' => $champ]);
    }

    private function sortieGasoil(string $emplacement): void
    {
        $this->db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) $this->magTerre->id]);

        try {
            (new SaisieMouvements($this->db))->enregistrer(['type' => 'sortie', 'codification' => 'GASOIL', 'quantite' => '1', 'origine' => (string) $this->id[$emplacement]]);
        } finally {
            $this->redevenirProprietaire();
        }
    }

    public function testLeLancementFigeLesQuantitesTheoriquesDuChamp(): void
    {
        $id = $this->planifier(['emplacement_id' => (string) $this->id['T-R1']]);
        $this->assertSame('planifie', $this->inventaire($id)['statut']);
        $this->assertSame([], $this->lignes($id));

        $this->action($this->chefTerre, $id, 'lancer')->assertSessionHas('message');

        $lignes = $this->lignes($id);
        $this->assertSame('en_cours', $this->inventaire($id)['statut']);
        $this->assertEqualsCanonicalizing(['GASOIL/T-R1', 'VEH/T-R1/V-1', 'VEH/T-R1/V-2'], array_keys($lignes), 'T-R2 est hors du champ');
        $this->assertSame(1000.0, (float) $lignes['GASOIL/T-R1']['quantite_theorique']);
        $this->assertNull($lignes['GASOIL/T-R1']['quantite_comptee']);
    }

    public function testUnEmplacementEnCoursDInventaireEstGele(): void
    {
        $id = $this->planifier(['emplacement_id' => (string) $this->id['T-R1']]);
        $this->action($this->chefTerre, $id, 'lancer');

        try {
            $this->sortieGasoil('T-R1');
            $this->fail('La sortie aurait dû être refusée pendant l\'inventaire.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('emplacement en cours d\'inventaire', $e->getMessage());
        }

        // Hors du champ de l'inventaire, les mouvements restent possibles
        $this->sortieGasoil('T-R2');
        $this->assertSame(199.0, $this->stock('GASOIL', 'T-R2'));

        // L'annulation lève le gel
        $this->action($this->chefTerre, $id, 'annuler')->assertSessionHas('message');
        $this->assertSame('annule', $this->inventaire($id)['statut']);
        $this->sortieGasoil('T-R1');
        $this->assertSame(999.0, $this->stock('GASOIL', 'T-R1'));
    }

    public function testSeulLeCompteurDesigneSaisitLesComptages(): void
    {
        $id = $this->planifier();
        $this->action($this->chefTerre, $id, 'lancer');

        $this->compter($this->chefTerre, $id, ['GASOIL/T-R1' => '900'])->assertSessionHas('erreur');
        $this->assertNull($this->lignes($id)['GASOIL/T-R1']['quantite_comptee']);

        $this->compter($this->magTerre, $id, ['GASOIL/T-R1' => '950,5'])->assertSessionHas('message');
        $this->assertSame(950.5, (float) $this->lignes($id)['GASOIL/T-R1']['quantite_comptee']);
        $this->assertSame(-49.5, (float) $this->lignes($id)['GASOIL/T-R1']['ecart']);

        // Un numéro de série se compte 1 ou 0
        $this->compter($this->magTerre, $id, ['VEH/T-R1/V-1' => '2'])->assertSessionHas('erreur');
    }

    public function testLesLignesNeSeModifientQueParLesFonctionsDeLaBase(): void
    {
        $id = $this->planifier();
        $this->action($this->chefTerre, $id, 'lancer');

        $this->expectException(DatabaseException::class);
        $this->executerCommeApplication($this->magTerre->id, 'UPDATE ligne_inventaire SET quantite_comptee = 0 WHERE inventaire_id = ?', [$id]);
    }

    public function testLeComptageNeSeTermineQueSiToutesLesLignesSontComptees(): void
    {
        $id = $this->planifier();
        $this->action($this->chefTerre, $id, 'lancer');
        $this->compter($this->magTerre, $id, ['GASOIL/T-R1' => '1000']);

        $this->action($this->magTerre, $id, 'terminer')->assertSessionHas('erreur');
        $this->assertSame('en_cours', $this->inventaire($id)['statut']);
    }

    public function testLeCompteurNePeutPasValiderSonPropreInventaire(): void
    {
        // RG-32 : le chef se désigne lui-même comme compteur, il ne pourra pas valider
        $id = $this->planifier(['compteur_id' => (string) $this->chefTerre->id, 'emplacement_id' => (string) $this->id['T-R2']]);
        $this->action($this->chefTerre, $id, 'lancer');
        $this->compter($this->chefTerre, $id, ['GASOIL/T-R2' => '150']);
        $this->action($this->chefTerre, $id, 'terminer')->assertSessionHas('message');

        $this->actingAs($this->chefTerre)->get("inventaires/{$id}")->assertDontSee('Valider et régulariser');
        $this->action($this->chefTerre, $id, 'valider')->assertSessionHas('erreur');

        $this->assertSame('compte', $this->inventaire($id)['statut']);
        $this->assertSame(200.0, $this->stock('GASOIL', 'T-R2'));
    }

    public function testLaValidationRegulariseChaqueEcartParUnMouvement(): void
    {
        $id = $this->planifier(['emplacement_id' => (string) $this->id['T-R1']]);
        $this->action($this->chefTerre, $id, 'lancer');

        // Un véhicule inconnu du stock est trouvé dans le rayon
        $this->action($this->magTerre, $id, 'lignes', [
            'codification' => 'veh', 'reference' => 'V-9', 'emplacement_id' => (string) $this->id['T-R1'],
        ])->assertSessionHas('message');

        $this->compter($this->magTerre, $id, ['GASOIL/T-R1' => '950', 'VEH/T-R1/V-1' => '1', 'VEH/T-R1/V-2' => '0']);
        $this->action($this->magTerre, $id, 'terminer')->assertSessionHas('message');

        $this->actingAs($this->chefTerre)->get("inventaires/{$id}")->assertSee('Valider et régulariser');
        $this->action($this->chefTerre, $id, 'valider')->assertSessionHas('message', 'Inventaire validé : 3 mouvement(s) de régularisation enregistré(s).');

        $inventaire = $this->inventaire($id);
        $this->assertSame('valide', $inventaire['statut']);
        $this->assertSame((string) $this->chefTerre->id, (string) $inventaire['valideur_id']);

        // Le stock correspond désormais au comptage
        $this->assertSame(950.0, $this->stock('GASOIL', 'T-R1'));
        $vehicules = array_column($this->db->query(
            'SELECT m.numero_serie FROM stock s JOIN materiel_serialise m ON m.id = s.materiel_id WHERE s.emplacement_id = ? ORDER BY 1',
            [$this->id['T-R1']],
        )->getResultArray(), 'numero_serie');
        $this->assertSame(['V-1', 'V-9'], $vehicules);

        // Chaque régularisation est un mouvement tracé, relié à sa ligne, auteur = valideur
        $regularisations = $this->db->table('mouvement')->where('type', 'regularisation')->get()->getResultArray();
        $this->assertCount(3, $regularisations);
        foreach ($regularisations as $m) {
            $this->assertSame((string) $this->chefTerre->id, (string) $m['auteur_id']);
            $this->assertStringContainsString("inventaire n° {$id}", $m['motif']);
        }
        $this->assertCount(3, array_filter($this->lignes($id), static fn ($l) => $l['mouvement_regularisation_id'] !== null));
        $this->assertNull($this->db->query('SELECT stocks_verifier_chaine() AS id')->getRow()->id, 'chaîne intacte');

        // Le gel est levé et l'inventaire ne bouge plus
        $this->sortieGasoil('T-R1');
        $this->action($this->chefTerre, $id, 'annuler')->assertSessionHas('erreur');
    }

    public function testUnInventaireContradictoireExigeLesDeuxSignatures(): void
    {
        $id = $this->planifier([
            'type'           => 'contradictoire',
            'emplacement_id' => (string) $this->id['T-R2'],
            'cedant_id'      => (string) $this->magTerre->id,
            'prenant_id'     => (string) $this->lecteurTerre->id,
        ]);
        $this->action($this->chefTerre, $id, 'lancer');
        $this->compter($this->magTerre, $id, ['GASOIL/T-R2' => '190']);
        $this->action($this->magTerre, $id, 'terminer');

        $this->action($this->chefTerre, $id, 'valider')->assertSessionHas('erreur');

        // Seul le cédant désigné signe comme cédant, et une seule fois
        $this->action($this->lecteurTerre, $id, 'signer', ['qualite' => 'cedant'])->assertSessionHas('erreur');
        $this->action($this->magTerre, $id, 'signer', ['qualite' => 'cedant'])->assertSessionHas('message');
        $this->action($this->magTerre, $id, 'signer', ['qualite' => 'cedant'])->assertSessionHas('erreur');
        $this->action($this->chefTerre, $id, 'valider')->assertSessionHas('erreur');

        $this->action($this->lecteurTerre, $id, 'signer', ['qualite' => 'prenant'])->assertSessionHas('message');
        $this->action($this->chefTerre, $id, 'valider')->assertSessionHas('message');

        $this->assertSame('valide', $this->inventaire($id)['statut']);
        $this->assertSame(190.0, $this->stock('GASOIL', 'T-R2'));
        $this->assertSame(2, $this->db->table('signature_inventaire')->where('inventaire_id', $id)->countAllResults());
    }

    public function testUnContradictoireSansCedantNiPrenantEstRefuse(): void
    {
        $this->actingAs($this->chefTerre)->post('inventaires', $this->jeton() + [
            'type'        => 'contradictoire',
            'entite_id'   => (string) $this->id['TERRE-D1'],
            'date_prevue' => date('Y-m-d'),
            'compteur_id' => (string) $this->magTerre->id,
        ])->assertSessionHas('erreurs');

        $this->assertSame(0, $this->db->table('inventaire')->countAllResults());
    }

    public function testLesInventairesSontCloisonnesParEntite(): void
    {
        $id = $this->planifier();

        $this->actingAs($this->chefTerre)->get('inventaires')->assertSee('Entité TERRE-D1');
        $this->actingAs($this->magAir)->get('inventaires')->assertDontSee('Entité TERRE-D1');

        try {
            $this->actingAs($this->magAir)->get("inventaires/{$id}");
            $this->fail('La fiche aurait dû être introuvable.');
        } catch (PageNotFoundException) {
            $this->redevenirProprietaire();
        }

        // Le magasinier Air ne peut ni lancer l'inventaire Terre, ni en planifier
        $this->action($this->magAir, $id, 'lancer')->assertSessionHas('erreur');
        $this->assertSame('planifie', $this->inventaire($id)['statut']);
        $this->actingAs($this->magAir)->get('inventaires/new')->assertSessionHas('erreur');
    }

    public function testLesPagesSAffichent(): void
    {
        $id = $this->planifier();

        $this->actingAs($this->chefTerre)->get('inventaires/new')->assertSee('Planifier un inventaire');
        $this->actingAs($this->chefTerre)->get("inventaires/{$id}")->assertSee('Lancer l\'inventaire');

        $this->action($this->chefTerre, $id, 'lancer');
        $page = $this->actingAs($this->magTerre)->get("inventaires/{$id}");
        $page->assertSee('Feuille de comptage');
        $page->assertSee('Terminer le comptage');
        $page = $this->actingAs($this->chefTerre)->get("inventaires/{$id}");
        $page->assertSee('Lignes et écarts');
        $page->assertDontSee('Terminer le comptage');
        $this->actingAs($this->chefTerre)->get('inventaires?statut=en_cours')->assertSee('En cours');
    }
}
