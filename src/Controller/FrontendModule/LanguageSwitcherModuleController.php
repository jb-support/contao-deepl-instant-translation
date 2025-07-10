<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\ModuleModel;
use Contao\Template;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\TranslationSettingsRegistry;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;

/**
 * @FrontendModule(category="jb_contao_deepl_instant_translation", name="language_switcher", label="Language Switcher", template="mod_language_switcher_module")
 */
class LanguageSwitcherModuleController extends AbstractFrontendModuleController
{

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        $container = System::getContainer();
        $registry = $container->get(TranslationSettingsRegistry::class);
        $registry->setKey($model->deepl_key);

        $enabledLanguages = unserialize($model->languages);
        $languagesArr = [$model->original_language => Settings::getLanguageString($model->original_language)];

        foreach ($enabledLanguages as $lang) {
            $languagesArr[$lang] = Settings::getLanguageString($lang);
        }
        $translationInProgressStrings = Settings::getTranslatingInProgressStrings();

        $agentLanguage = $this->getAgentLanguage($model);

        $language = $request->attributes->get('language_prefix', $agentLanguage);


        if (!in_array($language, array_keys($languagesArr))) {
            return $this->redirect('/' . $model->original_language . '/' . ltrim($request->getRequestUri(), '/'));
        }

        $registry->setEnabledLanguages($languagesArr);
        $registry->setOriginalLanguage($model->original_language);

        $template->languages = $languagesArr;
        $template->originalLanguage = $model->original_language;
        $template->lang = $language;
        $template->showModal = $model->show_modal ? true : false;
        $template->elementType = $model->element_type;
        $template->translatingStrings = json_encode($translationInProgressStrings);

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
