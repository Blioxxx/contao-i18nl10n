<?php
/**
 * i18nl10n Contao Module
 *
 * The i18nl10n module for Contao allows you to manage multilingual content
 * on the element level rather than with page trees.
 *
 *
 * @copyright   Copyright (c) 2014-2015 Verstärker, Patric Eberle
 * @author      Patric Eberle <line-in@derverstaerker.ch>
 * @package     i18nl10n classes
 * @license     LGPLv3 http://www.gnu.org/licenses/lgpl-3.0.html
 */

namespace Verstaerker\I18nl10nBundle\Classes;

use Contao\Controller;
use Contao\PageModel;

/**
 * Class I18nl10nFrontend
 * Common frontend functions go here
 *
 * @package    Controller
 */
class I18nl10nFrontend extends Controller
{
    /**
     * Load database object
     */
    protected function __construct()
    {
        // Import database instance
        $this->import('Database');

        parent::__construct();
    }

    /**
     * Replace title and pageTitle with translated equivalents
     * just before display them as menu. Also set visible elements.
     *
     * @param   array   $items
     * @return  array
     */
    public function l10nNavItems(array $items)
    {
        return self::i18nl10nNavItems($items, true);
    }

    /**
     * Replace title and pageTitle with translated equivalents
     * just before display them as menu.
     *
     * @param   array   $items              The menu items on the current menu level
     * @param   Bool    [$blnUseFallback]   Keep original item if no translation found
     * @return  array   $i18n_items
     *
     * @todo    Refactor usage of generateFrontendUrl()
     */
    public function i18nl10nNavItems(array $items, $blnUseFallback = false)
    {
        if (empty($items)) {
            return false;
        }

        /**
         * Info:
         * Be aware that Contao 3.4.0 supports 'isActive' only for start pages
         * with the alias 'index'. See ticket #7562 (https://github.com/contao/core/issues/7562)
         */

        $arrLanguages = I18nl10n::getInstance()->getLanguagesByDomain();

        //get item ids
        $item_ids = array();

        foreach ($items as $row) {
            $item_ids[] = $row['id'];
        }

        $arrI18nItems = array();

        if ($GLOBALS['TL_LANGUAGE'] !== $arrLanguages['default']) {
            $time = time();
            $fields = 'alias,pid,title,pageTitle,description,url,language';
            $sqlPublishedCondition = $blnUseFallback || BE_USER_LOGGED_IN
                ? ''
                : " AND (start='' OR start < $time) AND (stop='' OR stop > $time) AND i18nl10n_published = 1 ";

            $sql = "
                SELECT $fields
                FROM tl_page_i18nl10n
                WHERE
                    " . $this->Database->findInSet('pid', $item_ids) . '
                    AND language = ?
                    ' . $sqlPublishedCondition;

            $arrLocalizedPages = $this->Database
                ->prepare($sql)
                ->limit(1000)
                ->execute($GLOBALS['TL_LANGUAGE'])
                ->fetchAllassoc();

            foreach ($items as $item) {
                $foundItem = false;

                foreach ($arrLocalizedPages as $row) {
                    // Update navigation items with localization values
                    if ($row['pid'] === $item['id']) {
                        $foundItem = true;
                        $alias = $row['alias'] ?: $item['alias'];

                        $item['alias']  = $alias;
                        $row['alias']   = $alias;
                        $item['language'] = $row['language'];

                        switch ($item['type']) {
                            case 'forward':
                                $intForwardId = $item['jumpTo'] ?: PageModel::findFirstPublishedByPid($item['id'])
                                    ->current()->id;

                                $arrPage = PageModel::findWithDetails($intForwardId)->row();

                                $item['href']       = $this->generateFrontendUrl($arrPage);
                                break;

                            case 'redirect':
                                if ($row['url']) {
                                    $item['href'] = $row['url'];
                                }
                                break;

                            default:
                                $item['href'] = $this->generateFrontendUrl($item);
                                break;
                        }

                        print_r($item['href']);

                        $item['pageTitle'] = specialchars($row['pageTitle'], true);
                        $item['title'] = specialchars($row['title'], true);
                        $item['link'] = $item['title'];
                        $item['description'] = str_replace(
                            array('\n', '\r'),
                            array(' ', ''),
                            specialchars($row['description'])
                        );

                        array_push($arrI18nItems, $item);
                    }
                }

                if ($blnUseFallback && !$foundItem) {
                    array_push($arrI18nItems, $item);
                }
            }
        } else {
            foreach ($items as $item) {
                if (!$blnUseFallback && $item['i18nl10n_published'] == '') {
                    continue;
                }
                array_push($arrI18nItems, $item);
            }
        }

        return $arrI18nItems;
    }
}
