#!/bin/php
<?php

/**
 * @file
 * Allows to run tests only for several selected components.
 *
 * Usage:
 * $ php ./scripts/run_tests.php claudiu-cristea/repo1 [...other sub-repos]
 *
 * If no parameters are passed, all tests are running.
 */

use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Json\JsonFile;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;

require_once(__DIR__ . '/../vendor/autoload.php');

// Main composer object.
$io = new NullIO();
$composer = (new Factory())->createComposer($io, __DIR__ . '/../composer.json');
// The composer.json file handler.
$json = new JsonFile(__DIR__ . '/../composer.json');

$args = $_SERVER['argv'];
// First item is the PHP script file.
array_shift($args);

// If no arguments were passed, run all tests.
if (empty($args)) {
    buildAndRunTests();
    exit(0);
}

// Read composer.json content.
$definition = $json->read();
$gitSplitRepos = $definition['extra']['git-split']['repos'] ?? [];
if (!$gitSplitRepos) {
    throw new \LogicException("No repos in composer.json 'extras.git-split'.");
}
$devRequires =& $definition['require-dev'];
$repositories =& $definition['repositories'];

// Get the package name for each entry from extras.git-split. The package name
// is the key, the value is the relative path in monorepo.
$allComponents = [];
foreach ($gitSplitRepos as $path => $url) {
    $repo = $composer->getRepositoryManager()->createRepository('vcs', [
        'url' => $url,
    ], $path);
    // @todo This process is very slow, as the packages are gathered by
    //   performing an HTTP request for each repository. Consider to improve
    //   this by changing the structure of extras.git-split.repos to:
    //   "extra": {
    //     "git-split": {
    //       "repos": {
    //         "claudiu-cristea/repo1": {
    //           "path": "packages/repo1",
    //           "repo": "https://github.com/claudiu-cristea/repo1.git"
    //         },
    //         "claudiu-cristea/repo2": {
    //           "path": "packages/repo2",
    //           "repo": "https://github.com/claudiu-cristea/repo2.git"
    //         }
    //       }
    //     }
    //   }
    foreach ($repo->getPackages() as $package) {
        $allComponents[$package->getName()] = $path;
    }
}

// Validate input.
array_walk($args, function (string $arg) use ($allComponents): void {
    if (!isset($allComponents[$arg])) {
        throw new InvalidArgumentException("Invalid package '{$arg}'.");
    }
});

// Get the part from "require-dev" that corresponds to passed sub-repos.
$selectedDevRequires = array_intersect_key($devRequires, array_flip($args));
// Get "require-dev" entries that aren't components.
$nonSubRepoDevRequires = array_diff_key($devRequires, $allComponents);
// Concatenate passed packages with non-component packages.
$devRequires = $nonSubRepoDevRequires + $selectedDevRequires;
// Honour the "sort-packages" config.
if ($composer->getConfig()->get('sort-packages')) {
    ksort($devRequires);
}

// Cleanup "repositories" section.
$selectedDevRepos = array_intersect_key($allComponents, $selectedDevRequires);
$repositories = array_filter($repositories, function (array $repository) use ($selectedDevRepos): bool {
    return $repository['type'] === 'path' && in_array($repository['url'], $selectedDevRepos, true);
});

// Finally, remove the packages themselves.
$fileSystem = new Filesystem();
array_walk($allComponents, function (string $path, string $repository) use ($fileSystem, $selectedDevRepos): void {
    // @todo Ensure we are not removing packages that are dependencies of the
    //   packages we're keeping.
    if (!isset($selectedDevRepos[$repository])) {
        $fileSystem->remove($path);
    }
});

// Update composer.json.
$json->write($definition);

// The composer.json file has been changed. Run update.
passthru("./vendor/bin/composer update --no-interaction --no-progress --ansi");

buildAndRunTests();
exit(0);

function buildAndRunTests()
{
    // Build, install & run tests...
}
