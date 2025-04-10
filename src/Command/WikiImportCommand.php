<?php

namespace LinkORB\Bundle\WikiBundle\Command;

use LinkORB\Bundle\WikiBundle\Services\WikiService;
use PidHelper\PidHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'wiki:import',
    description: 'Wiki import .json file'
)]
class WikiImportCommand extends Command
{
    public function __construct(private readonly WikiService $wikiService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'wikiName',
                InputArgument::REQUIRED,
                'wiki name'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = new PidHelper('var/', 'wiki_import.pid');
        if (!$lock->lock()) {
            $output->writeln('<error>Other wiki import index command running, quiting.</error>');

            return 0;
        }

        $wikiName = $input->getArgument('wikiName');
        if (!$wiki = $this->wikiService->getWikiByName($wikiName)) {
            $output->writeLn('<error>Wiki not found.</error>');

            return 0;
        }

        $content = file_get_contents('php://stdin');
        $array = json_decode($content, true);
        $this->wikiService->import($wiki, $array);

        $output->writeLn('<info>Completed...</info>');

        return 0;
    }
}
