<?php

namespace LukeDavis\GcpApiGatewaySpec\Commands;

use LukeDavis\GcpApiGatewaySpec\Generator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'generate',
    description: 'Generates a Google Cloud API Gateway spec file based on a provided config and a given Swagger 2.0 YAML',
    hidden: false
)]
class Generate extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::REQUIRED, 'The input Swagger 2.0 spec file')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'A relative or absolute path to save the generated spec to. Defaults to current directory/generator-output.yaml.')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'The path to the config file.')
            ->addOption('preserve-responses', 'p', InputOption::VALUE_NONE, 'Whether to preserve the provided response schemas from the input spec. By default, this tool will replace them with generic 200 responses.')
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

        if (!file_exists($input->getArgument('input'))) {
            $io->error('Input API spec file not found. Please provide a valid path to a Swagger 2.0 spec file.');

            return Command::FAILURE;
        }

        if (is_null($input->getOption('config')) || !file_exists($input->getOption('config'))) {
            $io->error('Config file not found. Please provide a valid path to a config file.');

            return Command::FAILURE;
        }

        $generator = new Generator(
            $input->getArgument('input'),
            $input->getOption('output'),
            $input->getOption('config'),
            $input->getOption('preserve-responses')
        );

        try {
            $generator->validate();
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        try {
            $generator->generate();
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        try {
            $destination = $generator->save();
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Provided spec \''.$input->getArgument('input').'\' successfully converted and saved to '.$destination);

        return Command::SUCCESS;
    }
}
