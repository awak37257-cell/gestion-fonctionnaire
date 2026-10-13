<?php
session_start();
require_once __DIR__ . '/../config.php';

// Page de retour (adapter si ton fichier d'affichage porte un autre nom)
const PAGE_RETOUR = 'notifications.php';

function retour(string $query): void
{
    header('Location: ' . PAGE_RETOUR . '?' . $query);
    exit();
}
function retour_erreur(string $message): void
{
    retour('error=' . urlencode($message));
}

// Supprime un fichier uniquement s'il se trouve bien dans le dossier de base indiqué
function supprimer_fichier_dans(string $dossierBase, string $relatif): void
{
    $base = realpath($dossierBase);
    $relatif = trim($relatif);
    if (!$base || $relatif === '') {
        return;
    }
    $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relatif, '/\\'));
    $chemin = realpath($base . DIRECTORY_SEPARATOR . $rel);
    if ($chemin && str_starts_with($chemin, $base . DIRECTORY_SEPARATOR) && is_file($chemin)) {
        @unlink($chemin);
    }
}

// 1. Droits : méthode POST, rôle administrateur, session valide
$idUser = (int)($_SESSION['id_user'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$idUser || !in_array($_SESSION['rol'] ?? '', ['admin', 'admin_local'], true)) {
    http_response_code(403);
    exit('Accès refusé.');
}

// 2. Jeton CSRF
if (!hash_equals((string)($_SESSION['csrf_notifications'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
    retour_erreur('La session a expiré. Rechargez la page et réessayez.');
}

// 3. Notification : elle doit appartenir à l'utilisateur connecté
$idNotification = (int)($_POST['id_notification'] ?? 0);
$action = (string)($_POST['action'] ?? '');

$stmt = $db->prepare('SELECT * FROM NOTIFICATIONS WHERE id_notification = ? AND id_user = ?');
$stmt->execute([$idNotification, $idUser]);
$notification = $stmt->fetch();

if (!$notification || !in_array($action, ['verifier', 'supprimer'], true)) {
    retour_erreur('Déclaration introuvable.');
}
if ($notification['statut'] !== 'a_verifier') {
    retour_erreur('Cette déclaration a déjà été traitée.');
}

$libelles = [
    'diplome'    => 'un diplôme',
    'competence' => 'une compétence',
    'mutation'   => 'une étape du parcours',
];
$libelleElement = $libelles[$notification['type_element']] ?? 'un élément';
$idAgent = (int)$notification['id_fonc'];
$idElement = (int)$notification['id_element'];

$fichiersASupprimer = [];   // [dossier de base, chemin relatif] : supprimés seulement après le commit

try {
    $db->beginTransaction();

    // 4. On « prend » la notification en premier, de façon atomique :
    //    un double clic ou deux onglets ne peuvent pas la traiter deux fois.
    $nouveauStatut = ($action === 'verifier') ? 'verifie' : 'supprime';
    $stmt = $db->prepare("UPDATE NOTIFICATIONS SET statut = ?, date_traitement = NOW()
                          WHERE id_notification = ? AND id_user = ? AND statut = 'a_verifier'");
    $stmt->execute([$nouveauStatut, $idNotification, $idUser]);
    if ($stmt->rowCount() !== 1) {
        $db->rollBack();
        retour_erreur('Cette déclaration a déjà été traitée.');
    }

    // 5. Suppression de l'élément, si demandée
    if ($action === 'supprimer') {
        if ($notification['type_element'] === 'diplome') {
            $stmt = $db->prepare('SELECT fich_url FROM DIPLOME WHERE id_dipl = ?');
            $stmt->execute([$idElement]);
            $fichierDiplome = $stmt->fetchColumn();

            // Documents archivés rattachés à ce diplôme pour cet agent
            $stmt = $db->prepare('SELECT fich_url FROM DOCUMENT WHERE id_dipl = ? AND id_fonc = ?');
            $stmt->execute([$idElement, $idAgent]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $urlDoc) {
                if ($urlDoc) {
                    $fichiersASupprimer[] = [__DIR__ . '/../actions/uploads/pieces_jointes', basename((string)$urlDoc)];
                }
            }

            $db->prepare('DELETE FROM DOCUMENT WHERE id_dipl = ? AND id_fonc = ?')->execute([$idElement, $idAgent]);
            $db->prepare('DELETE FROM OBTENIR WHERE id_dipl = ? AND id_fonc = ?')->execute([$idElement, $idAgent]);

            // La fiche du diplôme n'est supprimée que si plus aucun agent n'y est rattaché
            $stmt = $db->prepare('SELECT COUNT(*) FROM OBTENIR WHERE id_dipl = ?');
            $stmt->execute([$idElement]);
            if ((int)$stmt->fetchColumn() === 0) {
                $db->prepare('DELETE FROM DIPLOME WHERE id_dipl = ?')->execute([$idElement]);
                if ($fichierDiplome) {
                    $fichiersASupprimer[] = [__DIR__ . '/../uploads/diplomes', basename((string)$fichierDiplome)];
                }
            }

        } elseif ($notification['type_element'] === 'competence') {
            $stmt = $db->prepare('SELECT justificatif_path FROM detenir WHERE id_comp = ? AND id_fonc = ?');
            $stmt->execute([$idElement, $idAgent]);
            $fichierCompetence = $stmt->fetchColumn();

            // On retire seulement le lien de cet agent : le référentiel de compétences reste intact
            $db->prepare('DELETE FROM detenir WHERE id_comp = ? AND id_fonc = ?')->execute([$idElement, $idAgent]);
            if ($fichierCompetence) {
                $fichiersASupprimer[] = [__DIR__ . '/../uploads', (string)$fichierCompetence];
            }

        } elseif ($notification['type_element'] === 'mutation') {
            $stmt = $db->prepare('SELECT id_fonc, date_fin FROM RECEVOIR WHERE id_rec = ?');
            $stmt->execute([$idElement]);
            $parcours = $stmt->fetch();

            if ($parcours && (int)$parcours['id_fonc'] === $idAgent) {
                $db->prepare('DELETE FROM RECEVOIR WHERE id_rec = ?')->execute([$idElement]);

                // Si l'étape supprimée était l'affectation en cours, la précédente redevient l'affectation en cours
                if ($parcours['date_fin'] === null) {
                    $stmt = $db->prepare('UPDATE RECEVOIR SET date_fin = NULL WHERE id_fonc = ? ORDER BY date_debut DESC, id_rec DESC LIMIT 1');
                    $stmt->execute([$idAgent]);
                }
            }
        }
    }

    // 6. Journal : chaque décision laisse une trace (qui, quoi, quel agent)
    $stmt = $db->prepare('SELECT matri FROM FONCTIONNAIRE WHERE id_fonc = ?');
    $stmt->execute([$idAgent]);
    $matri = (string)$stmt->fetchColumn();

    $descAction = ($action === 'verifier')
        ? "Admin a vérifié la déclaration de $libelleElement (Matricule: $matri)"
        : "Admin a supprimé $libelleElement du dossier (Matricule: $matri)";
    $db->prepare('INSERT INTO HISTORIQUE (id_user, action_desc, dat_hist) VALUES (?, ?, NOW())')
       ->execute([$idUser, $descAction]);

    $db->commit();

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('traitement_notification.php : ' . $e->getMessage());
    retour_erreur('Impossible de traiter cette déclaration. Réessayez dans un instant.');
}

// 7. Fichiers : supprimés uniquement une fois la base à jour (un échec du commit ne fait donc perdre aucun fichier)
foreach ($fichiersASupprimer as [$dossier, $relatif]) {
    supprimer_fichier_dans($dossier, $relatif);
}

retour('success=1');