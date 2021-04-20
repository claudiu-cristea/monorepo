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
use Composer\IO\BufferIO;
use Composer\Json\JsonFile;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;

require_once(__DIR__ . '/../vendor/autoload.php');

// Main composer object.
$composer = (new Factory())->createComposer(
  new BufferIO(),
  __DIR__ . '/../composer.json'
);
// The composer.json handler.
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
$devRequires =& $definition['require-dev'];
$repositories =& $definition['repositories'];
$gitSplitRepos = $definition['extra']['git-split']['repos'] ?? [];

if (!$gitSplitRepos) {
    throw new \LogicException(
      "No repos found in composer.json 'extras.git-split' section."
    );
}

// Get the package name for each entry from extras.git-split. The package name
// is the key, the value is the relative path in monorepo.
$packages = [];
foreach ($gitSplitRepos as $path => $url) {
    $repo = $composer->getRepositoryManager()->createRepository('vcs', [
        'url' => $url,
    ], $path);
    foreach ($repo->getPackages() as $package) {
        $packages[$package->getName()] = $path;
    }
}

// Validate input.
foreach ($args as $arg) {
    if (!isset($packages[$arg])) {
        throw new InvalidArgumentException("Invalid package '{$arg}'.");
    }
}

// Get tha part from "require-dev" that corresponds to passed sub-repos.
$selectedDevRequires = array_map(function (string $arg) use ($devRequires): string {
    return $devRequires[$arg];
}, array_combine($args, $args));

// Isolate all "require-dev" entries that aren't components.
$nonSubRepoDevRequires = array_diff_key($devRequires, $packages);
// Concatenate selected with non-selectable from "require-dev". We keep only
// packages passed at command line together with other dev packages that aren't
// sub-component.
$devRequires = $nonSubRepoDevRequires + $selectedDevRequires;
ksort($devRequires);

// Cleanup "repositories" section.
$selectedDevRepos = array_intersect_key($packages, $selectedDevRequires);
$repositories = array_filter(
  $repositories,
  function (array $repository) use ($selectedDevRepos): bool {
      return $repository['type'] === 'path' && in_array($repository['url'], $selectedDevRepos, true);
  }
);

// Finally, remove the packages themselves.
$fileSystem = new Filesystem();
array_walk($packages, function (string $path, string $repository) use ($fileSystem, $selectedDevRepos): void {
    if (!isset($selectedDevRepos[$repository])) {
        $fileSystem->remove($path);
    }
});

// Update composer.json
$json->write($definition);

// The composer.json file has been changed. Run update.
passthru("./vendor/bin/composer update");

buildAndRunTests();
exit(0);

function buildAndRunTests()
{
    // Build, install & run tests...
}
