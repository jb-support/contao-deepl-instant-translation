<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\UrlTranslationsConfig;

class LanguageRedirectListener
{
    private Config $config;
    private UrlTranslationsConfig $urlTranslationsConfig;

    public function __construct()
    {
        $this->config = new Config();
        $this->urlTranslationsConfig = new UrlTranslationsConfig();
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Ensure this is the main request and not a sub-request
        if ($event->isMainRequest() === false) {
            return;
        }

        if ($this->config->getDisabled()) {
            return;
        }

        if (isset($_GET["dev"]) && $_GET["dev"]) {
            setcookie('dev', '1', time() + 3600 * 24 * 30, '/');
        }

        if (isset($_GET["nodev"]) && $_GET["nodev"]) {
            setcookie('dev', '', time() - 3600, '/');
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        if (str_contains($pathInfo, '/contao') || str_contains($pathInfo, '/contao.php')) {
            // Skip Contao backend requests
            return;
        }

        $agentLanguage = null;

        if (!isset($_COOKIE['lang_code']) && $this->config->getRedirectAgent()) {
            $acceptLang = $request->headers->get('Accept-Language');
            if ($acceptLang) {
                $primary = strtolower(substr(trim(explode(';', explode(',', $acceptLang)[0])[0]), 0, 2));
                if (in_array($primary, $this->config->getEnabledLanguages())) {
                    $agentLanguage = $primary;
                    setcookie('agent_language', $agentLanguage, time() + 3600 * 24 * 30, '/');
                }
            }
        }

        if (empty($this->config)) {
            return;
        }

        $originalLang = $this->config->getOriginalLanguage();
        $enabledLanguages = $this->config->getEnabledLanguages();
        $showInUrl = $this->config->getShowInUrl();
        $translations = $this->urlTranslationsConfig->getUrlTranslations();

        $langNew = $agentLanguage ?: $request->request->get('lang');
        $langInUrl = explode('/', $pathInfo)[1];
        $langInUrl = preg_match('/^[a-z]{2}$/', $langInUrl) ? $langInUrl : '';

        $pathWithoutLang = preg_replace('/^\/[a-z]{2}(\/|$)/', '/', $pathInfo, 1);
        // $pathInfo = array_search($pathWithoutLang, $translations) ? $langInUrl . '/' . array_search($pathWithoutLang, $translations) : $pathInfo;

        if($_GET["dev"]) {
            dd($pathInfo, $langInUrl, $langNew, $agentLanguage, $translations);
        }
        if (!$showInUrl) {
            if ($langNew) {
                setcookie('lang_code', $langNew, time() + 3600 * 24 * 30, '/');
                $request->attributes->set('lang_code', $langNew);
            } else {
                $lang = $_COOKIE['lang_code'] ?? $originalLang;
                $request->attributes->set('lang_code', $lang);
            }
            return;
        }

        $lang = $agentLanguage ?: $originalLang;
        $strip = false;

        if ($langNew && preg_match('/^[a-z]{2}$/', $langNew)) {
            $lang = $langNew;
            $strip = true;
        } else if ($langInUrl && preg_match('/^[a-z]{2}$/', $langInUrl)) {
            $lang = $langInUrl;
            $strip = true;
        }

        $maybeLang = substr($pathInfo, 1, 2);
        if ($maybeLang == $lang && $maybeLang != $originalLang) {
            $strip = true;
        }

        if ($lang == $originalLang && !empty($langInUrl)) {
            $request->attributes->set('lang_code', $lang);
            if ($langInUrl !== $lang) {
                $newPath = preg_replace('#^/' . preg_quote($langInUrl, '#') . '(/|$)#', '/' . $lang . '$1', $pathInfo, 1);
            } else if ($langInUrl == $originalLang) {
                $newPath = substr($pathInfo, strlen($langInUrl) + 1);
                if ($newPath === '') {
                    $newPath = '/';
                }
            }
            setcookie('lang_code', $lang, time() + 3600 * 24 * 30, '/');
            $response = new RedirectResponse($newPath);
            $event->setResponse($response);
            return;
        } else if ($lang == $originalLang && empty($langInUrl)) {
            $request->attributes->set('lang_code', $lang);
            setcookie('lang_code', $lang, time() + 3600 * 24 * 30, '/');
            return;
        }

        $newReqPath = $pathInfo;

        if (in_array($lang, $enabledLanguages)) {
            if ($strip) {
                $newReqPath = substr($pathInfo, strlen($lang) + 1);
                if ($newReqPath === '') {
                    $newReqPath = '/';
                }

                $request->server->set('REQUEST_URI', $newReqPath);
                $request->server->set('PATH_INFO', $newReqPath);
                $request->server->set('ORIGINAL_PATH_INFO', $pathInfo);
            }

            if ($langInUrl !== $lang) {
                $newUrl = preg_replace(
                    '#^/' . preg_quote($langInUrl, '#') . '(?=/|$)#',
                    '/' . $lang,
                    $pathInfo,
                    1
                );


                if ($newUrl === '/' . $lang || $newUrl === '/' . $lang . '/') {
                } elseif (strpos($newUrl, '/' . $lang) !== 0) {
                    $newUrl = '/' . $lang . '/' . ltrim($newUrl, '/');
                }

                $response = new RedirectResponse($newUrl);
                $event->setResponse($response);

                setcookie('lang_code', $lang, time() + 3600 * 24 * 30, '/');
                return;
            }

            $request->attributes->set('lang_code', $lang);

            $reflection = new \ReflectionObject($request);
            if ($reflection->hasProperty('pathInfo')) {
                $property = $reflection->getProperty('pathInfo');
                $property->setAccessible(true);
                $property->setValue($request, $newReqPath);
            }

            setcookie('lang_code', $lang, time() + 3600 * 24 * 30, '/');
            return;
        }
    }
}
