#!/usr/bin/env bash

set -e
set -x

CURRENT_BRANCH="$(git branch --show-current)"

echo ${CURRENT_BRANCH}

case "$(uname -s)" in
  Linux*) SPLIT_SH="splitsh-lite-linux" ;;
  Darwin*) SPLIT_SH="splitsh-lite-mac" ;;
  *) exit 1 ;;
esac

function split() {
  SHA1=$(./bin/${SPLIT_SH} --prefix=$1)
  git push $2 "${SHA1}:refs/heads/${CURRENT_BRANCH}" -f --tags
}

function remote() {
  git remote add $1 $2 2>/dev/null || true
}

git pull origin ${CURRENT_BRANCH}

remote repo1 git@github.com:claudiu-cristea/repo1.git

split 'packages/repo1' repo1
