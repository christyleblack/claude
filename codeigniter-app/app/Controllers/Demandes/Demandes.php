<?php

namespace App\Controllers\Demandes;

use App\Controllers\GestionController;
use App\Libraries\ServiceDemandes;
use App\Models\Demandes\DemandeModel;
use App\Models\Demandes\LigneDemandeModel;
use App\Models\Organisation\EntiteModel;
use App\Models\Referentiel\ArticleModel;
use App\Models\Referentiel\FiliereModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use DomainException;
use Throwable;

/**
 * Demandes de matériel (DEM-01 à DEM-04) : saisie en brouillon, soumission, validation par
 * étapes, service par perception, clôture et annulation. Les règles (états, droits,
 * séparation des tâches, filière unique) sont vérifiées par la base.
 */
class Demandes extends GestionController
{
    protected array $contraintes = [
        'ligne_article_unique' => 'Cet article figure déjà dans la demande : modifiez sa quantité.',
    ];

    private DemandeModel $demandes;

    public function __construct()
    {
        $this->demandes = new DemandeModel();
    }

    private function moi(): int
    {
        return (int) auth()->id();
    }

    public function index()
    {
        $vue    = (string) $this->request->getGet('vue');
        $vue    = isset(DemandeModel::VUES[$vue]) ? $vue : 'mes';
        $statut = (string) $this->request->getGet('statut');

        return view('demandes/index', [
            'titrePage'   => 'Demandes',
            'demandes'    => $this->demandes->liste($vue, $statut, $this->moi()),
            'vue'         => $vue,
            'statut'      => $statut,
            'peutSaisir'  => service('droits')->peut('demandes.saisir'),
            'peutCircuit' => service('droits')->peut('demandes.circuits', null, true),
        ]);
    }

