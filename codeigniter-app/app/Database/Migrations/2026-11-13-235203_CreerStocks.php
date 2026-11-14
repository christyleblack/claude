<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Module Stocks et mouvements (lot 1, STK-01 à STK-05, RG-10 à RG-14).
 * Voir docs/lot1-modele-de-donnees.md, section 5.
 *
 * - Lots (n° de lot, péremption) et matériels sérialisés (n° de série, état, propriétaire).
 * - Journal des mouvements en insertion seule (RG-12) : une erreur se corrige par un
 *   mouvement d'annulation lié au mouvement d'origine.
 * - Chaque mouvement porte une empreinte SHA-256 chaînée au précédent (H-05) :
 *   toute altération directe en base devient détectable (stocks_verifier_chaine()).
 * - Stock tenu exclusivement par la base, à partir des mouvements (RG-10) ;
 *   jamais négatif (RG-13), verrouillé ligne par ligne pendant la mise à jour.
 * - Un matériel sérialisé a au plus un emplacement à la fois (RG-14).
 * - Détenteur = entité de l'emplacement ; propriétaire = entité portée par le mouvement (STK-04, H-06).
 * - Cloisonnement : un mouvement, une ligne de stock, un matériel ne sont visibles que si
 *   leur emplacement (ou leur propriétaire) est dans le périmètre ; saisir un mouvement exige
 *   stocks.mouvementer sur l'entité responsable du mouvement.
 */
