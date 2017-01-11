<?php


namespace Eccube\Composer;


use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;

class PluginInstaller extends LibraryInstaller
{
    public function getInstallPath(PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (!isset($extra['code'])) {
            throw new \RuntimeException('`extra.code` not found in '.$package->getName().'/composer.json');
        }
        return "app/Plugin/".$extra['code'];
    }
}