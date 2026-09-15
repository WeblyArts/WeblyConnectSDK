#!/usr/bin/env bash
# Poll jsDelivr until all widget assets return HTTP 200 for TAG.
set -euo pipefail

TAG="${1:?tag required}"
REPO="${GITHUB_REPOSITORY:?GITHUB_REPOSITORY required}"

BASE="https://cdn.jsdelivr.net/gh/${REPO}@${TAG}"
PATHS=(
  js/css/widget.css
  js/vendor/fetch-event-source.js
  js/src/stream-kit.js
  js/src/widget.js
)

for path in "${PATHS[@]}"; do
  url="${BASE}/${path}"
  echo "Checking ${url}"
  for attempt in 1 2 3 4 5 6 7 8 9 10; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "${url}")"
    if [[ "${code}" == "200" ]]; then
      echo "OK (${code})"
      break
    fi
    echo "Got ${code}, retrying in 20s (${attempt}/10)..."
    sleep 20
    if [[ "${attempt}" == "10" ]]; then
      echo "jsDelivr never returned 200 for ${url}" >&2
      exit 1
    fi
  done
done
