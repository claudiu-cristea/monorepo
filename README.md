# Multirepo+Manyrepos demo

Demonstrates a _multirepo_ plus _manyrepos_ configuration. The development (PRs)
goes in the _multirepos_. The synchronization can be triggered manually or
automatically, with Github actions.

* Reositories:
  * Monorepo: https://github.com/claudiu-cristea/monorepo
  * Component repo 1: https://github.com/claudiu-cristea/repo1 splits `packages/repo1`
  * Component repo 2: https://github.com/claudiu-cristea/repo2 splits `packages/repo1`

* Initially, https://github.com/devshop-packages/git-split has been required
  because it offers exacly this functionality, via the Composer command,
  `git:split`. However, using an external dependecy means that we need to run a
  full Composer install in GitHub actions, which is bad, as we need a very short
  running sync process. For this reason we maintain our own script,
  https://github.com/claudiu-cristea/monorepo/blob/master/src/GitSplit/GitSplit.php,
  and now the sync process is superfast.
* Uses Github actions to run `composer git:split` on each push to the multirepo.
* The workflow script is heavily inspired by
  https://github.com/opendevshop/devshop/blob/1.x/.github/workflows/git.yml.
* Github configurations:
  * Create a vendor-space API token with proper permissions.
  * In the _multirepo_ settings, add a repository secret named
    `INPUT_GITHUB_TOKEN` with the token generated above as value.
