<?php
namespace JBSupport\ContaoDeeplInstantTranslationBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use JBSupport\ContaoDeeplInstantTranslationBundle\Service\UrlIndexerService;

#[AsCommand(name: 'jbsupport:index', description: 'Write URL translations to config')]
class IndexUrlTranslationsCommand extends Command
{
    public function __construct(private UrlIndexerService $crawler) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $data = $this->crawler->crawl();

        if (isset($data['error'])) {
            $io->error($data['error']);
            return Command::FAILURE;
        }

        $io->success("Finished! Found " . count($data['urls']) . " URLs.");
        return Command::SUCCESS;
    }
}