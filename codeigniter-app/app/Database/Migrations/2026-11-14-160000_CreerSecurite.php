<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sécurité du lot 1 (sections 4.2-2, 4.3 et 5 du cahier des charges).
 *
 * - Double facteur par rôle : un rôle peut être déclaré sensible ; tout utilisateur qui
 *   détient ce rôle (par affectation ou par délégation active) confirme sa connexion par
 *   un code. La règle est paramétrable sur chaque rôle (ADM-02).
 * - Contrôle par attribut « sensibilité de l'article » (ABAC) : mouvementer un article
 *   sensible, d'armement ou de munitions exige, en plus de stocks.mouvementer, la
 *   permission stocks.sensibles sur l'entité responsable du mouvement. La règle est une
 *   politique de sécurité de la base, donc indépendante de l'interface.
 *   Les régularisations d'inventaire en sont exemptées : elles relèvent du droit
 *   inventaires.valider et de la séparation des tâches (RG-32).
 */
class CreerSecurite extends Migration
{
    public function up()
    {
        $this->db->transException(true)->transStart();

        $this->db->query('ALTER TABLE role ADD COLUMN double_facteur boolean NOT NULL DEFAULT false');
        $this->db->query("COMMENT ON COLUMN role.double_facteur IS 'Connexion confirmée par un code pour les détenteurs de ce rôle (section 4.3)'");
        // Profils sensibles par défaut : administration, direction, audit
        $this->db->query("UPDATE role SET double_facteur = true WHERE code IN ('ADMIN_SYSTEME', 'ADMIN_FONCTIONNEL', 'DIRECTEUR_DCMA', 'AUDITEUR')");

        $this->db->query(<<<'SQL'
            CREATE FUNCTION securite_double_facteur_requis(uid integer) RETURNS boolean
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT EXISTS (
                    SELECT 1 FROM droits_affectations_actives(uid) a
                    JOIN role r ON r.id = a.role_id
                    WHERE r.double_facteur
                )
            $$
            SQL);

        $this->db->query(<<<'SQL'
            INSERT INTO permission (code, libelle) VALUES
                ('stocks.sensibles', 'Mouvementer le matériel sensible : armement, munitions, matériel sensible')
            SQL);

        $this->db->query(<<<'SQL'
            CREATE FUNCTION article_sensible(id_article integer) RETURNS boolean
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT sensibilite <> 'courant' FROM article WHERE id = id_article
            $$
            SQL);

        // Politique restrictive : elle s'ajoute (ET) à la politique « saisie » existante
        $this->db->query(<<<'SQL'
            CREATE POLICY materiel_sensible ON mouvement AS RESTRICTIVE FOR INSERT WITH CHECK (
                type = 'regularisation'
                OR NOT article_sensible(article_id)
                OR droits_peut('stocks.sensibles',
                    mouvement_entite_responsable(type, emplacement_origine_id, emplacement_destination_id, mouvement_annule_id))
            )
            SQL);

        $this->db->transComplete();
        $this->db->transException(false);
    }

    public function down()
    {
        $this->db->query('DROP POLICY IF EXISTS materiel_sensible ON mouvement');
        $this->db->query('DROP FUNCTION IF EXISTS article_sensible(integer), securite_double_facteur_requis(integer)');
        $this->db->query("DELETE FROM role_permission WHERE permission = 'stocks.sensibles'");
        $this->db->query("DELETE FROM permission WHERE code = 'stocks.sensibles'");
        $this->db->query('ALTER TABLE role DROP COLUMN IF EXISTS double_facteur');
    }
}
