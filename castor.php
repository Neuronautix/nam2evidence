<?php

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Symfony\Component\Console\Input\InputOption;

use function Castor\io;
use function Castor\run;

#[AsTask(description: 'Start the Docker Compose stack and initialize the demo data.')]
function start(
    #[AsOption(description: 'Load the resolved NAM-CORE demo state.', mode: InputOption::VALUE_NONE)]
    bool $corrected = false,
): void {
    io()->title('Starting nam2evidence');

    run(['docker', 'compose', 'up', '-d', '--build']);

    docker_compose_exec_api(['php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction']);
    docker_compose_exec_api(['php', 'bin/console', 'app:load-demo-data', '--force']);
    docker_compose_exec_api(['php', 'bin/console', 'app:load-ontology-seed']);

    $namCoreDemoCommand = ['php', 'bin/console', 'app:load-namcore-demo'];
    if ($corrected) {
        $namCoreDemoCommand[] = '--corrected';
    }
    docker_compose_exec_api($namCoreDemoCommand);

    io()->success([
        'Stack started and demo data initialized.',
        'Frontend: http://localhost:3000',
        'API docs: http://localhost:8080/api/docs',
        'Validator: http://localhost:8000/health',
    ]);
}

/**
 * @param list<string> $command
 */
function docker_compose_exec_api(array $command): void
{
    run(['docker', 'compose', 'exec', '-T', 'api', ...$command]);
}
