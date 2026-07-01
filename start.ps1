param(
    [switch]$Corrected
)

$ErrorActionPreference = 'Stop'

Write-Host 'Starting nam2evidence'

docker compose up -d --build

docker compose exec -T api php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T api php bin/console app:load-demo-data --force
docker compose exec -T api php bin/console app:load-ontology-seed

$namCoreDemoCommand = @('compose', 'exec', '-T', 'api', 'php', 'bin/console', 'app:load-namcore-demo')
if ($Corrected) {
    $namCoreDemoCommand += '--corrected'
}

docker @namCoreDemoCommand

Write-Host ''
Write-Host 'Stack started and demo data initialized.'
Write-Host 'Frontend:  http://localhost:3000'
Write-Host 'API docs:  http://localhost:8080/api/docs'
Write-Host 'Validator: http://localhost:8000/health'
