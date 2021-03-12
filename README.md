# Multirepo+Manyrepos demo

Demonstrates a _multirepo_ plus _manyrepos_ configuration. The development (PRs)
goes in the _multirepos_. The synchronization can be triggered manually or
automatically, with Github actions.

* Requires https://github.com/devshop-packages/git-split that provides a
  Composer command, `git:split`, to sync changes from multirepo to manyrepos.
* Uses Github actions to run `composer git:split` on each push to the multirepo.
* The workflow script is heavily inspired by
  https://github.com/opendevshop/devshop/blob/1.x/.github/workflows/git.yml.
* Github configurations:
  * Create a vendor-space API token with proper permissions.
  * In the _multirepo_ settings, add a repository secret named
    `INPUT_GITHUB_TOKEN` with the token generated above as value.
