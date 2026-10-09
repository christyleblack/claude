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
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL', 'articles_user_id_foreign');
        $this->forge->processIndexes('articles');
    }

    public function down()
    {
        $this->forge->dropForeignKey('articles', 'articles_user_id_foreign');
        $this->forge->dropColumn('articles', 'user_id');
    }
}
