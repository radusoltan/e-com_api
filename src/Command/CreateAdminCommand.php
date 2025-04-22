<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private RoleRepository $roleRepository,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'The username of the admin user')
            ->addArgument('password', InputArgument::OPTIONAL, 'The password of the admin user')
            ->addArgument('email', InputArgument::OPTIONAL, 'The email of the admin user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Create Admin User');

        // Get username
        $username = $input->getArgument('username');
        if (!$username) {
            $question = new Question('Please provide a username: ');
            $username = $io->askQuestion($question);
        }

        // Check if username already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existingUser) {
            $io->error(sprintf('User "%s" already exists!', $username));
            return Command::FAILURE;
        }

        // Get password
        $password = $input->getArgument('password');
        if (!$password) {
            $question = new Question('Please provide a password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $io->askQuestion($question);
        }

        // Get email
        $email = $input->getArgument('email');
        if (!$email) {
            $question = new Question('Please provide an email (optional): ');
            $email = $io->askQuestion($question);
        }

        // Create the user
        $user = new User();
        $user->setUsername($username);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsActive(true);
        if ($email) {
            $user->setEmail($email);
        }

        // Find the admin role
        $adminRole = $this->roleRepository->findByName('admin');
        if (!$adminRole) {
            $io->warning('Admin role not found. Please run "app:init-permissions" command first.');
            $io->text('Creating user without admin role...');
        } else {
            $user->addRole($adminRole);
        }

        // Save the user
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success(sprintf('Admin user "%s" has been created successfully!', $username));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
