<?php

declare(strict_types=1);

namespace App\Shared\Pricing\Service;

use App\Shared\Pricing\Entity\PriceHistoryEntry;
use App\Shared\Pricing\Repository\PriceHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Servicio transversal del historial de precios (Adición A3 · §24.4).
 *
 * Punto ÚNICO de registro de cambios de precio: los módulos (Repuestos,
 * Motocicletas, …) lo invocan tras persistir el nuevo precio. Evita duplicar
 * la lógica de trazabilidad y centraliza el reporte de consulta.
 */
final class PriceHistoryService
{
    public const SUBJECT_SPARE_PART = 'spare_part';
    public const SUBJECT_MOTORCYCLE_MODEL = 'motorcycle_model';

    private const SUBJECT_LABELS = [
        self::SUBJECT_SPARE_PART => 'Repuesto',
        self::SUBJECT_MOTORCYCLE_MODEL => 'Modelo de motocicleta',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PriceHistoryRepository $repository,
        private readonly Security $security,
    ) {
    }

    /**
     * Registra un cambio de precio. No hace nada si el precio no cambió
     * realmente (comparación numérica), de modo que los módulos pueden
     * llamarlo siempre sin ensuciar el historial.
     */
    public function record(
        string $subjectType,
        int $subjectId,
        string $subjectLabel,
        ?string $oldPrice,
        ?string $newPrice,
        ?string $reason,
    ): void {
        if (!$this->hasChanged($oldPrice, $newPrice)) {
            return;
        }

        $reason = ($reason !== null && trim($reason) !== '') ? trim($reason) : null;
        $entry = new PriceHistoryEntry(
            $subjectType,
            $subjectId,
            $subjectLabel,
            $oldPrice,
            $newPrice,
            $reason,
            $this->security->getUser()?->getUserIdentifier(),
        );

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    /**
     * Reporte de consulta con filtros y paginación.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function list(
        int $page,
        int $perPage,
        string $subjectType,
        ?int $subjectId,
        string $search,
        string $from,
        string $to,
    ): array {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $qb = $this->repository->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($subjectType !== '' && isset(self::SUBJECT_LABELS[$subjectType])) {
            $qb->andWhere('p.subjectType = :st')->setParameter('st', $subjectType);
        }
        if ($subjectId !== null) {
            $qb->andWhere('p.subjectId = :sid')->setParameter('sid', $subjectId);
        }
        if ($search !== '') {
            $qb->andWhere('LOWER(p.subjectLabel) LIKE :s OR LOWER(p.reason) LIKE :s OR LOWER(p.username) LIKE :s')
                ->setParameter('s', '%'.mb_strtolower($search).'%');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            $qb->andWhere('p.createdAt >= :from')->setParameter('from', new \DateTimeImmutable($from.' 00:00:00'));
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $qb->andWhere('p.createdAt <= :to')->setParameter('to', new \DateTimeImmutable($to.' 23:59:59'));
        }

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'data' => array_map($this->toArray(...), iterator_to_array($paginator, false)),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function toArray(PriceHistoryEntry $e): array
    {
        return [
            'id' => $e->getId(),
            'subjectType' => $e->getSubjectType(),
            'subjectTypeLabel' => self::SUBJECT_LABELS[$e->getSubjectType()] ?? $e->getSubjectType(),
            'subjectId' => $e->getSubjectId(),
            'subjectLabel' => $e->getSubjectLabel(),
            'oldPrice' => $e->getOldPrice(),
            'newPrice' => $e->getNewPrice(),
            'reason' => $e->getReason(),
            'username' => $e->getUsername(),
            'createdAt' => $e->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function hasChanged(?string $oldPrice, ?string $newPrice): bool
    {
        if ($oldPrice === null && $newPrice === null) {
            return false;
        }
        if ($oldPrice === null || $newPrice === null) {
            return true;
        }

        return abs((float) $oldPrice - (float) $newPrice) > 0.0001;
    }
}
