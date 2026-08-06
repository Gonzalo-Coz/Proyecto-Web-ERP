<?php

declare(strict_types=1);

namespace App\Module\Lookup\Command;

use App\Module\Lookup\Infrastructure\ApisPeru\ApisPeruConfig;
use App\Module\Lookup\Service\CompanyLookupService;
use App\Module\Lookup\Service\PersonLookupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Diagnóstico de la integración de consultas DNI/RUC (APISPERU).
 *
 * Corre en un proceso CLI NUEVO (sin bytecode cacheado por opcache), usando el
 * mismo php.ini que el servidor, sin pasar por el frontend ni por la
 * autenticación. Muestra la configuración cargada desde el entorno y el error
 * REAL de transporte (cURL) si lo hay.
 *
 * Uso:
 *   php bin/console app:lookup:test 20131312955   (RUC)
 *   php bin/console app:lookup:test 77819251      (DNI)
 */
#[AsCommand(name: 'app:lookup:test', description: 'Diagnostica las consultas DNI/RUC (APISPERU)')]
final class TestLookupCommand extends Command
{
    public function __construct(
        private readonly ApisPeruConfig $config,
        private readonly PersonLookupService $personLookup,
        private readonly CompanyLookupService $companyLookup,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('document', InputArgument::REQUIRED, 'DNI (8 dígitos) o RUC (11 dígitos)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $doc = trim((string) $input->getArgument('document'));

        $io->section('1) Configuración cargada desde el entorno');
        foreach ($this->config->debugSnapshot() as $key => $value) {
            $io->writeln(sprintf('   %-14s %s', $key.':', \is_bool($value) ? ($value ? 'sí' : 'NO') : (string) $value));
        }
        $io->writeln(sprintf('   %-14s %s', 'ext-curl:', \function_exists('curl_init') ? 'disponible' : 'NO disponible'));

        $io->section('2) Consulta al proveedor');
        try {
            if (preg_match('/^\d{8}$/', $doc) === 1) {
                $result = $this->personLookup->byDni($doc)->toArray();
            } elseif (preg_match('/^\d{11}$/', $doc) === 1) {
                $result = $this->companyLookup->byRuc($doc)->toArray();
            } else {
                $io->error('El documento debe tener 8 dígitos (DNI) u 11 dígitos (RUC).');

                return Command::INVALID;
            }

            $io->success('Consulta EXITOSA');
            $io->writeln((string) json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('FALLÓ → %s', $e::class));
            $io->writeln('   Mensaje: '.$e->getMessage());
            $io->writeln('   Archivo: '.$e->getFile().':'.$e->getLine());

            return Command::FAILURE;
        }
    }
}
