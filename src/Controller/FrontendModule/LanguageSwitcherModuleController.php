<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Controller\FrontendModule;

use Contao\System;
use Contao\Template;
use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;
use JBSupport\ContaoDeeplInstantTranslationBundle\Model\PageTranslationModel;

/**
 * @FrontendModule(category="jb_contao_deepl_instant_translation", name="language_switcher", label="Language Switcher", template="mod_language_switcher_module")
 */
class LanguageSwitcherModuleController extends AbstractFrontendModuleController
{
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        /*
            Here we work with the ModuleModel instead of the Config class,
            because the ModuleModel already contains all the settings for the module, except the DeepL key.
        */

        $enabledLanguages_old = unserialize($model->languages);
        $enabledLanguages = [];
        $alias = $request->getPathInfo();
        $translations = PageTranslationModel::findBy(['alias LIKE ?'], [$alias]);
        if($translations) {
            $enabledLanguages = $translations->fetchEach("language");
            array_unshift($enabledLanguages, $model->original_language);
            $enabledLanguages = array_values($enabledLanguages);
        }

        $enabledLanguagesSort = $model->languages ? unserialize($model->languages) : [];

        $enabledLanguages = array_unique($enabledLanguages);
        $enabledLanguagesSort = array_unique($enabledLanguagesSort);

        usort($enabledLanguages, function($a, $b) use ($enabledLanguagesSort) {
            $aIndex = array_search($a, $enabledLanguagesSort);
            $bIndex = array_search($b, $enabledLanguagesSort);
            return $aIndex - $bIndex;
        });

        $short_labels = $model->element_label_type == 'short';
        $languagesArr = [];

        $languagesArr[0] = [$model->original_language => $short_labels ? strtoupper($model->original_language) : Settings::getLanguageString($model->original_language)];

        $step = 0;
        for($i = 1; $i < count($enabledLanguages); $i++) {
            if ($i % 5 == 0 && $i != 0) {
                $step++;
            }

            $languagesArr[$step][$enabledLanguages[$i]] = $short_labels ? strtoupper($enabledLanguages[$i]) : Settings::getLanguageString($enabledLanguages[$i]);
        }

        $translationInProgressStrings = Settings::getTranslatingInProgressStrings();
        $translationSubstrings = Settings::getTranslatingSubstrings();

        $agentLanguage = $this->getAgentLanguage($model);

        $language = $request->attributes->get('lang_code', $agentLanguage);

        $template->languages = $languagesArr;
        $template->originalLanguage = $model->original_language;
        $template->lang = $language;
        $template->getFlagSvg = function ($lang) {
            return Settings::getFlagSvg($lang);
        };
        $template->showModal = $model->show_modal ? true : false;
        $template->disabled = (bool) $model->disabled;
        $template->elementType = $model->element_type;
        $template->requestToken = System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue();
        $template->translatingStrings = json_encode($translationInProgressStrings);
        $template->translatingSubstrings = json_encode($translationSubstrings);

        return $template->getResponse();
    }

    protected function getAgentLanguage($model)
    {
        $enabledLanguages = unserialize($model->languages);

        if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $enabledLanguages)) {
            return $_COOKIE['lang'];
        }

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $acceptLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($acceptLangs as $acceptLang) {
                $langCode = strtolower(substr(trim($acceptLang), 0, 2));
                if (in_array($langCode, $enabledLanguages)) {
                    return $langCode;
                }
            }
        }

        return $model->original_language;
    }
}
