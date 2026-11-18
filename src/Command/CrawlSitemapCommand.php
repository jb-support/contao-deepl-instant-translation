<?php
namespace JBSupport\ContaoDeeplInstantTranslationBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use JBSupport\ContaoDeeplInstantTranslationBundle\Service\SitemapCrawlerService;

#[AsCommand(name: 'jbsupport:crawl', description: 'Crawl the sitemap for translation')]
class CrawlSitemapCommand extends Command
{
    public function __construct(private SitemapCrawlerService $crawler) {
        parent::__construct();
    }

    protected function configure() {
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'The host to crawl (e.g. https://domain.com)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $host = $input->getOption('host');

        if (!$host) {
            $io->error('Please provide a --host option');
            return Command::FAILURE;
        }

        $io->info("Starting crawl for $host...");
        $data = $this->crawler->crawl($host);

        if (isset($data['error'])) {
            $io->error($data['error']);
            return Command::FAILURE;
        }

        $io->success("Finished! Found " . count($data['urls']) . " URLs.");
        return Command::SUCCESS;
    }
}