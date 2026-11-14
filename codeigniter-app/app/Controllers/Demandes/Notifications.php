<?php

namespace App\Controllers\Demandes;

use App\Controllers\BaseController;
use App\Models\Demandes\NotificationModel;

/**
 * Notifications de l'utilisateur (DEM-04) : créées par la base à chaque étape d'une demande.
 */
class Notifications extends BaseController
{
    public function index()
    {
        return view('demandes/notifications', [
            'titrePage'     => 'Notifications',
            'notifications' => (new NotificationModel())->recentes(),
        ]);
    }

    /**
     * Marque la notification lue et ouvre la demande concernée.
     */
    public function lire(int $id)
    {
        $model        = new NotificationModel();
        $notification = $model->find($id);

        if ($notification === null) {
            return redirect()->to('notifications');
        }

        $model->marquerLue($id);

        return redirect()->to($notification['demande_id'] !== null ? 'demandes/' . $notification['demande_id'] : 'notifications');
    }

    public function toutLire()
    {
        (new NotificationModel())->toutMarquerLu();

        return redirect()->to('notifications')->with('message', 'Toutes les notifications sont marquées lues.');
    }
}
