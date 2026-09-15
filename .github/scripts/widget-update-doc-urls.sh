#!/usr/bin/env bash
# Replace pinned widget-v* CDN URLs in repo docs with NEW_TAG.
set -euo pipefail

NEW_TAG="${1:?new tag required}"
PREV_TAG="${2:-}"

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "${ROOT}"

if [[ -n "${PREV_TAG}" ]]; then
  find docs -type f -name '*.md' -print0 | while IFS= read -r -d '' file; do
    if grep -q "${PREV_TAG}" "${file}"; then
      sed -i "s/${PREV_TAG}/${NEW_TAG}/g" "${file}"
      echo "Updated ${file}"
    fi
  done
else
  echo "No prev_tag; skipping doc URL replacement"
fi

echo "${NEW_TAG#widget-v}" > js/WIDGET_VERSION
echo "Wrote js/WIDGET_VERSION"
