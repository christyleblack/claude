<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bons de mouvement (STK-06) : bon de perception, bon de sortie, bordereau de transfert.
 *
 * - Un bon est créé à la saisie, dans la même transaction que ses mouvements ; une saisie
 *   de plusieurs numéros de série donne un seul bon de plusieurs lignes.
 * - Numérotation continue et sans trou, par type et par année (ex. BS-2026-000001) :
 *   compteur verrouillé ligne par ligne ; une saisie refusée ne consomme pas de numéro.
 * - Bon et lien bon–mouvements en insertion seule ; le PDF est produit à la demande.
 *   La table mouvement n'est pas modifiée, pour que les empreintes restent vérifiables.
 * - Un bon n'est visible que si ses mouvements le sont.
 */
class CreerBons extends Migration
{
    public function up()
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE bon_compteur (
                type    varchar(15) NOT NULL CHECK (type IN ('perception', 'sortie', 'transfert')),
                annee   integer     NOT NULL,
                dernier integer     NOT NULL DEFAULT 0,
                PRIMARY KEY (type, annee)
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE bon (
                id         serial PRIMARY KEY,
                type       varchar(15) NOT NULL CHECK (type IN ('perception', 'sortie', 'transfert')),
                annee      integer     NOT NULL,
                rang       integer     NOT NULL,
                numero     varchar(20) NOT NULL UNIQUE,
                cree_par   integer     NOT NULL REFERENCES users (id),
                cree_le    timestamptz NOT NULL DEFAULT now(),
                UNIQUE (type, annee, rang)
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE bon_mouvement (
                bon_id       integer NOT NULL REFERENCES bon (id),
                mouvement_id bigint  NOT NULL UNIQUE REFERENCES mouvement (id),
                PRIMARY KEY (bon_id, mouvement_id)
            )
            SQL);

        // Numéro attribué par la base : compteur verrouillé, donc continu et sans doublon
        $this->db->query(<<<'SQL'
            CREATE FUNCTION bon_numeroter() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                prefixe text := CASE NEW.type WHEN 'perception' THEN 'BP' WHEN 'sortie' THEN 'BS' ELSE 'BT' END;
            BEGIN
                NEW.annee    := extract(year FROM now())::integer;
                NEW.cree_le  := now();
                NEW.cree_par := COALESCE(droits_utilisateur(), NEW.cree_par);

                INSERT INTO bon_compteur (type, annee, dernier) VALUES (NEW.type, NEW.annee, 1)
                ON CONFLICT (type, annee) DO UPDATE SET dernier = bon_compteur.dernier + 1
                RETURNING dernier INTO NEW.rang;

                NEW.numero := prefixe || '-' || NEW.annee || '-' || lpad(NEW.rang::text, 6, '0');
                RETURN NEW;
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER bon_numeroter BEFORE INSERT ON bon FOR EACH ROW EXECUTE FUNCTION bon_numeroter()');

        // Le type du mouvement doit correspondre au type du bon
        $this->db->query(<<<'SQL'
            CREATE FUNCTION bon_mouvement_verifier() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                type_bon varchar := (SELECT type FROM bon WHERE id = NEW.bon_id);
                type_mvt varchar := (SELECT type FROM mouvement WHERE id = NEW.mouvement_id);
                attendu  varchar;
            BEGIN
                -- Calculé à part : PL/pgSQL arrête la condition d'un IF au premier THEN
                attendu := CASE
                    WHEN type_mvt = 'perception' THEN 'perception'
                    WHEN type_mvt IN ('sortie', 'perte', 'reforme') THEN 'sortie'
                    WHEN type_mvt IN ('transfert', 'reintegration') THEN 'transfert'
                END;
                IF attendu IS DISTINCT FROM type_bon THEN
                    RAISE EXCEPTION 'Bon refusé : un mouvement de type % ne figure pas sur un bon de type %', type_mvt, type_bon
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER bon_mouvement_verifier BEFORE INSERT ON bon_mouvement FOR EACH ROW EXECUTE FUNCTION bon_mouvement_verifier()');

        // Insertion seule, même pour le propriétaire des tables
        $this->db->query(<<<'SQL'
            CREATE FUNCTION bon_immuable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Un bon émis ne se modifie pas ni ne se supprime' USING ERRCODE = 'insufficient_privilege';
            END;
            $$
            SQL);
        foreach (['bon', 'bon_mouvement'] as $table) {
            $this->db->query("CREATE TRIGGER {$table}_immuable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION bon_immuable()");
            $this->db->query("CREATE TRIGGER {$table}_immuable_truncate BEFORE TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION bon_immuable()");
        }

        // Cloisonnement : un bon est visible si l'un de ses mouvements l'est (RLS de mouvement)
        $this->db->query('ALTER TABLE bon ENABLE ROW LEVEL SECURITY');
        $this->db->query(<<<'SQL'
            CREATE POLICY lecture ON bon FOR SELECT USING (
                EXISTS (SELECT 1 FROM bon_mouvement bm JOIN mouvement m ON m.id = bm.mouvement_id WHERE bm.bon_id = bon.id)
            )
            SQL);
        $this->db->query("CREATE POLICY creation ON bon FOR INSERT WITH CHECK (droits_peut('stocks.mouvementer'))");

        // L'application crée des bons et leurs liens ; le compteur n'est tenu que par la base
        $this->db->query('REVOKE ALL ON bon_compteur FROM gestion_app');
        $this->db->query('REVOKE UPDATE, DELETE, TRUNCATE ON bon, bon_mouvement FROM gestion_app');
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS bon_mouvement, bon, bon_compteur CASCADE');
        $this->db->query('DROP FUNCTION IF EXISTS bon_immuable(), bon_mouvement_verifier(), bon_numeroter()');
    }
}
