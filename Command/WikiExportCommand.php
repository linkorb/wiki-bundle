<?php

namespace LinkORB\Bundle\WikiBundle\Command;

use LinkORB\Bundle\WikiBundle\Services\WikiService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WikiExportCommand extends Command
{
    private $wikiService;

    public function __construct(WikiService $wikiService)
    {
        $this->wikiService = $wikiService;

        parent:: __construct();
    }

    protected function configure()
    {
        $this
            ->setName('wiki:export')
            ->setDescription('Wiki export as .json file.')
            ->addArgument(
                'wikiName',
                InputArgument::REQUIRED,
                'wiki name'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $wikiName = $input->getArgument('wikiName');

        if (!$wiki = $this->wikiService->getWikiByName($wikiName)) {
            $output->writeLn(
                '<error>Wiki not found.</error>'
            );

            return 0;
        }

        $json = json_encode($this->wikiService->export($wiki), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $output->writeln($json);

        return 0;
    }
}
