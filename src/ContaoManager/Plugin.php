<?php

namespace Blioxxx\I18nl10nBundle\ContaoManager;

use Blioxxx\I18nl10nBundle\BlioxxxI18nl10nBundle;
use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;

/**
 * Plugin for the Contao Manager.
 *
 * @author Claudio De Facci <https://exploreimpact.de>
 */
class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(BlioxxxI18nl10nBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
                ->setReplace(['i18nl10n']),
        ];
    }
}
