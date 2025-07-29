<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\ModuleModel;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;

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

        $enabledLanguages = unserialize($model->languages);

        $short_labels = $model->element_label_type == 'short';

        $languagesArr = [$model->original_language => $short_labels ? strtoupper($model->original_language) : Settings::getLanguageString($model->original_language)];

        foreach ($enabledLanguages as $lang) {
            $languagesArr[$lang] = $short_labels ? strtoupper($lang) : Settings::getLanguageString($lang);
        }

        $translationInProgressStrings = Settings::getTranslatingInProgressStrings();

        $agentLanguage = $this->getAgentLanguage($model);

        $language = $request->attributes->get('language_prefix', $agentLanguage);

        if (!in_array($language, array_keys($languagesArr))) {
            return $this->redirect('/' . $model->original_language . '/' . ltrim($request->getRequestUri(), '/'));
        }

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
