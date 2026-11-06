<?php

use App\Kernel;
use App\Repository\UserRepository;
use App\Service\ReservationService;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$projectDir = dirname(__DIR__, 2);

(new Dotenv())->bootEnv($projectDir.'/.env');

$kernel = new Kernel('test', true);
$kernel->boot();

$container = $kernel->getContainer()->get('test.service_container');

$user = $container->get(UserRepository::class)->find($argv[1] ?? '');

if ($user === null) {
    fwrite(STDERR, "User not found.\n");
    exit(3);
}

try {
    $container->get(ReservationService::class)->reserve(
        $user,
        $argv[2] ?? ''
    );

    fwrite(STDOUT, "reserved\n");
    exit(0);
} catch (\App\Exception\ApiException $exception) {
    fwrite(STDOUT, $exception->errorCode."\n");
    exit(1);
} catch (\Throwable $exception) {
    fwrite(
        STDERR,
        $exception::class.': '.$exception->getMessage()."\n"
    );
    exit(3);
}