<?php

namespace LukeDavis\GcpApiGatewaySpec\Commands;

use LukeDavis\GcpApiGatewaySpec\Generator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'generate',
    description: 'Generates a spec file for Google Cloud API Gateway',
    hidden: false
)]
class Generate extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('spec', InputArgument::REQUIRED, 'The path to the input Swagger 2.0 spec file')
            ->addArgument('config', InputArgument::REQUIRED, 'The path to the config file')
            ->addArgument('output', InputArgument::REQUIRED, 'The path to the output spec file')
        ;
    }

    /**
     * Execute the command.
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $generator = new Generator(
            $input->getArgument('spec'),
            $input->getArgument('config'),
            $input->getArgument('output')
        );

        try {
            $generator->validate();
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        /*
        if ($answer === $result) {
            $io->success('Well done!');
        } else {
            $io->error(sprintf('Aww, so close. The answer was %s', $result));
        }

        if ($io->confirm('Play again?')) {
            return $this->execute($input, $output);
        }
            */

        return Command::SUCCESS;
    }
}
