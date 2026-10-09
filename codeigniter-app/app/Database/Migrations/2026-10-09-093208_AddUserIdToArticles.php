<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUserIdToArticles extends Migration
{
    public function up()
    {
        // Nullable : les articles créés avant cette migration n'ont pas d'auteur
        $this->forge->addColumn('articles', [
            'user_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'id'],
        ]);
        // Si l'auteur est supprimé, ses articles restent mais sans auteur
        // Pas de nom explicite : SQLite ne les prend pas en charge, CodeIgniter en génère un
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->processIndexes('articles');
    }

    public function down()
    {
        // Le nom de la clé étrangère dépend du pilote (MySQL, SQLite) : on le lit dans la base
        foreach ($this->db->getForeignKeyData('articles') as $cle) {
            if ($cle->column_name === ['user_id']) {
                $this->forge->dropForeignKey('articles', $cle->constraint_name);
            }
        }

        $this->forge->dropColumn('articles', 'user_id');
    }
}
