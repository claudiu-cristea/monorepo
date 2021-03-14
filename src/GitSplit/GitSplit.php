<?php

namespace OpenEuropa\Library\GitSplit;

use Composer\Composer;
use Composer\Script\Event;

/**
 * Splits a Git repo into multiple sub-repos using the splitsh-lite script.
 *
 * This script is heavily inspired by GitSplit Component (devshop/git-split). In
 * fact the code is almost the same. The reason not using devshop/git-split, by
 * requiring it as Composer dependency, is that we want to run the command
 * without needing a prior Composer install, which would slow the GitHub action.
 *
 * @see https://github.com/splitsh/lite
 * @see https://github.com/devshop-packages/git-split
 * @see .github/workflows/subtree-sync.yml
 */
class GitSplit {

  /**
   * The splitsh binary name.
   *
   * @var string
   */
  const SPLITSH_BIN = 'splitsh-lite';

  /**
   * The splitsh-lite download URL.
   *
   * @var string[]
   */
  const SPLITSH_URL = [
    'Linux' => 'https://github.com/splitsh/lite/releases/download/v1.0.1/lite_linux_amd64.tar.gz',
    'Darwin' => 'https://github.com/splitsh/lite/releases/download/v1.0.1/lite_darwin_amd64.tar.gz',
  ];

  /**
   * Run the splitsh-lite script on each repo.
   */
  public static function splitRepos(Event $event): void {
    $composer = $event->getComposer();
    $repos = $composer->getPackage()->getExtra()['git-split']['repos'] ?? NULL;
    if (!$repos) {
      throw new \LogicException("No repos found in composer.json 'extras.git-split' section. Nothing to do.");
    }

    $bin_dir = $composer->getConfig()->get('bin-dir');
    self::installBin($bin_dir);

    // Extracts the currently checked out branch name. In GitHub Actions, this
    // is the branch created in the step "Create a branch for the splitsh-lite".
    $current_ref = trim(shell_exec('git rev-parse --symbolic-full-name --abbrev-ref HEAD'));

    // If the Actions run was triggered by a push, the branch will be named
    // "heads/refs/tags/TAG".
    $is_tag = strpos($current_ref, 'heads/refs/tags') === 0;

    // If is a tag, current_ref contains the string "refs/tags" already.
    $target_ref = $is_tag ? str_replace('heads/', '', $current_ref) : "refs/heads/{$current_ref}";

    foreach ($repos as $folder => $remote) {
      echo "\n\n- Splitting {$folder} for Git reference {$current_ref} to {$remote}...\n";

      // Use a different local target branch so we dont break local installs by
      // reassigning the current branch to the new commit.
      $target = "refs/splits/{$folder}";

      // Split the commits into a different branch.
      $bin_path = realpath("{$bin_dir}/" . self::SPLITSH_BIN);

      if (!file_exists($bin_path)) {
        throw new \Exception("The script splitsh-lite was not found in {$bin_path}.");
      }
      elseif (!is_executable($bin_path)) {
        throw new \Exception("The script splitsh-lite file is not executable: {$bin_path}");
      }

      if (self::exec("{$bin_path} --prefix={$folder}/ --target={$target}") != 0) {
        exit(1);
      }

      // Push the current_ref to the remote.
      if (self::exec("git push --force {$remote} {$target}:{$target_ref}") != 0) {
        exit(1);
      }
    }
  }


  /**
   * Installs splitsh-lite bin.
   *
   * @param string $bin_dir
   *   The project's binary directory.
   */
  protected static function installBin(string $bin_dir): void {
    if (!is_dir($bin_dir)) {
      mkdir($bin_dir, 0777, TRUE);
    }
    $name = self::SPLITSH_BIN;

    $os = php_uname('s');
    if (!isset(self::SPLITSH_URL[$os])) {
      throw new \LogicException("There's no splitsh-lite version for '{$os}' operating system.");
    }
    $url = self::SPLITSH_URL[$os];

    $bin_path = $bin_dir . '/' . self::SPLITSH_BIN;
    if (file_exists($bin_path)) {
      echo "- {$name} already installed at {$bin_path}\n";
      return;
    }

    if (strpos($url, 'tar.gz') !== FALSE) {
      $filename = sys_get_temp_dir() . "/$name";
      $filename_tar = "{$filename}.tar";
      $filename_tar_gz = "{$filename_tar}.gz";

      echo "- Downloading to {$filename_tar_gz}\n";
      copy($url, $filename_tar_gz);

      passthru("tar zxf $filename_tar_gz");
      rename("./" . $name, $bin_path);
    }
    else {
      copy($url, $bin_path);
    }

    chmod($bin_path, 0755);
    echo "- Installed $url to $bin_path \n";
  }

  /**
   * Prints the command then run it.
   *
   * @param $command
   *
   * @return mixed
   */
  protected static function exec($command) {
    echo "> $command \n";
    passthru($command, $exit);
    return $exit;
  }

}
