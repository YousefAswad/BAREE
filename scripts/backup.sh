#!/usr/bin/env bash
# Nightly logical backup, encrypted before it leaves the server. Pair with managed PITR (WAL) for RPO ≤ 5 min.
# Requires: pg_dump, age (https://age-encryption.org), aws cli (or any S3-compatible client).
set -euo pipefail
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
FILE="/tmp/baree-${STAMP}.dump"
pg_dump "${DATABASE_OWNER_URL}" --format=custom --no-owner --compress=9 --file="$FILE"
age -r "${BACKUP_AGE_RECIPIENT}" -o "${FILE}.age" "$FILE"
sha256sum "${FILE}.age" > "${FILE}.age.sha256"
aws s3 cp "${FILE}.age" "s3://${BACKUP_BUCKET}/db/${STAMP}.dump.age" --storage-class STANDARD_IA
aws s3 cp "${FILE}.age.sha256" "s3://${BACKUP_BUCKET}/db/${STAMP}.dump.age.sha256"
rm -f "$FILE" "${FILE}.age" "${FILE}.age.sha256"
echo "backup ${STAMP} uploaded"
