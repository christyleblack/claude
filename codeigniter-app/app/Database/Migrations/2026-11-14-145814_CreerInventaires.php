<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Module Inventaires (lot 1, INV-01 à INV-03, RG-31, RG-32).
 * Voir docs/lot1-modele-de-donnees.md, section 6.
 *
 * - Statuts : planifié → en cours (quantités théoriques figées au lancement) → compté
 *   → validé, ou annulé. Chaque transition passe par une fonction de la base qui vérifie
 *   les droits et l'ordre des étapes : l'application ne modifie aucune ligne directement.
 * - Pendant l'inventaire, les emplacements inventoriés sont gelés : aucun mouvement,
 *   sauf les régularisations de la validation.
 * - Validation : le valideur n'est pas le compteur (RG-32) ; chaque écart génère un
 *   mouvement de régularisation (RG-31), dans la même transaction.
 * - Inventaire contradictoire (INV-03) : signatures du cédant et du prenant exigées.
 */
class CreerInventaires extends Migration
{
    private const FONCTIONS = [
        'inventaire_couvre(integer, integer)', 'inventaire_lancer(integer)', 'inventaire_ajouter_ligne(integer, integer, integer, integer, integer, integer, numeric)',
        'inventaire_compter(integer, numeric)', 'inventaire_terminer_comptage(integer)', 'inventaire_signer(integer, varchar)',
        'inventaire_valider(integer)', 'inventaire_annuler(integer)', 'inventaire_verifier()', 'mouvement_verifier_gel()',
    ];

