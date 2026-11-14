<?php

namespace App\Controllers\Inventaires;

use App\Controllers\GestionController;
use App\Models\Inventaires\InventaireModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Referentiel\ArticleModel;
use App\Models\Stocks\LotModel;
use App\Models\Stocks\MaterielModel;
use App\Models\Stocks\StockModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Models\UserModel;
use Throwable;

/**
 * Inventaires (INV-01 à INV-03, RG-31, RG-32). Les règles (droits, ordre des étapes,
 * séparation des tâches, régularisations, gel des emplacements) sont dans la base ;
 * ce contrôleur appelle ses fonctions et traduit leurs refus.
 */
class Inventaires extends GestionController
{
    private InventaireModel $inventaires;

    protected array $contraintes = [
        'valideur_different_du_compteur'       => 'Celui qui compte ne peut pas valider (RG-32).',
        'contradictoire_avec_cedant_et_prenant' => 'Un inventaire contradictoire exige un cédant et un prenant distincts.',
    ];

    public function __construct()
    {
        $this->inventaires = new InventaireModel();
    }

    public function index()
    {
        $statut = (string) $this->request->getGet('statut');

        return view('inventaires/index', [
            'titrePage'   => 'Inventaires',
            'inventaires' => $this->inventaires->liste($statut),
            'statut'      => $statut,
            'peutGerer'   => service('droits')->peut('inventaires.gerer'),
        ]);
    }

    public function new()
    {
        $utilisateurs = [];
        foreach (model(UserModel::class)->orderBy('username')->findAll() as $u) {
            $utilisateurs[$u->id] = $u->username;
        }

        return view('inventaires/form', [
            'titrePage'    => 'Planifier un inventaire',
            'entites'      => (new EntiteModel())->options(),
            'emplacements' => (new StockModel())->emplacements(),
            'utilisateurs' => $utilisateurs,
        ]);
    }

