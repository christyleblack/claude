<?php

namespace App\Models\Demandes;

use CodeIgniter\Model;

/**
 * Notifications de l'utilisateur connecté (DEM-04) : la base les crée et ne montre à
 * chacun que les siennes ; l'application ne peut que les marquer lues.
 */
class NotificationModel extends Model
{
    protected $table         = 'notification';
    protected $allowedFields = ['lue_le'];

    public function nonLues(): int
    {
        return $this->where('lue_le', null)->countAllResults();
    }

    public function recentes(int $nombre = 50): array
    {
        return $this->db->table('notification n')
            ->select('n.*, d.numero')
            ->join('demande d', 'd.id = n.demande_id', 'left')
            ->orderBy('n.lue_le IS NULL', 'DESC', false)->orderBy('n.id', 'DESC')
            ->limit($nombre)->get()->getResultArray();
    }

    public function marquerLue(int $id): void
    {
        $this->db->query('UPDATE notification SET lue_le = now() WHERE id = ? AND lue_le IS NULL', [$id]);
    }

    public function toutMarquerLu(): void
    {
        $this->db->query('UPDATE notification SET lue_le = now() WHERE lue_le IS NULL');
    }
}
