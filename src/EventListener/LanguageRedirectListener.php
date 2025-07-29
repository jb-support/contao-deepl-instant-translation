<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;

class LanguageRedirectListener
{
    private Config $config;
    public function __construct()
    {
        $this->config = new Config();
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Ensure this is the main request and not a sub-request
        if ($event->isMainRequest() === false) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        if (str_contains($pathInfo, '/contao') || str_contains($pathInfo, '/contao.php')) {
            // Skip Contao backend requests
            return;
        }

        if(empty($this->config)) {
            return;
        }

        $originalLang = $this->config->getOriginalLanguage();
        $enabledLanguages = $this->config->getEnabledLanguages();
        $showInUrl = $this->config->getShowInUrl();

        $langNew = $request->request->get('lang');
        $langInUrl = explode('/', $pathInfo)[1];
        $langInUrl = preg_match('/^[a-z]{2}$/', $langInUrl) ? $langInUrl : '';


        if (!$showInUrl) {
            if ($langNew) {
                setcookie('language_prefix', $langNew, time() + 3600 * 24 * 30, '/');
                $request->attributes->set('language_prefix', $langNew);
            } else {
                $lang = $_COOKIE['language_prefix'] ?? $originalLang;
                $request->attributes->set('language_prefix', $lang);
            }
            return;
        }

        $lang = $originalLang;
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
            $request->attributes->set('language_prefix', $lang);
            if ($langInUrl !== $lang) {
                $newPath = preg_replace('#^/' . preg_quote($langInUrl, '#') . '(/|$)#', '/' . $lang . '$1', $pathInfo, 1);
            } else if ($langInUrl == $originalLang) {
                $newPath = substr($pathInfo, strlen($langInUrl) + 1);
                if ($newPath === '') {
                    $newPath = '/';
                }
            }

            $response = new RedirectResponse($newPath);
            $event->setResponse($response);
            return;
        } else if ($lang == $originalLang && empty($langInUrl)) {
            $request->attributes->set('language_prefix', $lang);
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
                return;
            }

            $request->attributes->set('language_prefix', $lang);

            $reflection = new \ReflectionObject($request);
            if ($reflection->hasProperty('pathInfo')) {
                $property = $reflection->getProperty('pathInfo');
                $property->setAccessible(true);
                $property->setValue($request, $newReqPath);
            }

            return;
        }
    }
}
