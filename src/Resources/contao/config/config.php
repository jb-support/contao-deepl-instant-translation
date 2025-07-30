<?php

use Contao\System;
use Contao\ArrayUtil;
use Symfony\Component\HttpFoundation\Request;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\TranslationModel;
use JBSupport\ContaoDeeplInstantTranslationBundle\Controller\FrontendModule\LanguageSwitcherModuleController;

$GLOBALS['FMD']['jb_translation']['language_switcher'] = LanguageSwitcherModuleController::class;

$GLOBALS['TL_MODELS']['tl_jb_translation'] = TranslationModel::class;

if (System::getContainer()->get('contao.routing.scope_matcher')
    ->isBackendRequest(System::getContainer()->get('request_stack')->getCurrentRequest() ?? Request::create(''))
) {
    $GLOBALS['TL_CSS'][] = 'bundles/jbsupportcontaodeeplinstanttranslation/css/backend.css|static';
}

ArrayUtil::arrayInsert(
    $GLOBALS['BE_MOD'],
    array_search('content', array_keys($GLOBALS['BE_MOD'])) + 1,
    ['jb_translations' => [
        'translation' => [
            'tables' => ['tl_jb_translation'],
            'icon'   => 'bundles/jbsupportcontaodeeplinstanttranslation/deepl.svg',
        ],
    ]]
);
