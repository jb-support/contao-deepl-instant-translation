<?php

namespace JBSupport\ContaoDeeplInstantTranslationBundle\Controller;

use Terminal42\ServiceAnnotationBundle\Annotation\ServiceTag;
use Symfony\Component\Routing\Annotation\Route;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use JBSupport\ApiBundle\Entity\Subsidiary;
use JBSupport\ApiBundle\Entity\Job;
use JBSupport\ApiBundle\Repository\JobRepository;
use JBSupport\ApiBundle\Repository\SubsidiaryRepository;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use JBSupport\ContaoDeeplInstantTranslationBundle\Classes\Config;

/**
 * @ServiceTag("controller.service_arguments")
 */
class CrawlController
{
    protected $framework;
    private EntityManagerInterface $em;

    public function __construct(ContaoFramework $framework, EntityManagerInterface $em)
    {
        $this->framework = $framework;
        $this->em = $em;
    }


    /**
    * @Route("/api/crawl_sitemap", name="crawl_sitemap", defaults={"_token_check": false})
    */
    public function crawl(Request $request, SitemapCrawlerService $crawler): JsonResponse
    {
        $host = $request->getSchemeAndHttpHost();
        $data = $crawler->crawl($host);

        if (isset($data['error'])) {
            return new JsonResponse(['error' => $data['error']], 502);
        }

        return new JsonResponse($data, 200, [], true); // true = pretty print
    }
}