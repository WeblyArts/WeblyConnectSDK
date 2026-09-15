#!/usr/bin/env bash
# Opens a PR on WeblyArts/WeblySuite (branch dev) updating docs/weblyconnect.md CDN pins.
# Requires WEBLY_SUITE_PAT (repo scope on WeblySuite).
set -euo pipefail

NEW_TAG="${1:?new tag required}"
PREV_TAG="${2:-}"

if [[ -z "${WEBLY_SUITE_PAT:-}" ]]; then
  echo "WEBLY_SUITE_PAT not set; skipping WeblySuite docs sync"
  exit 0
fi

if [[ -z "${PREV_TAG}" ]]; then
  echo "No prev_tag; skipping WeblySuite docs sync"
  exit 0
fi

WORKDIR="$(mktemp -d)"
trap 'rm -rf "${WORKDIR}"' EXIT

git clone --depth 1 --branch dev \
  "https://x-access-token:${WEBLY_SUITE_PAT}@github.com/WeblyArts/WeblySuite.git" \
  "${WORKDIR}/WeblySuite"

cd "${WORKDIR}/WeblySuite"

if ! grep -q "${PREV_TAG}" docs/weblyconnect.md; then
  echo "docs/weblyconnect.md does not reference ${PREV_TAG}; nothing to update"
  exit 0
fi

BRANCH="docs/${NEW_TAG}"
git checkout -b "${BRANCH}"
sed -i "s/${PREV_TAG}/${NEW_TAG}/g" docs/weblyconnect.md

git config user.name "github-actions[bot]"
git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
git add docs/weblyconnect.md
git commit -m "docs: pin WeblyConnect widget CDN to ${NEW_TAG}"

git push origin "${BRANCH}"

export GH_TOKEN="${WEBLY_SUITE_PAT}"
gh pr create \
  --repo WeblyArts/WeblySuite \
  --base dev \
  --head "${BRANCH}" \
  --title "docs: WeblyConnect widget CDN ${NEW_TAG}" \
  --body "Automated PR from [WeblyConnectSDK](https://github.com/WeblyArts/WeblyConnectSDK) widget release \`${NEW_TAG}\`.

Updates jsDelivr embed URLs in \`docs/weblyconnect.md\`. Merge to publish on suite.weblyarts.com after deploy."
