<?php


namespace Eccube\Composer;


use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Eccube\Common\Constant;
use Eccube\Service\PluginService;

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

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);
        $this->addPluginIdToComposerJson($target);
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (!isset($GLOBALS['kernel'])) {
            $message = 'You can not install the EC-CUBE plugin via `composer` command.'.PHP_EOL
                .'Please use the `bin/console eccube:composer:require '.$package->getName().'` instead.';
            throw new \RuntimeException($message);
        }

        $kernel = $GLOBALS['kernel'];
        $container = $kernel->getContainer();

        parent::install($repo, $package);

        $this->addPluginIdToComposerJson($package);

        /** @var PluginService $pluginService */
        $pluginService = $container->get(PluginService::class);
        $config = $pluginService->readConfig($this->getInstallPath($package));
        $Plugin = $pluginService->registerPlugin($config, $config['source']);
    }

    private function addPluginIdToComposerJson(PackageInterface $package)
    {
        $extra = $package->getExtra();
        $id = @$extra['id'];
        $composerPath = $this->getInstallPath($package).DIRECTORY_SEPARATOR.'composer.json';
        if (file_exists($composerPath)) {
            $composerJson = json_decode(file_get_contents($composerPath), true);
            $composerJson['extra']['id'] = $id;
            file_put_contents($composerPath, json_encode($composerJson));
        }
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if (!isset($GLOBALS['kernel'])) {
            $message = 'You can not uninstall the EC-CUBE plugin via `composer` command.'.PHP_EOL
                .'Please use the `bin/console eccube:composer:remove '.$package->getName().'` instead.';
            throw new \RuntimeException($message);
        }

        $kernel = $GLOBALS['kernel'];
        $container = $kernel->getContainer();

        $extra = $package->getExtra();
        $code = $extra['code'];

        $pluginRepository = $container->get('Eccube\Repository\PluginRepository');
        $pluginService = $container->get('Eccube\Service\PluginService');

        // 他のプラグインから依存されている場合はアンインストールできない
        $enabledPlugins = $pluginRepository->findBy(['enabled' => Constant::ENABLED]);
        foreach ($enabledPlugins as $p) {
            if ($p->getCode() !== $code) {
                $dir = 'app/Plugin/'.$p->getCode();
                $jsonText = @file_get_contents($dir.'/composer.json');
                if ($jsonText) {
                    $json = json_decode($jsonText, true);
                    if (array_key_exists('ec-cube/'.$code, $json['require'])) {
                        throw new \RuntimeException('このプラグインに依存しているプラグインがあるため削除できません。'.$p->getCode());
                    }
                }
            }
        }

        // 無効化していないとアンインストールできない
        $id = @$extra['id'];
        if ($id) {
            $Plugin = $pluginRepository->findOneBy(['source' => $id]);
            if ($Plugin->isEnabled()) {
                throw new \RuntimeException('プラグインを無効化してください。'.$code);
            }
            if ($Plugin) {
                $pluginService->uninstall($Plugin);
            }
        }

        parent::uninstall($repo, $package);
    }
}
