#!/bin/bash
# =============================================================
# Script de sauvegarde automatisée MySQL - LocaDjelo
# Compétence BC02-CP6 : Gérer le stockage des données
# Usage : ./backup.sh
# Cron : 0 2 * * * /chemin/vers/backup.sh (tous les jours à 2h)
# =============================================================

# --- Configuration ---
BACKUP_DIR="${BACKUP_DIR:-/backups}"
MYSQL_CONTAINER="locadjelo_mysql"
DB_USER="locadjelo_user"
DB_PASSWORD="locadjelo2026"
DB_NAME="locadjelo"
RETENTION_DAYS=7

# --- Horodatage pour le nom du fichier ---
DATE=$(date +%Y-%m-%d_%H-%M-%S)
BACKUP_FILE="${BACKUP_DIR}/locadjelo_${DATE}.sql"
LOG_FILE="${BACKUP_DIR}/backup.log"

# --- Couleurs pour l'affichage ---
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# --- Fonctions utilitaires ---
log() {
    local message="[$(date +'%Y-%m-%d %H:%M:%S')] $1"
    echo -e "$message"
    echo "$message" >> "$LOG_FILE"
}

log_info()  { log "${GREEN}[INFO]${NC} $1"; }
log_warn()  { log "${YELLOW}[WARN]${NC} $1"; }
log_error() { log "${RED}[ERREUR]${NC} $1"; }

# --- Création du dossier de backup si nécessaire ---
mkdir -p "$BACKUP_DIR"

log_info "=========================================="
log_info "  Sauvegarde MySQL LocaDjelo"
log_info "=========================================="

# --- Vérifier que le container MySQL tourne ---
if ! docker ps --format '{{.Names}}' | grep -q "^${MYSQL_CONTAINER}$"; then
    log_error "Le container ${MYSQL_CONTAINER} n'est pas en cours d'exécution"
    exit 1
fi

# --- Créer la sauvegarde avec mysqldump ---
log_info "Création de la sauvegarde : ${BACKUP_FILE}"

docker exec "$MYSQL_CONTAINER" sh -c \
    "mysqldump --no-tablespaces -u${DB_USER} -p${DB_PASSWORD} ${DB_NAME}" \
    > "$BACKUP_FILE" 2>/dev/null

# --- Vérifier que la sauvegarde a réussi ---
if [ ! -s "$BACKUP_FILE" ]; then
    log_error "La sauvegarde a échoué ou est vide"
    rm -f "$BACKUP_FILE"
    exit 1
fi

BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
log_info "Sauvegarde créée : ${BACKUP_SIZE}"

# --- Compression de la sauvegarde ---
log_info "Compression en cours..."
gzip "$BACKUP_FILE"
COMPRESSED_SIZE=$(du -h "${BACKUP_FILE}.gz" | cut -f1)
log_info "Sauvegarde compressée : ${COMPRESSED_SIZE}"

# --- Nettoyage des vieilles sauvegardes ---
log_info "Suppression des sauvegardes de plus de ${RETENTION_DAYS} jours..."
DELETED_COUNT=$(find "$BACKUP_DIR" -name "locadjelo_*.sql.gz" -mtime +${RETENTION_DAYS} -delete -print | wc -l)
log_info "${DELETED_COUNT} ancienne(s) sauvegarde(s) supprimée(s)"

# --- Rapport final ---
TOTAL_BACKUPS=$(find "$BACKUP_DIR" -name "locadjelo_*.sql.gz" | wc -l)
log_info "Sauvegardes actuelles disponibles : ${TOTAL_BACKUPS}"
log_info "Sauvegarde terminée avec succès"
log_info "=========================================="

exit 0