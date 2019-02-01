<?php

namespace AlterPHP\EasyAdminExtensionBundle\DependencyInjection\Compiler;

use AlterPHP\EasyAdminExtensionBundle\EasyAdminExtensionBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TwigPathPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $twigLoaderFilesystemId = $container->getAlias('twig.loader')->__toString();
        $twigLoaderFilesystemDefinition = $container->getDefinition($twigLoaderFilesystemId);

        // Replaces native EasyAdmin templates
        $easyAdminExtensionBundleRefl = new \ReflectionClass(EasyAdminExtensionBundle::class);
        if ($easyAdminExtensionBundleRefl->isUserDefined()) {

            $bundleName = explode("\\", $easyAdminExtensionBundleRefl->getName());

//            $easyAdminExtensionBundlePath = \dirname((string) $easyAdminExtensionBundleRefl->getFileName());
            $easyAdminExtensionBundlePath = dirname(__FILE__, 7) . "/templates/bundles/" . $bundleName[2];

//            $easyAdminExtensionTwigPath = $easyAdminExtensionBundlePath.'/Resources/views';
            $easyAdminExtensionTwigPath = $easyAdminExtensionBundlePath;

            $twigLoaderFilesystemDefinition->addMethodCall(
                'prependPath',
                [$easyAdminExtensionTwigPath, 'EasyAdmin']
            );
        }

        $nativeEasyAdminBundleRefl = new \ReflectionClass(EasyAdminBundle::class);
        if ($nativeEasyAdminBundleRefl->isUserDefined()) {

            $bundleName = explode("\\", $easyAdminExtensionBundleRefl->getName());

//            $nativeEasyAdminBundlePath = \dirname((string) $nativeEasyAdminBundleRefl->getFileName());
//            $nativeEasyAdminBundlePath = dirname(__FILE__, 7) . "/templates/bundles/" . $bundleName[2];
            $nativeEasyAdminBundlePath = dirname(__FILE__, 7) . "/templates/bundles/" . 'EasyAdminBundle';

//            $nativeEasyAdminTwigPath = $nativeEasyAdminBundlePath.'/Resources/views';
            $nativeEasyAdminTwigPath = $nativeEasyAdminBundlePath;

            // Defines a namespace from native EasyAdmin templates
            $twigLoaderFilesystemDefinition->addMethodCall(
                'addPath',
                [$nativeEasyAdminTwigPath, 'BaseEasyAdmin']
            );
        }
    }
}
