# Reprise d'activité et tests de restauration

## Objectifs (à valider par le bureau)

| Indicateur | Objectif | Moyen |
|---|---|---|
| **RPO** (perte de données maximale acceptable) | 24 h | Sauvegarde nocturne (`deploy/backup.sh`) copiée hors serveur (`BACKUP_REMOTE`), chiffrée (`BACKUP_GPG_RECIPIENT`) |
| **RTO** (durée maximale d'interruption) | 1 journée ouvrée | Procédure de restauration de `docs/EXPLOITATION.md` §4 sur le serveur ou un serveur de remplacement |

## Scénarios

| Scénario | Réaction |
|---|---|
| Panne du site | Alerte de la sonde `/health` → vérifier les logs, redémarrer PHP-FPM / la base |
| Mise à jour défectueuse | Retour arrière : `docs/EXPLOITATION.md` §2 |
| Perte du serveur | Reprovisionner, installer (§1), restaurer la dernière sauvegarde (§4), rejouer les migrations, mettre à jour le DNS |
| Compromission | `docs/EXPLOITATION.md` §6 + [violations.md](violations.md) |
| Indisponibilité d'HelloAsso | Les commandes restent possibles en paiement au club (espèces/chèque) ; ne pas relancer de paiement en double |

## Journal des tests de restauration (au moins deux par an)

| Date | Archive restaurée | Environnement de test | Base et fichiers complets ? | Durée | Problèmes et corrections | Testé par |
|---|---|---|---|---|---|---|
| | | | | | | |

Un test réussi = connexion à l'administration, ouverture d'une fiche licencié, d'une commande et d'un fichier téléversé, sur l'environnement restauré.
