<?php

declare(strict_types=1);

namespace JBSupport\ContaoDeeplInstantTranslationBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use JBSupport\ContaoDeeplInstantTranslationBundle\JBSupportContaoDeeplInstantTranslationBundle;

class Plugin implements BundlePluginInterface
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
}
