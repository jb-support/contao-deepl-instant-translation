<?php

declare(strict_types=1);

namespace JBSupport\ContaoDeeplInstantTranslationBundle\ContaoManager;

use JBSupport\ContaoDeeplInstantTranslationBundle\JBSupportContaoDeeplInstantTranslationBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {

        return [
            BundleConfig::create(JBSupportContaoDeeplInstantTranslationBundle::class)
                ->setLoadAfter(
                    [
                        ContaoCoreBundle::class
                    ]
                ),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
        return $resolver
            ->resolve(__DIR__ . '/../Resources/config/routing.yaml')
            ->load(__DIR__ . '/../Resources/config/routing.yaml');
    }
}
