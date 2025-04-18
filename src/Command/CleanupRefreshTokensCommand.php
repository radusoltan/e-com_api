<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-refresh-tokens',
    description: 'Removes expired refresh tokens from the database',
)]
class CleanupRefreshTokensCommand extends Command
{

    public function __construct(
        private RefreshTokenService $refreshTokenService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Cleaning up expired refresh tokens');

        $count = $this->refreshTokenService->cleanupExpiredTokens();

        if ($count > 0) {
            $io->success(sprintf('Successfully removed %d expired refresh token(s)', $count));
        } else {
            $io->info('No expired refresh tokens found');
        }

        return Command::SUCCESS;
    }
}
