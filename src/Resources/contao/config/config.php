<?php

use JBSupport\ContaoDeeplInstantTranslationBundle\Model\TranslationModel;
use JBSupport\ContaoDeeplInstantTranslationBundle\Controller\FrontendModule\LanguageSwitcherModuleController;

$GLOBALS['FMD']['jb_translation']['language_switcher'] = LanguageSwitcherModuleController::class;

$GLOBALS['TL_MODELS']['tl_jb_translation'] = TranslationModel::class;

\Contao\ArrayUtil::arrayInsert(
    $GLOBALS['BE_MOD'],
    5,
    [
        'content' => [
            'translation' => [
                'tables' => ['tl_jb_translation'],
            ],
        ],
    ]
);