    public function create()
    {
        $entite = (int) $this->champ('entite_id');

        if (! service('droits')->peut('inventaires.gerer', $entite ?: null)) {
            return redirect()->back()->withInput()->with('erreurs', ['Vous ne pouvez pas planifier d\'inventaire pour cette entité.']);
        }

        $resultat = $this->enregistrer($this->inventaires, fn () => $this->inventaires->insert([
            'type'           => $this->champ('type'),
            'entite_id'      => $entite,
            'emplacement_id' => $this->champ('emplacement_id'),
            'date_prevue'    => $this->champ('date_prevue'),
            'compteur_id'    => $this->champ('compteur_id'),
            'cedant_id'      => $this->champ('cedant_id'),
            'prenant_id'     => $this->champ('prenant_id'),
            'observations'   => $this->champ('observations'),
            'cree_par'       => auth()->id(),
        ]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('inventaires/' . $resultat)->with('message', 'Inventaire planifié.');
    }

    public function show(int $id)
    {
        $inventaire = $this->inventaires->fiche($id);

        if ($inventaire === null) {
            throw PageNotFoundException::forPageNotFound('Inventaire introuvable.');
        }

        $moi = (int) auth()->id();

        return view('inventaires/show', [
            'titrePage'    => 'Inventaire n° ' . $id,
            'i'            => $inventaire,
            'lignes'       => $this->inventaires->lignes($id),
            'signatures'   => $this->inventaires->signatures($id),
            'estCompteur'  => (int) $inventaire['compteur_id'] === $moi,
            'peutGerer'    => service('droits')->peut('inventaires.gerer', (int) $inventaire['entite_id']),
            'peutValider'  => service('droits')->peut('inventaires.valider', (int) $inventaire['entite_id']) && (int) $inventaire['compteur_id'] !== $moi,
            'moi'          => $moi,
            'emplacements' => (new StockModel())->emplacements(),
        ]);
    }

    public function lancer(int $id)
    {
        return $this->transition($id, 'SELECT inventaire_lancer(?)', [$id], 'Inventaire lancé : quantités théoriques figées, emplacements gelés.');
    }

    public function terminer(int $id)
    {
        return $this->transition($id, 'SELECT inventaire_terminer_comptage(?)', [$id], 'Comptage terminé : l\'inventaire attend sa validation.');
    }

    public function signer(int $id)
    {
        return $this->transition($id, 'SELECT inventaire_signer(?, ?)', [$id, (string) $this->request->getPost('qualite')], 'Signature enregistrée.');
    }

    public function annuler(int $id)
    {
        return $this->transition($id, 'SELECT inventaire_annuler(?)', [$id], 'Inventaire annulé : emplacements dégelés.');
    }

    public function valider(int $id)
    {
        try {
            $nombre = (int) db_connect()->query('SELECT inventaire_valider(?) AS n', [$id])->getRow()->n;
        } catch (Throwable $e) {
            return redirect()->to('inventaires/' . $id)->with('erreur', self::message($e));
        }

        return redirect()->to('inventaires/' . $id)->with('message', 'Inventaire validé : ' . $nombre . ' mouvement(s) de régularisation enregistré(s).');
    }

    /**
     * Enregistre les comptages saisis (une quantité par ligne), en une seule transaction.
     */
    public function compter(int $id)
    {
        $comptages = (array) ($this->request->getPost('comptage') ?? []);
        $db        = db_connect();
        $db->transException(true)->transBegin();

        try {
            foreach ($comptages as $ligne => $valeur) {
                $valeur = str_replace([',', ' '], ['.', ''], trim((string) $valeur));
                if ($valeur === '') {
                    continue;
                }
                if (! is_numeric($valeur) || (float) $valeur < 0) {
                    throw new \DomainException('Quantité comptée invalide : « ' . $valeur . ' ».');
                }
                $db->query('SELECT inventaire_compter(?, ?)', [(int) $ligne, $valeur]);
            }
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            return redirect()->to('inventaires/' . $id)->with('erreur', $e instanceof \DomainException ? $e->getMessage() : self::message($e));
        } finally {
            $db->transException(false);
            $db->resetTransStatus();
        }

        return redirect()->to('inventaires/' . $id)->with('message', 'Comptages enregistrés.');
    }

    /**
     * Ajoute une ligne trouvée au comptage mais absente du stock théorique.
     */
    public function ajouterLigne(int $id)
    {
        $inventaire = $this->inventaires->find($id);
        $article    = (new ArticleModel())->where('codification', strtoupper($this->champ('codification')))->first();

        if ($inventaire === null) {
            throw PageNotFoundException::forPageNotFound('Inventaire introuvable.');
        }
        if ($article === null) {
            return redirect()->to('inventaires/' . $id)->with('erreur', 'Article inconnu : vérifiez la codification.');
        }

        $db = db_connect();
        $db->transException(true)->transBegin();

        try {
            $lot      = null;
            $materiel = null;
            $quantite = str_replace(',', '.', $this->champ('quantite'));

            if ($article['mode_suivi'] === 'lot') {
                $lots = new LotModel();
                $lot  = $lots->where(['article_id' => $article['id'], 'numero_lot' => $this->champ('reference')])->first()['id']
                    ?? (int) $lots->insert(['article_id' => $article['id'], 'numero_lot' => $this->champ('reference')]);
            } elseif ($article['mode_suivi'] === 'serie') {
                $materiels = new MaterielModel();
                $materiel  = $materiels->where(['article_id' => $article['id'], 'numero_serie' => $this->champ('reference')])->first()['id']
                    ?? (int) $materiels->insert(['article_id' => $article['id'], 'numero_serie' => $this->champ('reference'), 'proprietaire_entite_id' => $inventaire['entite_id']]);
                $quantite = '1';
            }

            $db->query('SELECT inventaire_ajouter_ligne(?, ?, ?, ?, ?, ?, ?)', [
                $id, $article['id'], $lot, $materiel, (int) $this->champ('emplacement_id'), $inventaire['entite_id'], $quantite === '' ? '0' : $quantite,
            ]);
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            return redirect()->to('inventaires/' . $id)->with('erreur', self::message($e));
        } finally {
            $db->transException(false);
            $db->resetTransStatus();
        }

        return redirect()->to('inventaires/' . $id)->with('message', 'Ligne ajoutée au comptage.');
    }

    private function transition(int $id, string $sql, array $valeurs, string $succes): RedirectResponse
    {
        try {
            db_connect()->query($sql, $valeurs);
        } catch (Throwable $e) {
            return redirect()->to('inventaires/' . $id)->with('erreur', self::message($e));
        }

        return redirect()->to('inventaires/' . $id)->with('message', $succes);
    }

    private static function message(Throwable $e): string
    {
        $texte = $e->getMessage();

        return match (true) {
            (bool) preg_match('/(Inventaire refusé|Mouvement refusé|Stock insuffisant)[^\n]*/u', $texte, $m) => preg_replace('/\s+CONTEXT:.*$/s', '', $m[0]),
            str_contains($texte, 'ligne_unique') => 'Cette ligne existe déjà dans l\'inventaire : modifiez son comptage.',
            str_contains($texte, 'serie_presente_ou_absente') => 'Un matériel sérialisé se compte 1 (présent) ou 0 (absent).',
            str_contains($texte, 'signature_inventaire_pkey') => 'Cette signature a déjà été donnée.',
            str_contains($texte, 'row-level security') => 'Vous n\'avez pas le droit de faire cette opération.',
            default => 'Opération refusée par la base de données.',
        };
    }
}
