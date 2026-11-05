#!/usr/bin/env bash
# Restore drill (monthly, into an EMPTY staging database — never into production by accident).
# Usage: scripts/restore.sh 20261103T020000Z
set -euo pipefail
STAMP="$1"
[[ "${TARGET_DATABASE_URL}" == *prod* ]] && { echo "Refusing: target looks like production."; exit 1; }
aws s3 cp "s3://${BACKUP_BUCKET}/db/${STAMP}.dump.age" /tmp/restore.dump.age
aws s3 cp "s3://${BACKUP_BUCKET}/db/${STAMP}.dump.age.sha256" /tmp/restore.dump.age.sha256
(cd /tmp && sed "s#/tmp/baree-${STAMP}.dump.age#restore.dump.age#" restore.dump.age.sha256 | sha256sum -c -)
age -d -i "${BACKUP_AGE_IDENTITY}" -o /tmp/restore.dump /tmp/restore.dump.age
pg_restore --dbname="${TARGET_DATABASE_URL}" --no-owner --clean --if-exists /tmp/restore.dump
psql "${TARGET_DATABASE_URL}" -c "select count(*) as tenants from tenants; select count(*) as users from users;"
rm -f /tmp/restore.dump /tmp/restore.dump.age /tmp/restore.dump.age.sha256
echo "restore of ${STAMP} verified — record the time it took in docs/RUNBOOK.md"