    /**
     * Entités pour lesquelles l'utilisateur peut saisir une demande.
     */
    private function entitesDemandeuses(): array
    {
        return array_filter(
            (new EntiteModel())->options(),
            static fn ($libelle, $id) => service('droits')->peut('demandes.saisir', (int) $id),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function new()
    {
        return view('demandes/form', [
            'titrePage' => 'Nouvelle demande',
            'demande'   => null,
            'entites'   => $this->entitesDemandeuses(),
            'filieres'  => (new FiliereModel())->options(),
        ]);
    }

    public function create()
    {
        $entite = (int) $this->champ('entite_id');

        if (! service('droits')->peut('demandes.saisir', $entite ?: null)) {
            return redirect()->back()->withInput()->with('erreurs', ['Vous ne pouvez pas saisir de demande pour cette entité.']);
        }

        $resultat = $this->enregistrer($this->demandes, fn () => $this->demandes->insert([
            'entite_id'    => $entite,
            'filiere_id'   => (int) $this->champ('filiere_id'),
            'demandeur_id' => $this->moi(),
            'objet'        => $this->champ('objet'),
            'date_besoin'  => $this->champ('date_besoin'),
            'priorite'     => $this->champ('priorite') ?: 'normale',
        ]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        return redirect()->to('demandes/' . $resultat)->with('message', 'Demande créée en brouillon : ajoutez les lignes, puis soumettez-la.');
    }

    private function fiche(int $id): array
    {
        $demande = $this->demandes->fiche($id);

        if ($demande === null) {
            throw PageNotFoundException::forPageNotFound('Demande introuvable.');
        }

        return $demande;
    }

    public function show(int $id)
    {
        $d          = $this->fiche($id);
        $moi        = $this->moi();
        $saisie     = service('droits')->peut('demandes.saisir', (int) $d['entite_id']);
        $peutServir = in_array($d['statut'], ['validee', 'en_service'], true) && $this->demandes->peutServir($d, $moi);

        return view('demandes/show', [
            'titrePage'    => 'Demande ' . $d['numero'],
            'd'            => $d,
            'lignes'       => $this->demandes->lignes($id),
            'etapes'       => $this->demandes->etapes($id),
            'evenements'   => $this->demandes->evenements($id),
            'services'     => $this->demandes->services($id),
            'modifiable'   => $d['statut'] === 'brouillon' && $saisie,
            'peutDecider'  => $this->demandes->peutDecider($d, $moi),
            'peutServir'   => $peutServir,
            'peutCloturer' => in_array($d['statut'], ['validee', 'en_service'], true) && ($saisie || $peutServir),
            'peutAnnuler'  => in_array($d['statut'], ['brouillon', 'soumise', 'en_validation', 'validee'], true) && $saisie,
            'origines'     => $peutServir ? $this->demandes->emplacementsDe((int) $d['entite_service_id']) : [],
            'destinations' => $peutServir ? $this->demandes->emplacementsLivraison($id) : [],
        ]);
    }

    public function edit(int $id)
    {
        $d = $this->fiche($id);

        return view('demandes/form', [
            'titrePage' => 'Modifier la demande ' . $d['numero'],
            'demande'   => $d,
            'entites'   => [],
            'filieres'  => [],
        ]);
    }

    public function update(int $id)
    {
        $this->fiche($id);

        $resultat = $this->enregistrer($this->demandes, fn () => $this->demandes->update($id, [
            'objet'       => $this->champ('objet'),
            'date_besoin' => $this->champ('date_besoin'),
            'priorite'    => $this->champ('priorite') ?: 'normale',
        ]));

        if ($resultat instanceof RedirectResponse) {
            return $resultat;
        }

        // Hors brouillon, la base ne modifie rien (aucune ligne visée par la politique de modification)
        if (db_connect()->affectedRows() === 0) {
            return redirect()->to('demandes/' . $id)->with('erreur', 'Seule une demande en brouillon se modifie.');
        }

        return redirect()->to('demandes/' . $id)->with('message', 'Demande modifiée.');
    }

    public function ajouterLigne(int $id)
    {
        $d       = $this->fiche($id);
        $article = (new ArticleModel())->where('codification', strtoupper($this->champ('codification')))->first();

        if ($article === null) {
            return redirect()->to('demandes/' . $id)->withInput()->with('erreur', 'Article inconnu : vérifiez la codification.');
        }

        $lignes   = new LigneDemandeModel();
        $quantite = str_replace([',', ' '], ['.', ''], $this->champ('quantite'));

        try {
            if (! $lignes->insert(['demande_id' => $d['id'], 'article_id' => $article['id'], 'quantite' => $quantite, 'observation' => $this->champ('observation') ?: null])) {
                return redirect()->to('demandes/' . $id)->withInput()->with('erreur', implode(' ', $lignes->errors()));
            }
        } catch (Throwable $e) {
            return redirect()->to('demandes/' . $id)->withInput()->with('erreur', ServiceDemandes::message($e));
        }

        return redirect()->to('demandes/' . $id)->with('message', 'Ligne ajoutée.');
    }

    public function supprimerLigne(int $id, int $ligne)
    {
        try {
            (new LigneDemandeModel())->where('demande_id', $id)->delete($ligne);
        } catch (Throwable $e) {
            return redirect()->to('demandes/' . $id)->with('erreur', ServiceDemandes::message($e));
        }

        return redirect()->to('demandes/' . $id)->with('message', 'Ligne retirée.');
    }

    public function soumettre(int $id)
    {
        return $this->transition($id, 'SELECT demande_soumettre(?) AS r', [$id], [
            'soumise' => 'Demande soumise : elle suit maintenant son circuit de validation.',
            'validee' => 'Demande soumise et validée d\'office (aucune étape de validation ne s\'applique).',
        ]);
    }

    public function decider(int $id)
    {
        $valider = $this->request->getPost('decision') === 'valider';

        return $this->transition($id, 'SELECT demande_decider(?, ?, ?) AS r', [$id, $valider, $this->champ('commentaire')], [
            'en_validation' => 'Étape validée : la demande passe à l\'étape suivante.',
            'validee'       => 'Demande validée : elle peut être servie.',
            'refusee'       => 'Demande refusée.',
        ]);
    }

    public function cloturer(int $id)
    {
        return $this->transition($id, 'SELECT demande_cloturer(?, ?) AS r', [$id, $this->champ('motif')], ['cloturee' => 'Demande clôturée.']);
    }

    public function annuler(int $id)
    {
        return $this->transition($id, 'SELECT demande_annuler(?, ?) AS r', [$id, $this->champ('motif')], ['annulee' => 'Demande annulée.']);
    }

    public function servir(int $id)
    {
        $ligne = (int) $this->request->getPost('ligne_id');

        if ((new LigneDemandeModel())->where(['id' => $ligne, 'demande_id' => $id])->first() === null) {
            return redirect()->to('demandes/' . $id)->with('erreur', 'Choisissez la ligne à servir.');
        }

        try {
            $mouvements = (new ServiceDemandes(db_connect()))->servir($ligne, [
                'origine'       => (string) $this->request->getPost('origine'),
                'destination'   => (string) $this->request->getPost('destination'),
                'quantite'      => (string) $this->request->getPost('quantite'),
                'numero_lot'    => (string) $this->request->getPost('numero_lot'),
                'numeros_serie' => (string) $this->request->getPost('numeros_serie'),
            ]);
        } catch (DomainException $e) {
            return redirect()->to('demandes/' . $id)->withInput()->with('erreur', $e->getMessage());
        }

        return redirect()->to('demandes/' . $id)->with('message', count($mouvements) . ' perception(s) enregistrée(s) et rattachée(s) à la demande ; le bon de perception est émis.');
    }

    /**
     * Appelle une fonction de transition de la base et affiche son résultat.
     *
     * @param array<string, string> $messages message selon le nouveau statut
     */
    private function transition(int $id, string $sql, array $valeurs, array $messages): RedirectResponse
    {
        try {
            $statut = (string) db_connect()->query($sql, $valeurs)->getRow()->r;
        } catch (Throwable $e) {
            return redirect()->to('demandes/' . $id)->withInput()->with('erreur', ServiceDemandes::message($e));
        }

        return redirect()->to('demandes/' . $id)->with('message', $messages[$statut] ?? 'Opération enregistrée.');
    }
}
