<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modèle de base des tables journalisées.
 *
 * Après une insertion, CodeIgniter lit l'identifiant créé avec LASTVAL() : la dernière
 * valeur de séquence de la session. Or le déclencheur d'audit insère aussitôt une ligne
 * dans journal_audit, dont la séquence devient la dernière utilisée : LASTVAL() renverrait
 * l'identifiant de la ligne du journal. On lit donc la séquence de la table elle-même.
 */
abstract class BaseModel extends Model
{
    protected function doInsert(array $row)
    {
        $resultat = parent::doInsert($row);

        if ($resultat && $this->useAutoIncrement) {
            $this->insertID = $this->db->insertID($this->db->prefixTable($this->table), $this->primaryKey);
        }

        return $resultat;
    }
}