class CreerStocks extends Migration
{
    public function up()
    {
        $this->db->query(<<<'SQL'
            INSERT INTO permission (code, libelle) VALUES
                ('stocks.mouvementer', 'Saisir et annuler les mouvements de stock de son périmètre')
            SQL);

        // Entité qui détient un emplacement (emplacement → site → entité)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION emplacement_entite(id_emplacement integer) RETURNS integer
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT s.entite_id FROM emplacement e JOIN site s ON s.id = e.site_id WHERE e.id = id_emplacement
            $$
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE lot (
                id              serial PRIMARY KEY,
                article_id      integer     NOT NULL REFERENCES article (id),
                numero_lot      varchar(60) NOT NULL,
                date_peremption date,
                created_at      timestamp,
                updated_at      timestamp,
                UNIQUE (article_id, numero_lot)
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE materiel_serialise (
                id                     serial PRIMARY KEY,
                article_id             integer     NOT NULL REFERENCES article (id),
                numero_serie           varchar(60) NOT NULL,
                -- État du matériel (STK-05)
                etat                   varchar(15) NOT NULL DEFAULT 'en_service'
                                       CHECK (etat IN ('en_service', 'en_reparation', 'indisponible', 'reforme')),
                proprietaire_entite_id integer     NOT NULL REFERENCES entite (id),
                created_at             timestamp,
                updated_at             timestamp,
                UNIQUE (article_id, numero_serie)
            )
            SQL);

        // Lot et matériel sérialisé : seulement pour un article suivi de cette façon (RG-06)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION stocks_verifier_mode_article() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                attendu varchar := CASE TG_TABLE_NAME WHEN 'lot' THEN 'lot' ELSE 'serie' END;
            BEGIN
                IF (SELECT mode_suivi FROM article WHERE id = NEW.article_id) <> attendu THEN
                    RAISE EXCEPTION 'Mouvement refusé : cet article n''est pas suivi par %', CASE attendu WHEN 'lot' THEN 'lot' ELSE 'numéro de série' END
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER verifier_mode BEFORE INSERT OR UPDATE OF article_id ON lot FOR EACH ROW EXECUTE FUNCTION stocks_verifier_mode_article()');
        $this->db->query('CREATE TRIGGER verifier_mode BEFORE INSERT OR UPDATE OF article_id ON materiel_serialise FOR EACH ROW EXECUTE FUNCTION stocks_verifier_mode_article()');

        // Journal des mouvements (RG-11, RG-12)
        $this->db->query(<<<'SQL'
            CREATE TABLE mouvement (
                id                         bigserial PRIMARY KEY,
                type                       varchar(15)    NOT NULL CHECK (type IN (
                                               'entree', 'sortie', 'transfert', 'perception', 'reintegration',
                                               'perte', 'reforme', 'regularisation', 'annulation')),
                article_id                 integer        NOT NULL REFERENCES article (id),
                lot_id                     integer        REFERENCES lot (id),
                materiel_id                integer        REFERENCES materiel_serialise (id),
                quantite                   numeric(14, 3) NOT NULL CHECK (quantite > 0),
                emplacement_origine_id     integer        REFERENCES emplacement (id),
                emplacement_destination_id integer        REFERENCES emplacement (id),
                proprietaire_entite_id     integer        NOT NULL REFERENCES entite (id),
                auteur_id                  integer        NOT NULL REFERENCES users (id),
                date_mouvement             timestamptz    NOT NULL DEFAULT now(),
                date_saisie                timestamptz    NOT NULL DEFAULT now(),
                motif                      text,
                -- Annulation : mouvement inverse lié à l'original, une seule fois (RG-12)
                mouvement_annule_id        bigint         UNIQUE REFERENCES mouvement (id),
                empreinte_precedente       text,
                empreinte                  text           NOT NULL,
                CHECK (emplacement_origine_id IS NOT NULL OR emplacement_destination_id IS NOT NULL),
                CHECK (emplacement_origine_id IS DISTINCT FROM emplacement_destination_id),
                CHECK ((type = 'annulation') = (mouvement_annule_id IS NOT NULL)),
                CONSTRAINT origine_et_destination_selon_type CHECK (CASE type
                    WHEN 'entree'         THEN emplacement_origine_id IS NULL AND emplacement_destination_id IS NOT NULL
                    WHEN 'sortie'         THEN emplacement_origine_id IS NOT NULL AND emplacement_destination_id IS NULL
                    WHEN 'perte'          THEN emplacement_origine_id IS NOT NULL AND emplacement_destination_id IS NULL
                    WHEN 'reforme'        THEN emplacement_origine_id IS NOT NULL AND emplacement_destination_id IS NULL
                    WHEN 'transfert'      THEN emplacement_origine_id IS NOT NULL AND emplacement_destination_id IS NOT NULL
                    WHEN 'perception'     THEN emplacement_origine_id IS NOT NULL AND emplacement_destination_id IS NOT NULL
                    WHEN 'reintegration'  THEN emplacement_origine_id IS NOT NULL AND emplacement_destination_id IS NOT NULL
                    WHEN 'regularisation' THEN (emplacement_origine_id IS NULL) <> (emplacement_destination_id IS NULL)
                    ELSE true
                END)
            )
            SQL);
        $this->db->query('CREATE INDEX mouvement_article_idx ON mouvement (article_id, date_mouvement DESC)');
        $this->db->query('CREATE INDEX mouvement_materiel_idx ON mouvement (materiel_id) WHERE materiel_id IS NOT NULL');

        // Stock : tenu par la base à partir des mouvements (RG-10), jamais négatif (RG-13)
        $this->db->query(<<<'SQL'
            CREATE TABLE stock (
                id                     bigserial PRIMARY KEY,
                article_id             integer        NOT NULL REFERENCES article (id),
                lot_id                 integer        REFERENCES lot (id),
                materiel_id            integer        REFERENCES materiel_serialise (id),
                emplacement_id         integer        NOT NULL REFERENCES emplacement (id),
                proprietaire_entite_id integer        NOT NULL REFERENCES entite (id),
                quantite               numeric(14, 3) NOT NULL CHECK (quantite >= 0),
                CONSTRAINT stock_ligne UNIQUE NULLS NOT DISTINCT (article_id, lot_id, materiel_id, emplacement_id, proprietaire_entite_id),
                CHECK (materiel_id IS NULL OR quantite <= 1)
            )
            SQL);
        // RG-14 : un matériel sérialisé est à un seul emplacement à la fois
        $this->db->query('CREATE UNIQUE INDEX stock_materiel_unique ON stock (materiel_id) WHERE materiel_id IS NOT NULL');
        $this->db->query('CREATE INDEX stock_emplacement_idx ON stock (emplacement_id)');

        // Entité responsable d'un mouvement : celle qui doit avoir le droit de le saisir.
        // Entrée et réintégration : le destinataire (qui reçoit) ; régularisation : l'emplacement
        // inventorié ; sinon l'expéditeur. Une annulation relève du responsable de l'original.
        $this->db->query(<<<'SQL'
            CREATE FUNCTION mouvement_entite_responsable(type_mvt varchar, origine integer, destination integer, annule bigint)
            RETURNS integer LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT CASE
                    WHEN type_mvt = 'annulation' THEN (
                        SELECT mouvement_entite_responsable(m.type, m.emplacement_origine_id, m.emplacement_destination_id, NULL)
                        FROM mouvement m WHERE m.id = annule)
                    WHEN type_mvt IN ('entree', 'reintegration') THEN emplacement_entite(destination)
                    WHEN type_mvt = 'regularisation' THEN emplacement_entite(COALESCE(origine, destination))
                    ELSE emplacement_entite(origine)
                END
            $$
            SQL);

        // Avant insertion : annulation, cohérence, auteur, empreinte chaînée
        $this->db->query(<<<'SQL'
            CREATE FUNCTION mouvement_preparer() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                original    mouvement%ROWTYPE;
                art         article%ROWTYPE;
                mat         materiel_serialise%ROWTYPE;
                utilisateur integer := droits_utilisateur();
            BEGIN
                -- L'auteur est l'utilisateur connecté ; à défaut (commande en ligne), celui fourni
                NEW.auteur_id   := COALESCE(utilisateur, NEW.auteur_id);
                NEW.date_saisie := now();
                IF NEW.date_mouvement > now() + interval '5 minutes' THEN
                    RAISE EXCEPTION 'Mouvement refusé : la date du mouvement est dans le futur' USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.type = 'annulation' THEN
                    SELECT * INTO original FROM mouvement WHERE id = NEW.mouvement_annule_id;
                    IF NOT FOUND THEN
                        RAISE EXCEPTION 'Mouvement refusé : mouvement à annuler introuvable' USING ERRCODE = 'check_violation';
                    END IF;
                    IF original.type = 'annulation' THEN
                        RAISE EXCEPTION 'Mouvement refusé : une annulation ne s''annule pas ; saisir un nouveau mouvement' USING ERRCODE = 'check_violation';
                    END IF;
                    -- Mouvement inverse de l'original (RG-12)
                    NEW.article_id                 := original.article_id;
                    NEW.lot_id                     := original.lot_id;
                    NEW.materiel_id                := original.materiel_id;
                    NEW.quantite                   := original.quantite;
                    NEW.proprietaire_entite_id     := original.proprietaire_entite_id;
                    NEW.emplacement_origine_id     := original.emplacement_destination_id;
                    NEW.emplacement_destination_id := original.emplacement_origine_id;
                END IF;

                SELECT * INTO art FROM article WHERE id = NEW.article_id;

                -- Mode de suivi (RG-06, RG-11)
                IF art.mode_suivi = 'quantite' AND (NEW.lot_id IS NOT NULL OR NEW.materiel_id IS NOT NULL) THEN
                    RAISE EXCEPTION 'Mouvement refusé : un article suivi en quantité n''a ni lot ni numéro de série' USING ERRCODE = 'check_violation';
                END IF;
                IF art.mode_suivi = 'lot' AND (NEW.lot_id IS NULL OR NEW.materiel_id IS NOT NULL
                        OR (SELECT article_id FROM lot WHERE id = NEW.lot_id) <> NEW.article_id) THEN
                    RAISE EXCEPTION 'Mouvement refusé : un article suivi par lot exige un lot de cet article' USING ERRCODE = 'check_violation';
                END IF;
                IF art.mode_suivi = 'serie' THEN
                    SELECT * INTO mat FROM materiel_serialise WHERE id = NEW.materiel_id;
                    IF NOT FOUND OR mat.article_id <> NEW.article_id OR NEW.lot_id IS NOT NULL THEN
                        RAISE EXCEPTION 'Mouvement refusé : un article suivi par numéro de série exige un matériel de cet article' USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.quantite <> 1 THEN
                        RAISE EXCEPTION 'Mouvement refusé : un mouvement porte sur un seul numéro de série (quantité 1)' USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.proprietaire_entite_id <> mat.proprietaire_entite_id THEN
                        RAISE EXCEPTION 'Mouvement refusé : le propriétaire indiqué n''est pas celui du matériel' USING ERRCODE = 'check_violation';
                    END IF;
                    IF NEW.type <> 'annulation' AND mat.etat = 'reforme' THEN
                        RAISE EXCEPTION 'Mouvement refusé : ce matériel est réformé' USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                -- Pas d'entrée d'un article désactivé ni vers un emplacement inactif
                IF NEW.type = 'entree' AND NOT art.actif THEN
                    RAISE EXCEPTION 'Mouvement refusé : article désactivé' USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.emplacement_destination_id IS NOT NULL
                   AND NOT (SELECT actif FROM emplacement WHERE id = NEW.emplacement_destination_id) THEN
                    RAISE EXCEPTION 'Mouvement refusé : emplacement de destination inactif' USING ERRCODE = 'check_violation';
                END IF;

                -- Empreinte chaînée (H-05) : un verrou sérialise le calcul entre transactions
                PERFORM pg_advisory_xact_lock(hashtext('mouvement_chaine'));
                SELECT empreinte INTO NEW.empreinte_precedente FROM mouvement ORDER BY id DESC LIMIT 1;
                NEW.empreinte := mouvement_empreinte(NEW);

                RETURN NEW;
            END;
            $$
            SQL);

        // Empreinte : SHA-256 du contenu du mouvement et de l'empreinte précédente
        $this->db->query(<<<'SQL'
            CREATE FUNCTION mouvement_empreinte(m mouvement) RETURNS text
            LANGUAGE sql STABLE AS $$
                -- Chaque champ est toujours présent (« ∅ » si vide) : concat_ws ignorerait les valeurs
                -- nulles, et deux mouvements différents pourraient alors produire le même texte.
                SELECT encode(sha256(convert_to(array_to_string(ARRAY[
                    m.id::text, m.type, m.article_id::text, COALESCE(m.lot_id::text, '∅'), COALESCE(m.materiel_id::text, '∅'),
                    m.quantite::numeric(14, 3)::text,
                    COALESCE(m.emplacement_origine_id::text, '∅'), COALESCE(m.emplacement_destination_id::text, '∅'),
                    m.proprietaire_entite_id::text, m.auteur_id::text,
                    to_char(m.date_mouvement AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS.US'),
                    COALESCE(m.motif, '∅'), COALESCE(m.mouvement_annule_id::text, '∅'), COALESCE(m.empreinte_precedente, 'origine')
                ], '|'), 'UTF8')), 'hex')
            $$
            SQL);
        $this->db->query('CREATE TRIGGER mouvement_preparer BEFORE INSERT ON mouvement FOR EACH ROW EXECUTE FUNCTION mouvement_preparer()');

        // Après insertion : mise à jour du stock (RG-10, RG-13, RG-14) et de l'état du matériel
        $this->db->query(<<<'SQL'
            CREATE FUNCTION mouvement_appliquer() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                ligne    bigint;
                restant  numeric;
                original mouvement%ROWTYPE;
            BEGIN
                IF NEW.emplacement_origine_id IS NOT NULL THEN
                    -- Verrou de la ligne de stock : une seconde sortie simultanée attend la fin de
                    -- cette transaction, puis relit la quantité restante (RG-13 en concurrence)
                    SELECT id, quantite INTO ligne, restant FROM stock
                    WHERE article_id = NEW.article_id
                      AND lot_id IS NOT DISTINCT FROM NEW.lot_id
                      AND materiel_id IS NOT DISTINCT FROM NEW.materiel_id
                      AND emplacement_id = NEW.emplacement_origine_id
                      AND proprietaire_entite_id = NEW.proprietaire_entite_id
                    FOR UPDATE;

                    IF restant IS NULL OR restant < NEW.quantite THEN
                        RAISE EXCEPTION 'Stock insuffisant à l''emplacement d''origine (disponible : %) : un stock ne peut jamais devenir négatif (RG-13)',
                            trim_scale(COALESCE(restant, 0)) USING ERRCODE = 'check_violation';
                    END IF;

                    IF restant = NEW.quantite THEN
                        DELETE FROM stock WHERE id = ligne;
                    ELSE
                        -- La contrainte quantite >= 0 reste le dernier rempart
                        UPDATE stock SET quantite = quantite - NEW.quantite WHERE id = ligne;
                    END IF;
                END IF;

                IF NEW.emplacement_destination_id IS NOT NULL THEN
                    IF NEW.materiel_id IS NOT NULL AND EXISTS (SELECT 1 FROM stock WHERE materiel_id = NEW.materiel_id) THEN
                        RAISE EXCEPTION 'Mouvement refusé : ce matériel est déjà en stock à un autre emplacement (RG-14)'
                            USING ERRCODE = 'check_violation';
                    END IF;
                    INSERT INTO stock (article_id, lot_id, materiel_id, emplacement_id, proprietaire_entite_id, quantite)
                    VALUES (NEW.article_id, NEW.lot_id, NEW.materiel_id, NEW.emplacement_destination_id, NEW.proprietaire_entite_id, NEW.quantite)
                    ON CONFLICT ON CONSTRAINT stock_ligne DO UPDATE SET quantite = stock.quantite + EXCLUDED.quantite;
                END IF;

                -- État du matériel sérialisé (STK-05)
                IF NEW.materiel_id IS NOT NULL THEN
                    IF NEW.type = 'reforme' THEN
                        UPDATE materiel_serialise SET etat = 'reforme' WHERE id = NEW.materiel_id;
                    ELSIF NEW.type = 'perte' THEN
                        UPDATE materiel_serialise SET etat = 'indisponible' WHERE id = NEW.materiel_id;
                    ELSIF NEW.type = 'annulation' THEN
                        SELECT * INTO original FROM mouvement WHERE id = NEW.mouvement_annule_id;
                        IF original.type IN ('reforme', 'perte') THEN
                            UPDATE materiel_serialise SET etat = 'en_service' WHERE id = NEW.materiel_id;
                        END IF;
                    END IF;
                END IF;

                RETURN NULL;
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER mouvement_appliquer AFTER INSERT ON mouvement FOR EACH ROW EXECUTE FUNCTION mouvement_appliquer()');

        // Immuabilité du journal (RG-12, STK-03), y compris pour le propriétaire des tables
        $this->db->query(<<<'SQL'
            CREATE FUNCTION mouvement_immuable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Le journal des mouvements est immuable : une erreur se corrige par un mouvement d''annulation (RG-12)'
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER mouvement_immuable BEFORE UPDATE OR DELETE ON mouvement FOR EACH ROW EXECUTE FUNCTION mouvement_immuable()');
        $this->db->query('CREATE TRIGGER mouvement_immuable_truncate BEFORE TRUNCATE ON mouvement FOR EACH STATEMENT EXECUTE FUNCTION mouvement_immuable()');

        // Vérification de la chaîne : premier mouvement altéré, ou NULL si la chaîne est intacte
        $this->db->query(<<<'SQL'
            CREATE FUNCTION stocks_verifier_chaine() RETURNS bigint
            LANGUAGE plpgsql STABLE SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                m         mouvement%ROWTYPE;
                precedent text := NULL;
            BEGIN
                FOR m IN SELECT * FROM mouvement ORDER BY id LOOP
                    IF m.empreinte_precedente IS DISTINCT FROM precedent OR m.empreinte <> mouvement_empreinte(m) THEN
                        RETURN m.id;
                    END IF;
                    precedent := m.empreinte;
                END LOOP;
                RETURN NULL;
            END;
            $$
            SQL);

        // Cloisonnement
        $this->db->query('ALTER TABLE mouvement ENABLE ROW LEVEL SECURITY');
        $this->db->query(<<<'SQL'
            CREATE POLICY lecture ON mouvement FOR SELECT USING (
                droits_entite_visible(emplacement_entite(emplacement_origine_id))
                OR droits_entite_visible(emplacement_entite(emplacement_destination_id))
            )
            SQL);
        $this->db->query(<<<'SQL'
            CREATE POLICY saisie ON mouvement FOR INSERT WITH CHECK (
                droits_peut('stocks.mouvementer',
                    mouvement_entite_responsable(type, emplacement_origine_id, emplacement_destination_id, mouvement_annule_id))
            )
            SQL);

        $this->db->query('ALTER TABLE stock ENABLE ROW LEVEL SECURITY');
        $this->db->query('CREATE POLICY lecture ON stock FOR SELECT USING (droits_entite_visible(emplacement_entite(emplacement_id)))');

        $this->db->query('ALTER TABLE lot ENABLE ROW LEVEL SECURITY');
        $this->db->query('CREATE POLICY lecture ON lot FOR SELECT USING (true)');
        $this->db->query("CREATE POLICY creation ON lot FOR INSERT WITH CHECK (droits_peut('stocks.mouvementer'))");

        $this->db->query('ALTER TABLE materiel_serialise ENABLE ROW LEVEL SECURITY');
        $this->db->query(<<<'SQL'
            CREATE POLICY lecture ON materiel_serialise FOR SELECT USING (
                droits_entite_visible(proprietaire_entite_id)
                OR EXISTS (SELECT 1 FROM stock s WHERE s.materiel_id = materiel_serialise.id)
            )
            SQL);
        $this->db->query("CREATE POLICY creation ON materiel_serialise FOR INSERT WITH CHECK (droits_peut('stocks.mouvementer', proprietaire_entite_id))");
        $this->db->query("CREATE POLICY modification ON materiel_serialise FOR UPDATE USING (droits_peut('stocks.mouvementer', proprietaire_entite_id)) WITH CHECK (droits_peut('stocks.mouvementer', proprietaire_entite_id))");

        foreach (['lot', 'materiel_serialise'] as $table) {
            $this->db->query("CREATE TRIGGER audit AFTER INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION audit_journaliser()");
        }

        // L'application lit le stock et ajoute des mouvements ; elle ne modifie ni ne supprime rien
        $this->db->query('REVOKE INSERT, UPDATE, DELETE, TRUNCATE ON stock FROM gestion_app');
        $this->db->query('REVOKE UPDATE, DELETE, TRUNCATE ON mouvement FROM gestion_app');
        $this->db->query('REVOKE UPDATE, DELETE ON lot FROM gestion_app');
        $this->db->query('REVOKE DELETE ON materiel_serialise FROM gestion_app');
    }

    public function down()
    {
        // DROP TABLE ne déclenche pas les déclencheurs d'immuabilité (réservé aux migrations)
        $this->db->query('DROP TABLE IF EXISTS stock, mouvement, materiel_serialise, lot CASCADE');
        $this->db->query('DROP FUNCTION IF EXISTS stocks_verifier_chaine(), mouvement_immuable(), mouvement_appliquer(), mouvement_preparer(), mouvement_empreinte(mouvement), mouvement_entite_responsable(varchar, integer, integer, bigint), stocks_verifier_mode_article(), emplacement_entite(integer)');
        $this->db->query("DELETE FROM role_permission WHERE permission = 'stocks.mouvementer'");
        $this->db->query("DELETE FROM permission WHERE code = 'stocks.mouvementer'");
    }
}
