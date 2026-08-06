<?php

declare(strict_types=1);

namespace App\Module\Invoicing\Entity;

use App\Module\Invoicing\Repository\DocumentSeriesRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Serie y correlativo por tipo de comprobante (§17, §23.10).
 * El correlativo se incrementa con bloqueo pesimista para evitar duplicados.
 */
#[ORM\Entity(repositoryClass: DocumentSeriesRepository::class)]
#[ORM\Table(name: 'document_series')]
#[ORM\UniqueConstraint(name: 'uq_series', columns: ['doc_type', 'series'])]
class DocumentSeries
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Código SUNAT: 01=Factura, 03=Boleta, 07=Nota Crédito, 08=Nota Débito. */
    #[ORM\Column(length: 2)]
    private string $docType;

    #[ORM\Column(length: 4)]
    private string $series;

    #[ORM\Column(options: ['default' => 0])]
    private int $lastCorrelative = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(string $docType, string $series)
    {
        $this->docType = $docType;
        $this->series = strtoupper($series);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocType(): string
    {
        return $this->docType;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function getLastCorrelative(): int
    {
        return $this->lastCorrelative;
    }

    public function nextCorrelative(): int
    {
        return ++$this->lastCorrelative;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
