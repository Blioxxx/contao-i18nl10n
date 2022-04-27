<?php

namespace Blioxxx\I18nl10nBundle\Hook;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Blioxxx\I18nl10nBundle\Classes\I18nl10n;

/**
 * Class GetPageIdFromUrlHook
 * @package Blioxxx\I18nl10nBundle\Hook
 *
 * Translate the i18nl10n URL fragments to their corresponding parent counterpart with language parameter.
 * https://docs.contao.org/books/api/extensions/hooks/getPageIdFromUrl.html
 */
class GetPageIdFromUrlHook
{
    /**
     * Get page id from url, based on current Contao settings
     *
     * Note: In some cases this will never be called...
     *
     * @param array $arrFragments
     *
     * @return array
     */
    public function getPageIdFromUrl(array $arrFragments)
    {
        // Check if url fragments are available (see #66)
        if (empty($arrFragments[0])) {
            return $arrFragments;
        }

        $arrFragments = \array_map('urldecode', $arrFragments);
        $arrLanguages = I18nl10n::getInstance()->getLanguagesByDomain();

        // If no root pages found, return
        if (!\count($arrLanguages)) {
            return $arrFragments;
        }


        /*
         * Extract the language snippet from URL
         */
        // Get default language
        $arrMappedFragments = $this->mapUrlFragments($arrFragments);

        // First entry must be language
        $strLanguage = $arrFragments[0];


        /*
         * Arrange correct i18nl10n alias
         */
        // try to find localized page by alias
        $arrAlias = $this->findAliasByLocalizedAliases($arrMappedFragments, $strLanguage);

        // Remove first entry (will be replaced by alias further on)
        \array_shift($arrMappedFragments);

        // if alias has folder, remove related entries
        if (strpos($arrAlias['alias'], '/') !== false || strpos($arrAlias['l10nAlias'], '/') !== false) {
            $arrAliasFragments = \array_merge(explode('/', $arrAlias['alias']), \explode('/', $arrAlias['l10nAlias']));

            // remove alias parts
            foreach ($arrAliasFragments as $strAliasFragment) {
                // if alias part is still part of arrFragments, remove it from there
                if (($key = \array_search($strAliasFragment, $arrMappedFragments)) !== false) {
                    unset($arrMappedFragments[$key]);
                    $arrMappedFragments =  \array_values($arrMappedFragments);
                }
            }
        }

        // Insert alias
        \array_unshift($arrMappedFragments, $arrAlias['alias']);


        /*
         * Add language to URL fragments
         */
        // Add language
        \array_push($arrMappedFragments, 'language', $strLanguage);

        // Add the second fragment as auto_item if the number of fragments is even
        if (\Config::get('useAutoItem') && count($arrMappedFragments) % 2 == 0) {
            array_insert($arrMappedFragments, 1, array('auto_item'));
        }

        // Restore the urlSuffix config if it has been removed in initializeSystem hook
        if (\Config::has('tmpUrlSuffix')) {
            \Config::set('urlSuffix', \Config::get('tmpUrlSuffix'));
            \Config::set('tmpUrlSuffix', '');
        }

        // Consider that - as Contao native does - if we don't have a fragment, make it / to find homepage.
        if ($arrMappedFragments[0] === "") {
            $arrMappedFragments[0] = "/";
        }

        return $arrMappedFragments;
    }

    /**
     * Clean url fragments from language and auto_item
     *
     * @param $arrFragments
     *
     * @return array
     */
    private function mapUrlFragments($arrFragments)
    {
        // Delete auto_item
        if (\Config::get('useAutoItem') && $arrFragments[1] === 'auto_item') {
            unset($arrFragments[1]);
            $arrFragments =  \array_values($arrFragments);
        }

        // Delete language if first part of url
        unset($arrFragments[0]);
        $arrFragments =  \array_values($arrFragments);

        return $arrFragments;
    }

    /**
     * Find alias for internationalized content or use fallback language alias
     *
     * @param $arrFragments
     * @param $strLanguage
     *
     * @return null|array
     */
    private function findAliasByLocalizedAliases($arrFragments, $strLanguage)
    {
        $arrAlias = array(
            'alias'     => $arrFragments[0],
            'l10nAlias' => ''
        );

        $arrAliasGuess = \Config::get('folderUrl')
            ? $this->createAliasGuessingArray($arrFragments)
            : array();

        $strAlias = !empty($arrAliasGuess)
            ? implode("','", $arrAliasGuess)
            : $arrFragments[0];

        $database = \Database::getInstance();

        // Find alias usages by language from tl_page and tl_page_i18nl10n
        $sql = "(SELECT pid as pageId, alias, 'tl_page_i18nl10n' as 'source', language
                 FROM tl_page_i18nl10n
                 WHERE alias IN(?) AND language = ?)
                UNION
                (SELECT id as pageId, alias, 'tl_page' as 'source', language
                 FROM tl_page
                 WHERE alias IN(?))
                ORDER BY "
            . $database->findInSet('alias', $arrAliasGuess) . ", "
            . $database->findInSet('source', array('tl_page_i18nl10n', 'tl_page'));

        $objL10nPage = $database
            ->prepare($sql)
            ->execute(
                $strAlias,
                $strLanguage,
                $strAlias
            );


        $strHost = \Environment::get('host');

        // If page(s) where found, get l10n alias and related parent page
        while ($objL10nPage->next()) {

            // Only set the l10n alias if the found page is realy a translation.
            if ($objL10nPage->language === $strLanguage) {
                $arrAlias['l10nAlias'] = $objL10nPage->alias;
            // otherwise it is 100% a wrong match
            } else {
                throw new PageNotFoundException('Page not found: ' . \Contao\Environment::get('uri'));
            }

            // Get tl_page with details
            $objPage = \PageModel::findWithDetails($objL10nPage->pageId);

            if ($objPage !== null) {
                // Save alias of page with related or empty domain
                if (empty($objPage->domain) || $objPage->domain === $strHost) {
                    $arrAlias['alias'] = $objPage->alias;
                    break;
                }
            }
        }

        return $arrAlias;
    }

    /**
     * Create an array of possible aliases
     *
     * @param $arrFragments
     *
     * @return array
     */
    private function createAliasGuessingArray($arrFragments)
    {
        $arrAliasGuess = array();

        if (!empty($arrFragments)) {
            // glue together possible aliases
            foreach ($arrFragments as $key => $fragment) {
                $arrAliasGuess[] = !$key
                    ? $fragment
                    : $arrAliasGuess[$key - 1] . '/' . $fragment;
            }

            // Remove everything that is not an alias
            $arrAliasGuess = array_filter(
                array_map(
                    function ($v) {
                        return preg_match('/^[\pN\pL\/\._-]+$/u', $v) ? $v : null;
                    },
                    $arrAliasGuess
                )
            );

            // Reverse array to get more specific entries first
            $arrAliasGuess = array_reverse($arrAliasGuess);
        }

        return $arrAliasGuess;
    }
}
