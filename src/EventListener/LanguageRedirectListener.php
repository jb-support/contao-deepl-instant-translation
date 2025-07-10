<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Exception\RedirectResponseException;
use DASPRiD\Enum\Exception\UnserializeNotSupportedException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use JBSupport\ContaoDeeplInstantTranslationBundle\Settings;

class LanguageRedirectListener
{
    private array $supportedLanguages = [];

    public function __construct()
    {
        $this->supportedLanguages = array_keys(Settings::getLanguages());
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_contains($path, '/contao') || str_contains($path, '.xml') || str_contains($path, '.php')) {
            return;
        }

        $cookielang = $request->cookies->get('lang', '');
        $newLang = $request->query->get('lang', $cookielang);

        $segments = explode('/', ltrim($path, '/'));
        $langInUrl = $segments[0] ?? '';

        if (in_array($newLang, $this->supportedLanguages, true)) {
            if ($newLang !== $cookielang) {
                $remainingSegments = array_slice($segments, 1);
                $newPath = '/' . $newLang;
                if (!empty($remainingSegments)) {
                    $newPath .= '/' . implode('/', $remainingSegments);
                }
                $response = new RedirectResponse($newPath);
                $response->headers->setCookie(
                    new Cookie('lang', $newLang, strtotime('+1 year'), '/')
                );
                $event->setResponse($response);
                return;
            }
        }

        if (in_array($langInUrl, $this->supportedLanguages, true)) {
            if ($langInUrl !== $cookielang) {
                $remainingSegments = array_slice($segments, 1);
                $newPath = '/' . $langInUrl;
                if (!empty($remainingSegments)) {
                    $newPath .= '/' . implode('/', $remainingSegments);
                }
                $response = new RedirectResponse($newPath);
                $response->headers->setCookie(
                    new Cookie('lang', $langInUrl, strtotime('+1 year'), '/')
                );
                $event->setResponse($response);
                return;
            }

            $request->attributes->set('language_prefix', $langInUrl);
            setcookie('lang', $langInUrl, time() + 365 * 24 * 60 * 60, '/');

            $remainingSegments = array_slice($segments, 1);
            $newPath = '/' . ltrim(implode('/', $remainingSegments), '/');
            $this->overridePathInfo($request, $newPath);
            return;
        }

        $redirectUrl = '/' . $cookielang . $path;
        $response = new RedirectResponse($redirectUrl);
        $response->headers->setCookie(
            new Cookie('lang', $cookielang, strtotime('+1 year'), '/')
        );
        $event->setResponse($response);
        return;
    }

    private function overridePathInfo(Request $request, string $newPath): void
    {
        $refObject = new \ReflectionObject($request);
        if ($refObject->hasProperty('pathInfo')) {
            $prop = $refObject->getProperty('pathInfo');
            $prop->setAccessible(true);
            $prop->setValue($request, $newPath);
        }

        $request->server->set('REQUEST_URI', $newPath);
    }
}
