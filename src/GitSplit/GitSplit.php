<?php

namespace OpenEuropa\Library\GitSplit;

use Composer\Composer;
use Composer\Script\Event;

/**
 * Splits a Git repo into multiple sub-repos using the splitsh-lite script.
 *
 * This script is heavily inspired by GitSplit Component (devshop/git-split), in
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
   * The Composer bin dir.
   *
   * @var string
   */
  protected static $binDir;

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
   *
   * @param \Composer\Script\Event $event
   *   The Composer event.
   */
  public static function splitRepos(Event $event): void {
    $composer = $event->getComposer();
    self::$binDir = $composer->getConfig()->get('bin-dir');

    $repos = $composer->getPackage()->getExtra()['git-split']['repos'] ?? NULL;
    if (!$repos) {
      throw new \LogicException("No repos found in composer.json 'extras.git-split' section. Nothing to do.");
    }

    $bin_path = self::installBin();

    // Extracts the currently checked out branch name. In GitHub Actions, this
    // is the branch created in the step "Create a branch for the splitsh-lite".
    $current_ref = trim(shell_exec('git rev-parse --symbolic-full-name --abbrev-ref HEAD'));

    // If the Actions run was triggered by a push, the branch will be named
    // "heads/refs/tags/TAG".
    $is_tag = strpos($current_ref, 'heads/refs/tags') === 0;

    // If is a tag, current_ref contains the string "refs/tags" already.
    $target_ref = $is_tag ? str_replace('heads/', '', $current_ref) : "refs/heads/{$current_ref}";

    foreach ($repos as $folder => $remote) {
      echo "\n- Splitting {$folder} for Git reference {$current_ref} to {$remote}\n";

      // Use a different local target branch so we dont break local installs by
      // reassigning the current branch to the new commit.
      $target = "refs/splits/{$folder}";

      // Git split subtree.
      $exit_code = self::exec("{$bin_path} --prefix={$folder}/ --target={$target}");
      if ($exit_code) {
        exit($exit_code);
      }

      // Push the $target_ref to the remote.
      $exit_code = self::exec("git push --force {$remote} {$target}:{$target_ref}");
      if ($exit_code) {
        exit($exit_code);
      }
    }
  }

  /**
   * Installs splitsh-lite bin.
   *
   * @return string
   *   The binary path.
   */
  protected static function installBin(): string {
    if (!is_dir(self::$binDir)) {
      mkdir(self::$binDir, 0777, TRUE);
    }
    $name = self::SPLITSH_BIN;

    $os = php_uname('s');
    if (!isset(self::SPLITSH_URL[$os])) {
      throw new \LogicException("There's no splitsh-lite version for {$os} operating system.");
    }

    $bin_path = self::$binDir . '/' . self::SPLITSH_BIN;
    if (file_exists($bin_path)) {
      if (is_executable($bin_path)) {
        echo "- {$name} already installed at " . self::getRelativePath($bin_path) . "\n";
        return $bin_path;
      }
      else {
        // The file exists but is not executable. Re-download.
        unlink($bin_path);
      }
    }

    $url = self::SPLITSH_URL[$os];
    $filename = sys_get_temp_dir() . "/{$name}";
    $filename_tar_gz = "{$filename}.tar.gz";

    echo "- Downloading to {$filename_tar_gz}\n";
    copy($url, $filename_tar_gz);
    echo "- Unpacking {$filename_tar_gz}\n";
    self::exec("tar xzf {$filename_tar_gz}");
    echo "- Moving ./{$name} to {$bin_path}\n";
    self::exec("mv ./{$name} {$bin_path}");
    echo "- Setting permissions for {$bin_path}\n";
    self::exec("chmod 0755 {$bin_path}");
    echo "- Installed {$url} to {$bin_path}\n";

    return $bin_path;
  }

  /**
   * Prints the command then run it.
   *
   * @param $command
   *
   * @return mixed
   */
  protected static function exec($command) {
    $displayed_command = str_replace(getcwd(), '.', $command);
    echo "> {$displayed_command} \n";
    passthru($command, $exit);
    return $exit;
  }

  /**
   * Converts an absolute path to a path relative to project's root.
   *
   * @param string $absolute_path
   *   The absolute path.
   *
   * @return string
   *   A path relative to project's root.
   */
  protected static function getRelativePath(string $absolute_path): string {
    return substr($absolute_path, strlen(getcwd()) + 1);
  }

}
