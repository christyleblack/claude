<?php

namespace Tests\Feature\Stocks;

use App\Libraries\SaisieMouvements;
use App\Models\Stocks\MaterielModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Stocks\JeuDeStocks;
use Tests\Support\TestCase;

/**
 * Pages des stocks et cloisonnement : chaque magasinier ne voit et ne mouvemente que
 * le stock de son dépôt (STK-01, règle 4.2-6, critère de recette 7.3-2).
 *
 * @internal
 */
final class PagesTest extends TestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use JeuDeStocks;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creerJeuDeStocks();

        // Stock initial des deux dépôts, saisi par chaque magasinier
        foreach ([[$this->magTerre, 'T-R1', '1000', "V-T1\nV-T2"], [$this->magAir, 'A-R1', '700', 'V-A1']] as [$u, $emplacement, $gasoil, $vehicules]) {
            $this->db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) $u->id]);
            $saisie = new SaisieMouvements($this->db);
            $saisie->enregistrer(['type' => 'entree', 'codification' => 'GASOIL', 'quantite' => $gasoil, 'destination' => (string) $this->id[$emplacement]]);
            $saisie->enregistrer(['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => $vehicules, 'destination' => (string) $this->id[$emplacement]]);
        }
        $this->redevenirProprietaire();
    }

    private function jeton(): array
    {
        return [csrf_token() => csrf_hash()];
    }

    private function idMouvement(string $emplacementDestination): int
    {
        return (int) $this->db->table('mouvement')->where('emplacement_destination_id', $this->id[$emplacementDestination])->orderBy('id')->get()->getRow()->id;
    }

    // Consultation (STK-01)

    public function testChacunNeVoitQueLeStockDeSonPerimetre(): void
    {
        $result = $this->actingAs($this->magTerre)->get('stocks');
        $result->assertOK();
        $result->assertSee('V-T1');
        $result->assertSee('Rayon Terre 1');
        $result->assertDontSee('V-A1');
        $result->assertDontSee('Rayon Air 1');

        $this->actingAs($this->magAir)->get('stocks')->assertSee('V-A1');
    }

    public function testLeTotalNAdditionneQueLeStockVisible(): void
    {
        $result = $this->actingAs($this->magTerre)->get('stocks?vue=nationale&q=GASOIL');

        $result->assertSee('1 000 L');
        $result->assertDontSee('1 700 L');
    }

    public function testLaBaseNeRenvoieQueLeStockDuPerimetre(): void
    {
        $lignes = $this->executerCommeApplication($this->magTerre->id, 'SELECT count(*) AS n FROM stock');
        $this->assertSame(3, (int) $lignes[0]['n'], 'gas-oil et deux véhicules du dépôt Terre');

        $mouvements = $this->executerCommeApplication($this->magTerre->id, 'SELECT count(*) AS n FROM mouvement');
        $this->assertSame(3, (int) $mouvements[0]['n']);

        $materiels = $this->executerCommeApplication($this->magTerre->id, "SELECT count(*) AS n FROM materiel_serialise WHERE numero_serie = 'V-A1'");
        $this->assertSame(0, (int) $materiels[0]['n'], 'le numéro de série d\'un autre dépôt n\'est pas visible');
    }

    public function testUnMouvementHorsPerimetreEstIntrouvable(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->actingAs($this->magTerre)->get('stocks/mouvements/' . $this->idMouvement('A-R1'));
    }

    // Saisie

    public function testUnMagasinierSaisitUnTransfertDansSonDepot(): void
    {
        $result = $this->actingAs($this->magTerre)->post('stocks/mouvements', $this->jeton() + [
            'type' => 'transfert', 'codification' => 'gasoil', 'quantite' => '250', 'origine' => (string) $this->id['T-R1'], 'destination' => (string) $this->id['T-R2'], 'motif' => 'Rangement',
        ]);

        $result->assertRedirect();
        $this->assertStringContainsString('enregistré', (string) session('message'));
        $this->assertSame(750.0, $this->stock('GASOIL', 'T-R1'));
        $this->assertSame(250.0, $this->stock('GASOIL', 'T-R2'));
    }

    public function testUnStockInsuffisantEstExpliqueAuFormulaire(): void
    {
        $result = $this->actingAs($this->magTerre)->post('stocks/mouvements', $this->jeton() + [
            'type' => 'sortie', 'codification' => 'GASOIL', 'quantite' => '5000', 'origine' => (string) $this->id['T-R1'],
        ]);

        $this->assertStringContainsString('Stock insuffisant', implode(' ', session('erreurs')));
    }

    public function testOnNeSortPasDuStockDUnAutreDepot(): void
    {
        $result = $this->actingAs($this->magTerre)->post('stocks/mouvements', $this->jeton() + [
            'type' => 'transfert', 'codification' => 'GASOIL', 'quantite' => '100', 'origine' => (string) $this->id['A-R1'], 'destination' => (string) $this->id['T-R1'],
        ]);

        $this->assertStringContainsString('pas le droit', implode(' ', session('erreurs')));
        $this->assertSame(700.0, $this->stock('GASOIL', 'A-R1'));
    }

    public function testLaBaseRefuseUnMouvementHorsPerimetreEtToucheAuStock(): void
    {
        try {
            $this->executerCommeApplication($this->magTerre->id,
                "INSERT INTO mouvement (type, article_id, quantite, emplacement_origine_id, proprietaire_entite_id, auteur_id) VALUES ('sortie', ?, 100, ?, ?, ?)",
                [$this->id['GASOIL'], $this->id['A-R1'], $this->id['AIR-D1'], $this->magTerre->id]);
            $this->fail('La base aurait dû refuser le mouvement.');
        } catch (DatabaseException $e) {
            $this->assertStringContainsString('row-level security', $e->getMessage());
        }

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('permission denied');
        $this->executerCommeApplication($this->magTerre->id, 'UPDATE stock SET quantite = 999999');
    }

    public function testSansLaPermissionOnNeSaisitRien(): void
    {
        $this->actingAs($this->lecteurTerre)->get('stocks')->assertSee('V-T1');
        $this->actingAs($this->lecteurTerre)->get('stocks/mouvements/new')->assertSessionHas('erreur');

        $this->actingAs($this->lecteurTerre)->post('stocks/mouvements', $this->jeton() + [
            'type' => 'sortie', 'codification' => 'GASOIL', 'quantite' => '1', 'origine' => (string) $this->id['T-R1'],
        ])->assertSessionHas('erreur');

        $this->assertSame(1000.0, $this->stock('GASOIL', 'T-R1'));
    }

    // Annulation et matériels

    public function testUnMagasinierAnnuleUnMouvementDeSonDepot(): void
    {
        $entree = $this->idMouvement('T-R1');

        $this->actingAs($this->magTerre)->get('stocks/mouvements/' . $entree)->assertSee('Annuler par un mouvement inverse');
        $this->actingAs($this->magTerre)->post("stocks/mouvements/{$entree}/annuler", $this->jeton() + ['motif' => 'Livraison saisie deux fois']);

        $this->assertSame(0.0, $this->stock('GASOIL', 'T-R1'));
        $this->actingAs($this->magTerre)->get('stocks/mouvements/' . $entree)->assertSee('annulé par le n°');
    }

    public function testLaFicheDUnMaterielMontreSonHistoriqueEtChangeSonEtat(): void
    {
        $materiel = (new MaterielModel())->where('numero_serie', 'V-T1')->first();

        $result = $this->actingAs($this->magTerre)->get('stocks/materiels/' . $materiel['id']);
        $result->assertSee('Rayon Terre 1');
        $result->assertSee('Entrée');

        $this->actingAs($this->magTerre)->post('stocks/materiels/' . $materiel['id'] . '/etat', $this->jeton() + ['etat' => 'en_reparation']);
        $this->assertSame('en_reparation', (new MaterielModel())->find($materiel['id'])['etat']);

        // La réforme ne se fait que par un mouvement, qui sort le matériel du stock
        $this->actingAs($this->magTerre)->post('stocks/materiels/' . $materiel['id'] . '/etat', $this->jeton() + ['etat' => 'reforme'])->assertSessionHas('erreur');
        $this->assertSame('en_reparation', (new MaterielModel())->find($materiel['id'])['etat']);
    }

    public function testLeMagasinierDUnAutreDepotNeChangePasLEtat(): void
    {
        $materiel = (new MaterielModel())->where('numero_serie', 'V-T1')->first();

        // Le matériel est hors de son périmètre : il lui est introuvable (son existence n'est pas révélée)
        try {
            $this->actingAs($this->magAir)->post('stocks/materiels/' . $materiel['id'] . '/etat', $this->jeton() + ['etat' => 'indisponible']);
            $this->fail('Le matériel aurait dû être introuvable.');
        } catch (PageNotFoundException) {
            $this->redevenirProprietaire();
        }

        $this->assertSame('en_service', (new MaterielModel())->find($materiel['id'])['etat']);
    }
}
