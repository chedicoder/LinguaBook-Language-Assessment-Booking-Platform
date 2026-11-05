<?php

namespace App\Command;

use App\Document\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create-admin',
    description: 'Create an administrator account using interactive prompts.'
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $emailQuestion = new Question('Admin email: ');
        $nameQuestion = new Question('Admin name: ');
        $passwordQuestion = new Question('Admin password (minimum 12 characters): ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);

        $email = strtolower(trim((string) $helper->ask($input, $output, $emailQuestion)));
        $name = trim((string) $helper->ask($input, $output, $nameQuestion));
        $password = (string) $helper->ask($input, $output, $passwordQuestion);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($password) < 12) {
            $output->writeln('<error>Provide a valid email, non-empty name, and password with at least 12 characters.</error>');
            return Command::INVALID;
        }
        if ($this->users->findByEmail($email) !== null) {
            $output->writeln('<error>An account with that email already exists.</error>');
            return Command::FAILURE;
        }

        $user = new User($name, $email, '');
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $this->users->save($user);

        $output->writeln('<info>Administrator account created.</info>');
        return Command::SUCCESS;
    }
}
