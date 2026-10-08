#!/usr/bin/env bash
#
# Sauvegarde quotidienne : base de données + fichiers envoyés (uploads, documents).
# Usage : deploy/backup.sh [dossier-de-destination]    (défaut : ../sauvegardes)
#
# Les archives contiennent des données personnelles : le dossier doit être protégé, et une copie
# doit sortir du serveur (rsync/rclone vers un autre site, voir BACKUP_REMOTE). Chiffrement GPG
# facultatif si BACKUP_GPG_RECIPIENT est défini. Les sauvegardes de plus de BACKUP_KEEP_DAYS jours
# (30 par défaut) sont supprimées.
#
# Variables lues dans .env.local / l'environnement : DATABASE_URL (obligatoire).
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="${1:-$PROJECT_DIR/../sauvegardes}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-30}"
STAMP="$(date +%Y%m%d-%H%M%S)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

cd "$PROJECT_DIR"
umask 077
mkdir -p "$DEST"

# DATABASE_URL : depuis l'environnement, sinon .env.local, sinon .env.
if [ -z "${DATABASE_URL:-}" ]; then
    for f in .env.local .env; do
        [ -f "$f" ] && DATABASE_URL="$(grep -E '^DATABASE_URL=' "$f" | tail -n1 | cut -d= -f2- | tr -d '"')" && [ -n "$DATABASE_URL" ] && break
    done
fi
[ -n "${DATABASE_URL:-}" ] || { echo "DATABASE_URL introuvable" >&2; exit 1; }

# mysql://user:pass@host:port/base?options
re='^mysql://([^:]+):([^@]*)@([^:/]+):?([0-9]*)/([^?]+)'
[[ "$DATABASE_URL" =~ $re ]] || { echo "DATABASE_URL non reconnue (attendu : mysql://…)" >&2; exit 1; }
DB_USER="${BASH_REMATCH[1]}"; DB_PASS="${BASH_REMATCH[2]}"; DB_HOST="${BASH_REMATCH[3]}"; DB_PORT="${BASH_REMATCH[4]:-3306}"; DB_NAME="${BASH_REMATCH[5]}"

echo "→ Base de données $DB_NAME"
MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --routines --triggers --no-tablespaces \
    -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" | gzip -9 > "$WORK/base.sql.gz"

echo "→ Fichiers envoyés"
paths=()
for p in public/uploads var/documents var/uploads; do [ -d "$p" ] && paths+=("$p"); done
tar -czf "$WORK/fichiers.tar.gz" "${paths[@]}"

ARCHIVE="$DEST/escoutances-$STAMP.tar"
tar -cf "$ARCHIVE" -C "$WORK" base.sql.gz fichiers.tar.gz

if [ -n "${BACKUP_GPG_RECIPIENT:-}" ]; then
    gpg --batch --yes --trust-model always -r "$BACKUP_GPG_RECIPIENT" -o "$ARCHIVE.gpg" -e "$ARCHIVE" && rm -f "$ARCHIVE"
    ARCHIVE="$ARCHIVE.gpg"
fi

find "$DEST" -maxdepth 1 -name 'escoutances-*.tar*' -mtime +"$KEEP_DAYS" -delete

if [ -n "${BACKUP_REMOTE:-}" ]; then
    echo "→ Copie hors serveur : $BACKUP_REMOTE"
    rsync -a "$ARCHIVE" "$BACKUP_REMOTE"
fi

echo "✓ Sauvegarde : $ARCHIVE ($(du -h "$ARCHIVE" | cut -f1))"
