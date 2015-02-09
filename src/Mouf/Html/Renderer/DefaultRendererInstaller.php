<?php
/*
 * Copyright (c) 2013 David Negrier
 * 
 * See the file LICENSE.txt for copying permission.
 */

namespace Mouf\Html\Renderer;

use Mouf\Actions\InstallUtils;
use Mouf\Installer\PackageInstallerInterface;
use Mouf\MoufManager;

/**
 * A logger class that writes messages into the php error_log.
 */
class DefaultRendererInstaller implements PackageInstallerInterface {

	/**
	 * (non-PHPdoc)
	 * @see \Mouf\Installer\PackageInstallerInterface::install()
	 */
	public static function install(MoufManager $moufManager) {

		$rendererCacheService = InstallUtils::getOrCreateInstance("rendererCacheService", "Mouf\\Utils\\Cache\\InMemoryCache", $moufManager);
		if ($moufManager->instanceExists("apcCacheService")) {
			$rendererCacheService->getProperty("chainWith")->setValue($moufManager->getInstanceDescriptor("apcCacheService"));
		}

		$customRenderer = InstallUtils::getOrCreateInstance("customRenderer", "Mouf\\Html\\Renderer\\FileBasedRenderer", $moufManager);
		$customRenderer->getProperty("directory")->setValue("src/templates");
		$customRenderer->getProperty("cacheService")->setValue($rendererCacheService);
		$customRenderer->getProperty("type")->setValue(ChainableRendererInterface::TYPE_CUSTOM);
		$customRenderer->getProperty("priority")->setValue(0);

		$defaultRenderer = InstallUtils::getOrCreateInstance("defaultRenderer", "Mouf\\Html\\Renderer\\AutoChainRenderer", $moufManager);
		$defaultRenderer->getProperty("cacheService")->setValue($rendererCacheService);
		$defaultRenderer->getProperty("container")->setValue('return $this;')->setOrigin('php');

		if (!file_exists(ROOT_PATH.'src/templates')) {
			$old = umask(0);
			mkdir(ROOT_PATH.'src/templates', 0775, true);
			// We add a default file in the templates directory in order to make sure the directory is commited in Git
			// (Git does not support commiting empty directories)
			file_put_contents(ROOT_PATH.'src/templates/README.txt', 'Templates directory
===================

This directory contains the templates used to renderer compatible objects in your application.
If you are not familiar with Mouf template mechanism, please have a look at the documentation:
	http://mouf-php.com/packages/mouf/html.renderer/README.md');
			umask($old);
		}
		
		// Let's rewrite the MoufComponents.php file to save the component
		$moufManager->rewriteMouf();
	}
}
