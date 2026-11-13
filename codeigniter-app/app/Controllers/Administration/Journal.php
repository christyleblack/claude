<?php

namespace App\Controllers\Administration;

use App\Controllers\BaseController;

/**
 * Consultation du journal d'audit (ADM-03), en lecture seule.
 */
class Journal extends BaseController
{
    private const PAR_PAGE = 50;

    public function index()
    {
        // Le journal n'est pas cloisonné par entité : sa consultation exige la portée nationale
        if (! service('droits')->peut('audit.consulter', null, true)) {
            return redirect()->to('/')->with('erreur', 'La consultation du journal d\'audit exige un rôle de portée nationale.');
        }

        $filtres = [
            'utilisateur' => trim((string) $this->request->getGet('utilisateur')),
            'objet'       => trim((string) $this->request->getGet('objet')),
            'action'      => trim((string) $this->request->getGet('action')),
            'du'          => trim((string) $this->request->getGet('du')),
            'au'          => trim((string) $this->request->getGet('au')),
        ];
        $page = max(1, (int) $this->request->getGet('page'));

        $requete = db_connect()->table('journal_audit j')
            ->select('j.*, u.username')
            ->join('users u', 'u.id = j.utilisateur_id', 'left');

        if ($filtres['utilisateur'] !== '') {
            $requete->where('u.username', $filtres['utilisateur']);
        }
        if ($filtres['objet'] !== '') {
            $requete->where('j.objet_type', $filtres['objet']);
        }
        if ($filtres['action'] !== '') {
            $requete->where('j.action', strtoupper($filtres['action']));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres['du'])) {
            $requete->where('j.horodatage >=', $filtres['du']);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtres['au'])) {
            $requete->where('j.horodatage <', date('Y-m-d', strtotime($filtres['au'] . ' +1 day')));
        }

        $lignes = $requete->orderBy('j.id', 'DESC')
            ->limit(self::PAR_PAGE + 1, ($page - 1) * self::PAR_PAGE)
            ->get()->getResultArray();

        return view('administration/journal/index', [
            'titrePage' => 'Journal d\'audit',
            'lignes'    => array_slice($lignes, 0, self::PAR_PAGE),
            'filtres'   => $filtres,
            'page'      => $page,
            'suivante'  => count($lignes) > self::PAR_PAGE,
        ]);
    }
}
