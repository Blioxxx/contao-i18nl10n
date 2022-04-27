<?php

namespace Blioxxx\I18nl10nBundle\Hook;

use Blioxxx\I18nl10nBundle\Classes\I18nl10n;

/**
 * Class GenerateFrontendUrlHook
 * @package Blioxxx\I18nl10nBundle\Hook
 *
 * Create links to translated pages.
 */
class GenerateFrontendUrlHook
{
    /**
     * @param $arrRow
     * @param $strParams
     * @param $strUrl
     * @return mixed|string
     * @throws \Exception
     *
     * @todo:   Don't use TL_LANGUAGE in backend
     * @todo:   Get language by domain
     * @todo:   I18nl10n::getInstance()->getLanguagesByDomain() not valid in BE, since the domain is taken from url
     */
    public function generateFrontendUrl($arrRow, $strParams, $strUrl)
    {
        if (!is_array($arrRow)) {
            throw new \Exception('not an associative array.');
        }

        // @todo:   Call I18nl10n as service, not singleton pattern
        $arrLanguages = I18nl10n::getInstance()->getLanguagesByDomain();
        $arrL10nAlias = null;

        // Append language if existing and forced (by i18nl10n)
        $language = empty($arrRow['language']) || empty($arrRow['forceRowLanguage'])
            ? $GLOBALS['TL_LANGUAGE']
            : $arrRow['language'];

        // Do not look for a translation page if there is an alias given already existing in the language we want
        if ($arrRow['alias']) {
            $objAlias = \Database::getInstance()
                ->prepare('SELECT alias FROM tl_page_i18nl10n WHERE alias = ? AND language = ?')
                ->limit(1)
                ->execute($arrRow['alias'], $language);
        }

        // Try to get l10n alias by language and pid
        if ($language !== $arrLanguages['default']) {
            $database = \Database::getInstance();
            $arrL10nAlias = $database
                ->prepare('SELECT alias FROM tl_page_i18nl10n WHERE pid = ? AND language = ?')
                ->execute($arrRow['id'], $language)
                ->fetchAssoc();
        }

        $alias = is_array($arrL10nAlias)
            ? $arrL10nAlias['alias']
            : $arrRow['alias'];

        // Remove auto_item and language
        $regex = '@/auto_item|/language/[a-z]{2}|[\?&]language=[a-z]{2}@';
        $strParams = preg_replace($regex, '', $strParams);
        $strUrl    = preg_replace($regex, '', $strUrl);

        // If alias is disabled add language to get param end return
        if (\Config::get('disableAlias')) {
            $missingValueRegex = '@(.*\?[^&]*&)([^&]*)=(?=$|&)(&.*)?@';

            if (\Config::get('useAutoItem') && preg_match($missingValueRegex, $strUrl) == 1) {
                $strUrl = preg_replace($missingValueRegex, '${1}auto_item=${2}${3}', $strUrl);
            }

            return $strUrl . '&language=' . $language;
        }

        // Create i18n URL
        $strL10nUrl = $language . '/' . $alias . $strParams . \Config::get('urlSuffix');

        // Catch "/" page aliases and do not add suffix to them (as they are considered as base request)
        if ($strL10nUrl == $language."//".\Config::get('urlSuffix')) {
            $strL10nUrl = $language."/";
        }

        // Finally, if the basic url contains the domain, it means we want an absolute URL, so keep it this way
        if (false !== strpos($strUrl, \Environment::get('base'))) {
            $strL10nUrl = ($arrRow['rootUseSSL'] ? 'https://' : 'http://')
                    . ($arrRow['domain'] ?: \Environment::get('host'))
                    . '/'
                    . $strL10nUrl;
        }

        return $strL10nUrl;
    }
}
