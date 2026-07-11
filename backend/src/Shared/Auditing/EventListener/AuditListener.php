<?php

declare(strict_types=1);

namespace App\Shared\Auditing\EventListener;

use App\Shared\Auditing\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Auditoría automática (§23.6): captura creaciones, modificaciones y
 * eliminaciones de cualquier entidad, con valores anteriores y nuevos.
 *
 * Los registros se insertan en postFlush mediante DBAL directo para no
 * provocar un flush recursivo dentro del ciclo de persistencia.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AuditListener
{
    /** Campos cuyo contenido nunca debe almacenarse en claro. */
    private const SENSITIVE_FIELDS = ['password', 'passphrase', 'secret', 'token'];

    /** @var list<array{action: string, entity: object, old: ?array, new: ?array}> */
    private array $pending = [];

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($this->isIgnored($entity)) {
                continue;
            }
            $this->pending[] = [
                'action' => 'create',
                'entity' => $entity,
                'old' => null,
                'new' => $this->fromChangeSet($uow->getEntityChangeSet($entity), 1),
            ];
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($this->isIgnored($entity)) {
                continue;
            }
            $this->pending[] = [
                'action' => 'update',
                'entity' => $entity,
                'old' => $this->fromChangeSet($uow->getEntityChangeSet($entity), 0),
                'new' => $this->fromChangeSet($uow->getEntityChangeSet($entity), 1),
            ];
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->isIgnored($entity)) {
                continue;
            }
            $this->pending[] = [
                'action' => 'delete',
                'entity' => $entity,
                'old' => ['id' => $this->entityId($entity)],
                'new' => null,
            ];
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pending === []) {
            return;
        }

        $connection = $args->getObjectManager()->getConnection();
        $username = $this->security->getUser()?->getUserIdentifier();
        $ip = $this->requestStack->getCurrentRequest()?->getClientIp();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $rows = $this->pending;
        $this->pending = [];

        foreach ($rows as $row) {
            $class = $row['entity']::class;
            $connection->insert('audit_logs', [
                'username' => $username,
                'ip_address' => $ip,
                'module' => $this->moduleFromClass($class),
                'entity_class' => $class,
                'entity_id' => $this->entityId($row['entity']),
                'action' => $row['action'],
                'old_values' => $row['old'] === null ? null : json_encode($row['old'], JSON_UNESCAPED_UNICODE),
                'new_values' => $row['new'] === null ? null : json_encode($row['new'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ]);
        }
    }

    private function isIgnored(object $entity): bool
    {
        return $entity instanceof AuditLog;
    }

    /**
     * Extrae la posición 0 (valores anteriores) o 1 (valores nuevos) del
     * changeset de Doctrine, enmascarando campos sensibles.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     */
    private function fromChangeSet(array $changeSet, int $position): array
    {
        $values = [];
        foreach ($changeSet as $field => $change) {
            if (in_array(strtolower($field), self::SENSITIVE_FIELDS, true)) {
                $values[$field] = '***';
                continue;
            }
            $values[$field] = $this->toScalar($change[$position]);
        }

        return $values;
    }

    private function toScalar(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \BackedEnum => $value->value,
            is_object($value) => method_exists($value, 'getId')
                ? sprintf('%s#%s', $value::class, (string) $value->getId())
                : $value::class,
            default => $value,
        };
    }

    private function entityId(object $entity): ?string
    {
        if (method_exists($entity, 'getId') && $entity->getId() !== null) {
            return (string) $entity->getId();
        }

        return null;
    }

    /** App\Module\Sales\Entity\Sale → "sales"; App\Shared\... → "shared". */
    private function moduleFromClass(string $class): string
    {
        if (preg_match('/^App\\\\Module\\\\([^\\\\]+)\\\\/', $class, $m) === 1) {
            return strtolower($m[1]);
        }

        return 'shared';
    }
}
