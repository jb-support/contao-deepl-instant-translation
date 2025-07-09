<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\ModuleModel;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\TranslationSettingsRegistry;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;

/**
 * @FrontendModule(category="jb_translation")
 */
class LanguageSwitcherModuleController extends AbstractFrontendModuleController
{

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        $container = \System::getContainer();
        $registry = $container->get(TranslationSettingsRegistry::class);
        $registry->setKey($model->deepl_key);

        $enabledLanguages = unserialize($model->languages);
        $languagesArr = [$model->original_language => Settings::getLanguageString($model->original_language)];

        if ($request->isMethod('POST') && $request->request->has('lang')) {
            $lang = $request->request->get('lang');
            if (in_array($lang, $enabledLanguages) || $lang == $model->original_language) {
                setcookie('lang', $lang, time() + 86400 * 30, '/');
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit();
            }
        }

        foreach ($enabledLanguages as $lang) {
            $languagesArr[$lang] = Settings::getLanguageString($lang);
        }
        $translationInProgressStrings = Settings::getTranslatingInProgressStrings();

        $agentLanguage = $this->getAgentLanguage($model);

        // Check if the language cookie is set, otherwise use the original language
        $cookie = $_COOKIE['lang'] ?? $model->original_language;

        $registry->setEnabledLanguages($languagesArr);
        $registry->setOriginalLanguage($model->original_language);

        $template->languages = $languagesArr;
        $template->lang = $cookie ? $cookie : $agentLanguage;
        $template->showModal = $model->show_modal ? true : false;
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