    public function up()
    {
        // CodeIgniter n'exécute pas une migration dans une transaction : en cas d'erreur, les
        // objets déjà créés resteraient, sans les protections de fin de migration. PostgreSQL
        // sait annuler aussi les créations de tables : tout ou rien.
        $this->db->transException(true)->transStart();

        $this->db->query(<<<'SQL'
            INSERT INTO permission (code, libelle) VALUES
                ('inventaires.gerer', 'Planifier, lancer et annuler les inventaires de son périmètre'),
                ('inventaires.valider', 'Valider les inventaires et leurs régularisations (sans avoir compté)')
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE inventaire (
                id             serial PRIMARY KEY,
                type           varchar(15) NOT NULL CHECK (type IN ('complet', 'tournant', 'contradictoire')),
                entite_id      integer     NOT NULL REFERENCES entite (id),
                -- Facultatif : un seul emplacement (et ce qu'il contient) ; sinon toute l'entité
                emplacement_id integer     REFERENCES emplacement (id),
                statut         varchar(10) NOT NULL DEFAULT 'planifie'
                               CHECK (statut IN ('planifie', 'en_cours', 'compte', 'valide', 'annule')),
                date_prevue    date        NOT NULL,
                compteur_id    integer     NOT NULL REFERENCES users (id),
                valideur_id    integer     REFERENCES users (id),
                cedant_id      integer     REFERENCES users (id),
                prenant_id     integer     REFERENCES users (id),
                observations   text,
                cree_par       integer     REFERENCES users (id),
                created_at     timestamp,
                updated_at     timestamp,
                lance_le       timestamptz,
                compte_le      timestamptz,
                valide_le      timestamptz,
                annule_le      timestamptz,
                -- RG-32 : celui qui compte ne valide pas
                CONSTRAINT valideur_different_du_compteur CHECK (valideur_id IS DISTINCT FROM compteur_id),
                -- INV-03 : cédant et prenant désignés, distincts
                CONSTRAINT contradictoire_avec_cedant_et_prenant CHECK (
                    type <> 'contradictoire' OR (cedant_id IS NOT NULL AND prenant_id IS NOT NULL AND cedant_id <> prenant_id)
                )
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE ligne_inventaire (
                id                          serial PRIMARY KEY,
                inventaire_id               integer        NOT NULL REFERENCES inventaire (id),
                article_id                  integer        NOT NULL REFERENCES article (id),
                lot_id                      integer        REFERENCES lot (id),
                materiel_id                 integer        REFERENCES materiel_serialise (id),
                emplacement_id              integer        NOT NULL REFERENCES emplacement (id),
                proprietaire_entite_id      integer        NOT NULL REFERENCES entite (id),
                -- Figée au lancement, pour que les mouvements ultérieurs ne faussent pas l'écart
                quantite_theorique          numeric(14, 3) NOT NULL DEFAULT 0 CHECK (quantite_theorique >= 0),
                quantite_comptee            numeric(14, 3) CHECK (quantite_comptee >= 0),
                ecart                       numeric(14, 3) GENERATED ALWAYS AS (quantite_comptee - quantite_theorique) STORED,
                -- Ligne ajoutée au comptage : matériel trouvé mais absent du stock théorique
                ajoutee                     boolean        NOT NULL DEFAULT false,
                mouvement_regularisation_id bigint         REFERENCES mouvement (id),
                CONSTRAINT ligne_unique UNIQUE NULLS NOT DISTINCT (inventaire_id, article_id, lot_id, materiel_id, emplacement_id, proprietaire_entite_id),
                CONSTRAINT serie_presente_ou_absente CHECK (materiel_id IS NULL OR quantite_comptee IS NULL OR quantite_comptee IN (0, 1))
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE signature_inventaire (
                inventaire_id  integer     NOT NULL REFERENCES inventaire (id),
                qualite        varchar(10) NOT NULL CHECK (qualite IN ('cedant', 'prenant')),
                utilisateur_id integer     NOT NULL REFERENCES users (id),
                horodatage     timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (inventaire_id, qualite)
            )
            SQL);

        // Un emplacement est-il dans le champ de l'inventaire ? (emplacement choisi et ce qu'il contient,
        // ou tous les emplacements des sites de l'entité)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION inventaire_couvre(id_inventaire integer, id_emplacement integer) RETURNS boolean
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT CASE
                    WHEN i.emplacement_id IS NULL THEN emplacement_entite(id_emplacement) = i.entite_id
                    ELSE EXISTS (
                        WITH RECURSIVE dedans AS (
                            SELECT e.id FROM emplacement e WHERE e.id = i.emplacement_id
                            UNION
                            SELECT e.id FROM emplacement e JOIN dedans d ON e.parent_id = d.id
                        )
                        SELECT 1 FROM dedans WHERE dedans.id = id_emplacement
                    )
                END
                FROM inventaire i WHERE i.id = id_inventaire
            $$
            SQL);

        // Cohérence à la planification
        $this->db->query(<<<'SQL'
            CREATE FUNCTION inventaire_verifier() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            BEGIN
                IF NEW.emplacement_id IS NOT NULL AND emplacement_entite(NEW.emplacement_id) <> NEW.entite_id THEN
                    RAISE EXCEPTION 'Inventaire refusé : l''emplacement n''appartient pas à l''entité inventoriée' USING ERRCODE = 'check_violation';
                END IF;
                IF TG_OP = 'INSERT' THEN
                    NEW.statut   := 'planifie';
                    NEW.cree_par := COALESCE(droits_utilisateur(), NEW.cree_par);
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER inventaire_verifier BEFORE INSERT OR UPDATE ON inventaire FOR EACH ROW EXECUTE FUNCTION inventaire_verifier()');

        // Lancement : quantités théoriques figées à partir du stock
        $this->db->query(<<<'SQL'
            CREATE FUNCTION inventaire_lancer(id_inventaire integer) RETURNS void
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                inv inventaire%ROWTYPE;
            BEGIN
                SELECT * INTO inv FROM inventaire WHERE id = id_inventaire FOR UPDATE;
                IF NOT FOUND OR NOT droits_peut('inventaires.gerer', inv.entite_id) THEN
                    RAISE EXCEPTION 'Inventaire refusé : vous ne pouvez pas lancer cet inventaire' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF inv.statut <> 'planifie' THEN
                    RAISE EXCEPTION 'Inventaire refusé : seul un inventaire planifié peut être lancé' USING ERRCODE = 'check_violation';
                END IF;
                IF EXISTS (
                    SELECT 1 FROM inventaire autre
                    WHERE autre.id <> inv.id AND autre.statut IN ('en_cours', 'compte') AND autre.entite_id = inv.entite_id
                ) THEN
                    RAISE EXCEPTION 'Inventaire refusé : un autre inventaire de cette entité est déjà en cours' USING ERRCODE = 'check_violation';
                END IF;

                INSERT INTO ligne_inventaire (inventaire_id, article_id, lot_id, materiel_id, emplacement_id, proprietaire_entite_id, quantite_theorique)
                SELECT inv.id, s.article_id, s.lot_id, s.materiel_id, s.emplacement_id, s.proprietaire_entite_id, s.quantite
                FROM stock s
                WHERE inventaire_couvre(inv.id, s.emplacement_id);

                UPDATE inventaire SET statut = 'en_cours', lance_le = now(), updated_at = now() WHERE id = inv.id;
            END;
            $$
            SQL);

        // Comptage : réservé au compteur désigné, pendant l'inventaire
        $this->db->query(<<<'SQL'
            CREATE FUNCTION inventaire_compter(id_ligne integer, quantite numeric) RETURNS void
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                inv inventaire%ROWTYPE;
            BEGIN
                SELECT i.* INTO inv FROM inventaire i JOIN ligne_inventaire l ON l.inventaire_id = i.id WHERE l.id = id_ligne;
                IF NOT FOUND OR inv.compteur_id IS DISTINCT FROM droits_utilisateur() THEN
                    RAISE EXCEPTION 'Inventaire refusé : seul le compteur désigné saisit les comptages' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF inv.statut <> 'en_cours' THEN
                    RAISE EXCEPTION 'Inventaire refusé : le comptage n''est possible que pendant l''inventaire' USING ERRCODE = 'check_violation';
                END IF;
                UPDATE ligne_inventaire SET quantite_comptee = quantite WHERE id = id_ligne;
            END;
            $$
            SQL);

        // Matériel trouvé au comptage mais absent du stock théorique
        $this->db->query(<<<'SQL'
            CREATE FUNCTION inventaire_ajouter_ligne(id_inventaire integer, id_article integer, id_lot integer, id_materiel integer,
                                                     id_emplacement integer, id_proprietaire integer, quantite numeric)
            RETURNS integer LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                inv   inventaire%ROWTYPE;
                ligne integer;
            BEGIN
                SELECT * INTO inv FROM inventaire WHERE id = id_inventaire;
                IF NOT FOUND OR inv.compteur_id IS DISTINCT FROM droits_utilisateur() THEN
                    RAISE EXCEPTION 'Inventaire refusé : seul le compteur désigné saisit les comptages' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF inv.statut <> 'en_cours' THEN
                    RAISE EXCEPTION 'Inventaire refusé : le comptage n''est possible que pendant l''inventaire' USING ERRCODE = 'check_violation';
                END IF;
                IF NOT inventaire_couvre(inv.id, id_emplacement) THEN
                    RAISE EXCEPTION 'Inventaire refusé : cet emplacement n''est pas dans le champ de l''inventaire' USING ERRCODE = 'check_violation';
                END IF;

                INSERT INTO ligne_inventaire (inventaire_id, article_id, lot_id, materiel_id, emplacement_id, proprietaire_entite_id, quantite_theorique, quantite_comptee, ajoutee)
                VALUES (inv.id, id_article, id_lot, id_materiel, id_emplacement, id_proprietaire, 0, quantite, true)
                RETURNING id INTO ligne;

                RETURN ligne;
            END;
            $$
            SQL);

        $this->db->query(<<<'SQL'
            CREATE FUNCTION inventaire_terminer_comptage(id_inventaire integer) RETURNS void
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                inv inventaire%ROWTYPE;
            BEGIN
                SELECT * INTO inv FROM inventaire WHERE id = id_inventaire FOR UPDATE;
                IF NOT FOUND OR inv.compteur_id IS DISTINCT FROM droits_utilisateur() THEN
                    RAISE EXCEPTION 'Inventaire refusé : seul le compteur désigné termine le comptage' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF inv.statut <> 'en_cours' THEN
                    RAISE EXCEPTION 'Inventaire refusé : l''inventaire n''est pas en cours de comptage' USING ERRCODE = 'check_violation';
                END IF;
                IF EXISTS (SELECT 1 FROM ligne_inventaire WHERE inventaire_id = inv.id AND quantite_comptee IS NULL) THEN
                    RAISE EXCEPTION 'Inventaire refusé : toutes les lignes doivent être comptées' USING ERRCODE = 'check_violation';
                END IF;
                UPDATE inventaire SET statut = 'compte', compte_le = now(), updated_at = now() WHERE id = inv.id;
            END;
            $$
            SQL);

        // Inventaire contradictoire : signature du cédant ou du prenant désigné (H-07 : signature
        // électronique simple, par l'utilisateur authentifié, horodatée et journalisée)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION inventaire_signer(id_inventaire integer, qualite_signataire varchar) RETURNS void
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                inv     inventaire%ROWTYPE;
                attendu integer;
            BEGIN
                SELECT * INTO inv FROM inventaire WHERE id = id_inventaire;
                IF NOT FOUND OR inv.type <> 'contradictoire' THEN
                    RAISE EXCEPTION 'Inventaire refusé : seul un inventaire contradictoire se signe' USING ERRCODE = 'check_violation';
                END IF;
                IF inv.statut <> 'compte' THEN
                    RAISE EXCEPTION 'Inventaire refusé : la signature intervient après le comptage, avant la validation' USING ERRCODE = 'check_violation';
                END IF;
                -- Calculé à part : PL/pgSQL arrête la condition d'un IF au premier THEN
                attendu := CASE qualite_signataire WHEN 'cedant' THEN inv.cedant_id WHEN 'prenant' THEN inv.prenant_id END;
                IF droits_utilisateur() IS DISTINCT FROM attendu THEN
                    RAISE EXCEPTION 'Inventaire refusé : seul le % désigné peut signer à ce titre', qualite_signataire USING ERRCODE = 'insufficient_privilege';
                END IF;
                INSERT INTO signature_inventaire (inventaire_id, qualite, utilisateur_id) VALUES (inv.id, qualite_signataire, droits_utilisateur());
            END;
            $$
            SQL);

        // Validation : séparation des tâches (RG-32), régularisation de chaque écart (RG-31)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION inventaire_valider(id_inventaire integer) RETURNS integer
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                inv        inventaire%ROWTYPE;
                l          ligne_inventaire%ROWTYPE;
                mouvement  bigint;
                nombre     integer := 0;
            BEGIN
                SELECT * INTO inv FROM inventaire WHERE id = id_inventaire FOR UPDATE;
                IF NOT FOUND OR NOT droits_peut('inventaires.valider', inv.entite_id) THEN
                    RAISE EXCEPTION 'Inventaire refusé : vous ne pouvez pas valider cet inventaire' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF droits_utilisateur() = inv.compteur_id THEN
                    RAISE EXCEPTION 'Inventaire refusé : celui qui a compté ne peut pas valider la régularisation (RG-32)' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF inv.statut <> 'compte' THEN
                    RAISE EXCEPTION 'Inventaire refusé : seul un inventaire compté peut être validé' USING ERRCODE = 'check_violation';
                END IF;
                IF inv.type = 'contradictoire' AND (SELECT count(*) FROM signature_inventaire WHERE inventaire_id = inv.id) < 2 THEN
                    RAISE EXCEPTION 'Inventaire refusé : le cédant et le prenant doivent d''abord signer (INV-03)' USING ERRCODE = 'check_violation';
                END IF;

                FOR l IN SELECT * FROM ligne_inventaire WHERE inventaire_id = inv.id AND ecart <> 0 ORDER BY id LOOP
                    INSERT INTO mouvement (type, article_id, lot_id, materiel_id, quantite,
                                           emplacement_origine_id, emplacement_destination_id, proprietaire_entite_id, auteur_id, motif)
                    VALUES ('regularisation', l.article_id, l.lot_id, l.materiel_id, abs(l.ecart),
                            CASE WHEN l.ecart < 0 THEN l.emplacement_id END,
                            CASE WHEN l.ecart > 0 THEN l.emplacement_id END,
                            l.proprietaire_entite_id, droits_utilisateur(),
                            'Régularisation de l''inventaire n° ' || inv.id)
                    RETURNING id INTO mouvement;
                    UPDATE ligne_inventaire SET mouvement_regularisation_id = mouvement WHERE id = l.id;
                    nombre := nombre + 1;
                END LOOP;

                UPDATE inventaire SET statut = 'valide', valideur_id = droits_utilisateur(), valide_le = now(), updated_at = now() WHERE id = inv.id;
                RETURN nombre;
            END;
            $$
            SQL);

        $this->db->query(<<<'SQL'
            CREATE FUNCTION inventaire_annuler(id_inventaire integer) RETURNS void
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                inv inventaire%ROWTYPE;
            BEGIN
                SELECT * INTO inv FROM inventaire WHERE id = id_inventaire FOR UPDATE;
                IF NOT FOUND OR NOT droits_peut('inventaires.gerer', inv.entite_id) THEN
                    RAISE EXCEPTION 'Inventaire refusé : vous ne pouvez pas annuler cet inventaire' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF inv.statut IN ('valide', 'annule') THEN
                    RAISE EXCEPTION 'Inventaire refusé : un inventaire validé ou annulé ne s''annule plus' USING ERRCODE = 'check_violation';
                END IF;
                UPDATE inventaire SET statut = 'annule', annule_le = now(), updated_at = now() WHERE id = inv.id;
            END;
            $$
            SQL);

        // Gel : aucun mouvement sur un emplacement en cours d'inventaire, sauf les régularisations
        $this->db->query(<<<'SQL'
            CREATE FUNCTION mouvement_verifier_gel() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                bloquant integer;
            BEGIN
                IF NEW.type = 'regularisation' THEN
                    RETURN NEW;
                END IF;
                SELECT i.id INTO bloquant FROM inventaire i
                WHERE i.statut IN ('en_cours', 'compte')
                  AND ((NEW.emplacement_origine_id IS NOT NULL AND inventaire_couvre(i.id, NEW.emplacement_origine_id))
                    OR (NEW.emplacement_destination_id IS NOT NULL AND inventaire_couvre(i.id, NEW.emplacement_destination_id)))
                LIMIT 1;
                IF bloquant IS NOT NULL THEN
                    RAISE EXCEPTION 'Mouvement refusé : emplacement en cours d''inventaire (inventaire n° %), réessayer après sa validation ou son annulation', bloquant
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        // Nom choisi pour s'exécuter après mouvement_preparer (ordre alphabétique des déclencheurs)
        $this->db->query('CREATE TRIGGER mouvement_verifier_gel BEFORE INSERT ON mouvement FOR EACH ROW EXECUTE FUNCTION mouvement_verifier_gel()');

        // Cloisonnement : visible si l'entité inventoriée l'est ; création réservée à inventaires.gerer
        $this->db->query('ALTER TABLE inventaire ENABLE ROW LEVEL SECURITY');
        $this->db->query('CREATE POLICY lecture ON inventaire FOR SELECT USING (droits_entite_visible(entite_id))');
        $this->db->query("CREATE POLICY creation ON inventaire FOR INSERT WITH CHECK (droits_peut('inventaires.gerer', entite_id))");
        foreach (['ligne_inventaire', 'signature_inventaire'] as $table) {
            $this->db->query("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            $this->db->query("CREATE POLICY lecture ON {$table} FOR SELECT USING (EXISTS (SELECT 1 FROM inventaire i WHERE i.id = inventaire_id))");
        }

        foreach (['inventaire', 'ligne_inventaire', 'signature_inventaire'] as $table) {
            $this->db->query("CREATE TRIGGER audit AFTER INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION audit_journaliser()");
        }

        // L'application planifie ; tout le reste passe par les fonctions ci-dessus
        $this->db->query('REVOKE UPDATE, DELETE, TRUNCATE ON inventaire FROM gestion_app');
        $this->db->query('REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON ligne_inventaire, signature_inventaire FROM gestion_app');

        $this->db->transComplete();
        $this->db->transException(false);
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS mouvement_verifier_gel ON mouvement');
        $this->db->query('DROP TABLE IF EXISTS signature_inventaire, ligne_inventaire, inventaire CASCADE');
        foreach (self::FONCTIONS as $fonction) {
            $this->db->query("DROP FUNCTION IF EXISTS {$fonction}");
        }
        $this->db->query("DELETE FROM role_permission WHERE permission IN ('inventaires.gerer', 'inventaires.valider')");
        $this->db->query("DELETE FROM permission WHERE code IN ('inventaires.gerer', 'inventaires.valider')");
    }
}
