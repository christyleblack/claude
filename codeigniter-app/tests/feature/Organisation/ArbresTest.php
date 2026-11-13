<?php

namespace Tests\Feature\Organisation;

use App\Models\Organisation\EmplacementModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Organisation\ForceModel;
use App\Models\Organisation\SiteModel;
use App\Models\Organisation\TypeEntiteModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Garanties apportées par la base de données (migration CreerOrganisation) :
 * chemins des deux arbres, propagation des déplacements, refus des cycles,
 * appartenance à une force (RG-01, RG-02).
 *
 * @internal
 */
final class ArbresTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    private EntiteModel $entites;
    private int $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entites = new EntiteModel();
        $this->type    = (new TypeEntiteModel())->insert(['code' => 'TEST', 'libelle' => 'Test']);
    }

    private function creer(string $code, ?int $parentH = null, ?int $parentL = null): int
    {
        $id = $this->entites->insert([
            'code' => $code, 'nom' => $code, 'type_entite_id' => $this->type,
            'parent_hierarchique_id' => $parentH, 'parent_logistique_id' => $parentL,
        ]);
        $this->assertNotFalse($id, implode(' ', $this->entites->errors()));

        return (int) $id;
    }

    private function chemins(int $id): array
    {
        return $this->db->table('entite')->select('chemin_hierarchique, chemin_logistique, force_id')
            ->where('id', $id)->get()->getRowArray();
    }

    public function testLesCheminsSontCalculesALaCreation(): void
    {
        $a = $this->creer('A');
        $b = $this->creer('B', $a);
        $c = $this->creer('C', $b, $a);

        $this->assertSame("{$a}", $this->chemins($a)['chemin_hierarchique']);
        $this->assertSame("{$a}.{$b}.{$c}", $this->chemins($c)['chemin_hierarchique']);
        $this->assertSame("{$a}.{$c}", $this->chemins($c)['chemin_logistique']);
        $this->assertSame("{$b}", $this->chemins($b)['chemin_logistique'], 'sans parent logistique, une entité est racine de l\'arbre logistique');
    }

    public function testUnDeplacementSePropageATouteLaBranche(): void
    {
        $a = $this->creer('A');
        $x = $this->creer('X');
        $b = $this->creer('B', $a);
        $c = $this->creer('C', $b);
        $d = $this->creer('D', $c);

        $this->entites->update($b, ['parent_hierarchique_id' => $x]);

        $this->assertSame("{$x}.{$b}", $this->chemins($b)['chemin_hierarchique']);
        $this->assertSame("{$x}.{$b}.{$c}", $this->chemins($c)['chemin_hierarchique']);
        $this->assertSame("{$x}.{$b}.{$c}.{$d}", $this->chemins($d)['chemin_hierarchique']);
        $this->assertSame([$b, $c, $d], $this->entites->sousArbre($b, 'hierarchique'));
        $this->assertSame([$a], $this->entites->sousArbre($a, 'hierarchique'));
    }

    public function testLesDeuxArbresSontIndependants(): void
    {
        $a = $this->creer('A');
        $b = $this->creer('B', $a, $a);
        $c = $this->creer('C', $b, $a);

        $this->entites->update($b, ['parent_logistique_id' => null]);

        $this->assertSame("{$b}", $this->chemins($b)['chemin_logistique']);
        $this->assertSame("{$a}.{$c}", $this->chemins($c)['chemin_logistique'], 'C reste ravitaillée par A');
        $this->assertSame("{$a}.{$b}.{$c}", $this->chemins($c)['chemin_hierarchique'], 'l\'arbre hiérarchique ne bouge pas');
    }

    public function testUnCycleHierarchiqueEstRefuseParLaBase(): void
    {
        $a = $this->creer('A');
        $b = $this->creer('B', $a);
        $c = $this->creer('C', $b);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Cycle refusé');

        $this->entites->update($a, ['parent_hierarchique_id' => $c]);
    }

    public function testUnCycleLogistiqueEstRefuseParLaBase(): void
    {
        $a = $this->creer('A');
        $b = $this->creer('B', null, $a);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Cycle refusé');

        $this->entites->update($a, ['parent_logistique_id' => $b]);
    }

    public function testUneEntiteNePeutPasEtreSonPropreParent(): void
    {
        $a = $this->creer('A');

        $this->expectException(DatabaseException::class);

        $this->entites->update($a, ['parent_hierarchique_id' => $a]);
    }

    public function testLesCheminsEtLaForceNePeuventPasEtreModifiesDirectement(): void
    {
        $a = $this->creer('A');
        $b = $this->creer('B', $a);
        (new ForceModel())->insert(['code' => 'F', 'nom' => 'Force', 'entite_racine_id' => $a]);
        $avant = $this->chemins($b);

        $this->db->query("UPDATE entite SET chemin_hierarchique = '99.98', chemin_logistique = '97', force_id = NULL WHERE id = ?", [$b]);

        $this->assertSame($avant, $this->chemins($b));
    }

    public function testLaForceEstDeduiteDeLArbreHierarchique(): void
    {
        $national = $this->creer('NATIONAL');
        $racine   = $this->creer('FORCE');
        $unite    = $this->creer('UNITE', $racine, $national);
        $depot    = $this->creer('DEPOT', $unite, $national);

        $this->assertNull($this->chemins($depot)['force_id'], 'aucune force tant qu\'elle n\'est pas créée');

        $force = (new ForceModel())->insert(['code' => 'F', 'nom' => 'Force', 'entite_racine_id' => $racine]);

        foreach ([$racine, $unite, $depot] as $id) {
            $this->assertSame((string) $force, (string) $this->chemins($id)['force_id']);
        }
        $this->assertNull($this->chemins($national)['force_id'], 'le niveau national n\'appartient à aucune force (RG-02)');

        // L'unité quitte la force avec son dépôt : la force n'est pas suivie par l'arbre logistique
        $this->entites->update($unite, ['parent_hierarchique_id' => $national]);
        $this->assertNull($this->chemins($unite)['force_id']);
        $this->assertNull($this->chemins($depot)['force_id']);

        $this->entites->update($unite, ['parent_hierarchique_id' => $racine]);
        $this->assertSame((string) $force, (string) $this->chemins($depot)['force_id']);
    }

    public function testLesAncetresSontDonnesDeLaRacineAuParent(): void
    {
        $a = $this->creer('A');
        $b = $this->creer('B', $a);
        $c = $this->creer('C', $b);

        $this->assertSame(['A', 'B'], array_column($this->entites->ancetres($c, 'hierarchique'), 'code'));
        $this->assertSame([], $this->entites->ancetres($c, 'logistique'));
    }

    public function testLArbreAplatiRespecteLaProfondeur(): void
    {
        $a = $this->creer('A');
        $this->creer('B', $a);

        $arbre = $this->entites->arbre('hierarchique');

        $this->assertSame([['A', 0], ['B', 1]], array_map(static fn ($e) => [$e['code'], $e['profondeur']], $arbre));
        $this->assertTrue($arbre[0]['actif']);
    }

    public function testUnEmplacementNePeutPasEtreRangeDansUnAutreSite(): void
    {
        $entite  = $this->creer('DEPOT');
        $sites   = new SiteModel();
        $site1   = $sites->insert(['code' => 'S1', 'nom' => 'Site 1', 'entite_id' => $entite]);
        $site2   = $sites->insert(['code' => 'S2', 'nom' => 'Site 2', 'entite_id' => $entite]);
        $magasin = (new EmplacementModel())->insert(['site_id' => $site1, 'code' => 'MAG', 'libelle' => 'Magasin']);

        $this->expectException(DatabaseException::class);

        (new EmplacementModel())->insert(['site_id' => $site2, 'parent_id' => $magasin, 'code' => 'X', 'libelle' => 'X']);
    }

    public function testUnCycleDEmplacementsEstRefuse(): void
    {
        $entite  = $this->creer('DEPOT');
        $site    = (new SiteModel())->insert(['code' => 'S1', 'nom' => 'Site 1', 'entite_id' => $entite]);
        $model   = new EmplacementModel();
        $magasin = $model->insert(['site_id' => $site, 'code' => 'MAG', 'libelle' => 'Magasin']);
        $rayon   = $model->insert(['site_id' => $site, 'parent_id' => $magasin, 'code' => 'R1', 'libelle' => 'Rayon']);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Cycle refusé');

        $model->update($magasin, ['parent_id' => $rayon]);
    }
}
