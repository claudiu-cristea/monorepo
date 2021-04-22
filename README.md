# Monorepo+Manyrepos demo

Demonstrates a _monorepo_+_manyrepos_ configuration. The development (PRs) goes in the _monorepo_. The synchronization can be triggered manually or automatically, with Github actions.

## Why?

* **Simplicity**. Sub-projects are organized and grouped together
* **No dependencies, no more "dependency hell"**. Reducing almost to zero all the issues arising from dependencies. The dependency issue is totally vanished. Automated testing all components on a change guarantees that we're not introducing any dependency issue.
* **Tooling**. Only one set of tools to manage all sub-projects. This could lower dramatically that maintenance process.
* **Cross-subproject changes**. With manyrepos, making cross-repo changes is painful. It typically involves tedious manual coordination across each repo or hack-y scripts. And even if the scripts work, there's the overhead of correctly updating cross-repo version dependencies. Refactoring an API that's used across tens of active internal projects will probably a good chunk of a day. With a monorepo, you just refactor the API and all of its callers in one commit.
* **Unified "one version fits all" releasing strategy**. There are two possible approaches:
  1. One version fits all: The new release is created on the monorepo level.
  1. Independent versions: Tagging release  on each submodule.
  The first is better for achieving the homogeneity between components and ensuring no dependency issues

## How?

* Repositories:
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
* Uses Github actions to run `composer git:split` on each push to the _monorepo_.
* The splits are defined in `composer.json`:
  ```json
  "extra": {
      "git-split": {
          "repos": {
              "packages/repo1": "https://github.com/claudiu-cristea/repo1.git",
              "packages/repo2": "https://github.com/claudiu-cristea/repo2.git"
          }
      }
  }
  ```
* The workflow script is heavily inspired by
  https://github.com/opendevshop/devshop/blob/1.x/.github/workflows/git.yml.
* Github configurations:
  * Create a vendor-space API token with proper permissions.
  * In the _monorepo_ settings, add a repository secret named
    `INPUT_GITHUB_TOKEN` with the token generated above as value.
    
## Testing

Testing all the components together it's one of the strengths of the _monorepo_+_manyrepos_ setup because it allows to check that a change to one component doesn't affect the rest of components that are dependencies. However, there are some cases when it's preferable to run isolated tests, limited to one or several components:
* When the test suite is so big and intensive and threatens the allocated resources. In such cases it might be better to only run the tests of the component that was updated. Then, for instance, on nightly basis, the whole suite can run.
* When we test that a specific component has the correct dependencies. In such cases, we want the test to install only the dependencies of the tested component.

For both cases I've written a PoC script ([/scripts/run_tests.php](./scripts/run_tests.php)) that illustrates how we can limit testing to one or more components, including dependencies. 

## Known issues
  
* Synchronizing a branch or tag deletion doesn't work yet.

## Feature requests

* It might be useful to be able to limit the sync to a configurable list of branches and tags.
