<?php

namespace App\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:database:ensure-indexes',
    description: 'Create ODM collections and indexes declared by document mappings.'
)]
final class EnsureIndexesCommand extends Command
{
    public function __construct(private readonly DocumentManager $documents)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->documents->getSchemaManager()->ensureIndexes();
        $output->writeln('<info>MongoDB indexes are ready.</info>');
        return Command::SUCCESS;
    }
}
