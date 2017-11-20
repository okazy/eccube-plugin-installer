<?php


namespace Eccube\Composer;


use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Eccube\Common\Constant;

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

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $extra = $package->getExtra();

        $app = $this->getApplication();
        $code = $extra['code'];
        $configYml = Yaml::parse(file_get_contents($app['config']['plugin_realdir'].'/'.$code.'/config.yml'));
        $eventYml = Yaml::parse(file_get_contents($app['config']['plugin_realdir'].'/'.$code.'/event.yml'));

        $app['eccube.service.plugin']->preInstall();
        $app['eccube.service.plugin']->postInstall($configYml, $eventYml, @$extra['id']);
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $app = $this->getApplication();

        $extra = $package->getExtra();
        $code = $extra['code'];

        // 他のプラグインから依存されている場合はアンインストールできない
        $enabledPlugins = $app['eccube.repository.plugin']->findBy(array('enable' => Constant::ENABLED));
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
            $Plugin = $app['eccube.repository.plugin']->findOneBy(array('source' => $id));
            if ($Plugin->isEnable()) {
                throw new RuntimeException('プラグインを無効化してください。'.$code);
            }
            if ($Plugin) {
                $app['eccube.service.plugin']->uninstall($Plugin);
            }
        }

        parent::uninstall($repo, $package);
    }

    private function getApplication()
    {
        $loader = require_once __DIR__.'/../../../../../../autoload.php';

        $app = \Eccube\Application::getInstance(['eccube.autoloader' => $loader]);
        if (!$app->isBooted()) {
            $app->initialize();
            $app->boot();
        }

        return $app;
    }

}
