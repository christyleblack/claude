<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Extension ltree : chemins hiérarchiques indexables, utilisés pour les arbres
 * hiérarchique et logistique de l'organisation (cahier des charges, section 6).
 * Extension « de confiance » depuis PostgreSQL 13 : le propriétaire de la base
 * peut l'activer sans être superutilisateur.
 */
class ActiverExtensionLtree extends Migration
{
    public function up()
    {
        $this->db->query('CREATE EXTENSION IF NOT EXISTS ltree');
    }

    public function down()
    {
        $this->db->query('DROP EXTENSION IF EXISTS ltree');
    }
}
