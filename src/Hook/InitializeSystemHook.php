<?php

namespace Verstaerker\I18nl10nBundle\Hook;

use Contao\Input;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Verstaerker\I18nl10nBundle\Classes\I18nl10n;
use Verstaerker\I18nl10nBundle\Exception\NoRootPageException;

/**
 * Class InitializeSystemHook
 * @package Verstaerker\I18nl10nBundle\Hook
 *
 * Implementation of i18nl10n search logic.
 */
class InitializeSystemHook extends System
{
    /** @var  Request */
    protected $request;


    public function __construct()
    {
        parent::__construct();

        $this->import('request_stack', 'request_stack');
        $this->request = $this->request_stack->getCurrentRequest();
    }


    /**
     * @throws NoRootPageException
     */
    public function initializeSystem()
    {
        // Show all contents in Backend
        if (TL_MODE !== 'FE') {
            return true;
        }

        // Catch Facebook token fbclid and redirect without him (trigger 404 errors)...
        if (strpos(\Contao\Environment::get('request'), '?fbclid')) {
            \Contao\Controller::redirect(\strtok(\Contao\Environment::get('request'), '?'));
        }

        // Get locale information for system and user
        $arrLanguages = I18nl10n::getInstance()->getAvailableLanguages();
        //$userLanguage = $this->request->getLocale();
        $userLanguage = "de";

        if (Input::get('debug') == "1") {
            print_r($arrLanguages);
            print_r($userLanguage);
        }


        // Fail if no languages were configured
        if (\count($arrLanguages) === 0) {
            throw new NoRootPageException();
        }

        // Fallback to default language if language of request does not exist
        $languages = $arrLanguages[$_SERVER['HTTP_HOST']] ?: $arrLanguages['*'];
        print_r($languages);
        if (!\in_array($userLanguage, $languages['languages'])) {
            $GLOBALS['TL_LANGUAGE'] = $languages['default'];
        }

        print_r($GLOBALS['TL_LANGUAGE']);
    }
}
