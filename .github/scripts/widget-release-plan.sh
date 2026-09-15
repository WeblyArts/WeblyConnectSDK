#!/usr/bin/env bash
# Computes the next widget-vX.Y.Z tag (or exits with skip=1).
# Outputs (append to GITHUB_OUTPUT):
#   skip=true|false
#   skip_reason=...
#   new_tag=widget-vX.Y.Z
#   new_version=X.Y.Z
#   prev_tag=widget-v... (may be empty)
set -euo pipefail

BUMP="${1:-patch}"
FORCE="${2:-false}"

git fetch --tags --force >/dev/null 2>&1 || true

HEAD_SHA="$(git rev-parse HEAD)"
LATEST_TAG="$(git tag -l 'widget-v*' --sort=-v:refname | head -n1 || true)"

if [[ -n "${LATEST_TAG}" ]]; then
  LATEST_SHA="$(git rev-list -n1 "${LATEST_TAG}")"
  if [[ "${LATEST_SHA}" == "${HEAD_SHA}" ]]; then
    {
      echo "skip=true"
      echo "skip_reason=HEAD already tagged as ${LATEST_TAG}"
    } >> "${GITHUB_OUTPUT}"
    exit 0
  fi

  if [[ "${FORCE}" != "true" ]] && git diff --quiet "${LATEST_TAG}" HEAD -- js/src js/css js/vendor; then
    {
      echo "skip=true"
      echo "skip_reason=no changes under js/src, js/css, or js/vendor since ${LATEST_TAG}"
    } >> "${GITHUB_OUTPUT}"
    exit 0
  fi

  PREV_VERSION="${LATEST_TAG#widget-v}"
else
  PREV_VERSION="0.0.0"
  LATEST_TAG=""
fi

IFS=. read -r MA MI PA <<< "${PREV_VERSION}"
MA="${MA:-0}"
MI="${MI:-0}"
PA="${PA:-0}"

case "${BUMP}" in
  major)
    MA=$((MA + 1))
    MI=0
    PA=0
    ;;
  minor)
    MI=$((MI + 1))
    PA=0
    ;;
  patch|*)
    PA=$((PA + 1))
    ;;
esac

NEW_VERSION="${MA}.${MI}.${PA}"
NEW_TAG="widget-v${NEW_VERSION}"

{
  echo "skip=false"
  echo "new_tag=${NEW_TAG}"
  echo "new_version=${NEW_VERSION}"
  echo "prev_tag=${LATEST_TAG}"
} >> "${GITHUB_OUTPUT}"

echo "Planned release: ${NEW_TAG} (from ${LATEST_TAG:-none}, bump ${BUMP})"
