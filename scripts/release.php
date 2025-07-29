<?php
/**
 * リリース管理スクリプト
 * composer.jsonとconfig.phpのバージョンを同期します
 */

class ReleaseManager
{
    private $composerFile = 'composer.json';

    public function updateVersion($newVersion)
    {
        if (!$this->isValidVersion($newVersion)) {
            throw new InvalidArgumentException("Invalid version format: $newVersion");
        }

        $this->updateComposerVersion($newVersion);

        echo "Version updated to: $newVersion\n";
        echo "config.php will automatically read the version from composer.json\n";
        echo "Next steps:\n";
        echo "1. git add .\n";
        echo "2. git commit -m \"Bump version to $newVersion\"\n";
        echo "3. git tag v$newVersion\n";
        echo "4. git push origin main --tags\n";
    }

    private function isValidVersion($version)
    {
        return preg_match('/^\d+\.\d+\.\d+$/', $version);
    }

    private function updateComposerVersion($version)
    {
        $composer = json_decode(file_get_contents($this->composerFile), true);
        $composer['version'] = $version;
        file_put_contents($this->composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function getCurrentVersion()
    {
        $composer = json_decode(file_get_contents($this->composerFile), true);
        return $composer['version'] ?? 'unknown';
    }
}

// コマンドライン実行
if (php_sapi_name() === 'cli') {
    $manager = new ReleaseManager();

    if (isset($argv[1])) {
        try {
            $manager->updateVersion($argv[1]);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    } else {
        echo "Current version: " . $manager->getCurrentVersion() . "\n";
        echo "Usage: php scripts/release.php <version>\n";
        echo "Example: php scripts/release.php 1.3.0\n";
    }
}
