#!/bin/bash
# Runs custom/asics_v1/add_query/*.sql of a staged release, in file-name order.
# Called by the SSM document Asics-RunAddQuery on the migration EC2 instance.
# Terraform embeds this file into the SSM document, so editing it here changes
# nothing until the next `terraform apply`.
set -euo pipefail
export LC_ALL=C

RELEASE_ID="${1:-}"
if [[ ! "$RELEASE_ID" =~ ^[0-9a-f]{40}$ ]]; then
  echo "ERROR invalid release id: '$RELEASE_ID'"; exit 1
fi

RELEASE_DIR="/mnt/efs/releases/$RELEASE_ID"
QUERY_DIR="$RELEASE_DIR/custom/asics_v1/add_query"
MYSQL=(mysql --defaults-extra-file=/etc/deploy/my.cnf)

if [ ! -d "$RELEASE_DIR" ]; then
  echo "ERROR release not staged: $RELEASE_DIR"; exit 1
fi

"${MYSQL[@]}" -e "CREATE TABLE IF NOT EXISTS fm_deploy_add_query_history (
  file_name   VARCHAR(255) PRIMARY KEY,
  executed_at DATETIME NOT NULL
)"

shopt -s nullglob
files=("$QUERY_DIR"/*.sql)
if [ ${#files[@]} -eq 0 ]; then
  echo "no add_query files"; exit 0
fi

for f in "${files[@]}"; do
  name=$(basename "$f")
  if [[ ! "$name" =~ ^[A-Za-z0-9_.-]+\.sql$ ]]; then
    echo "ERROR unsupported file name: $name"; exit 1
  fi
  done_cnt=$("${MYSQL[@]}" -N -e "SELECT COUNT(*) FROM fm_deploy_add_query_history WHERE file_name='$name'")
  if [ "$done_cnt" -gt 0 ]; then
    echo "SKIP $name"; continue
  fi
  echo "RUN  $name"
  "${MYSQL[@]}" < "$f"
  "${MYSQL[@]}" -e "INSERT INTO fm_deploy_add_query_history VALUES('$name', NOW())"
  echo "DONE $name"
done
