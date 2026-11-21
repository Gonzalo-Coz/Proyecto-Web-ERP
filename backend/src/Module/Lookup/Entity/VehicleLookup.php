<?php

declare(strict_types=1);

namespace App\Module\Lookup\Entity;

use App\Module\Lookup\Repository\VehicleLookupRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Caché PERMANENTE de consultas vehiculares por placa, guardada en la base de
 * datos (no en archivos). Así una placa ya consultada no vuelve a gastar cupo del
 * proveedor: sobrevive a los despliegues y al reinicio mensual del plan.
 *
 * Solo datos TÉCNICOS del vehículo (no el titular), conforme a la Ley 29733.
 */
#[ORM\Entity(repositoryClass: VehicleLookupRepository::class)]
#[ORM\Table(name: 'vehicle_lookups')]
#[ORM\UniqueConstraint(name: 'uq_vehicle_lookup_placa', columns: ['placa'])]
class VehicleLookup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private string $placa;

    #[ORM\Column(length: 80)]
    private string $marca = '';

    #[ORM\Column(length: 80)]
    private string $modelo = '';

    #[ORM\Column(length: 60)]
    private string $color = '';

    #[ORM\Column(length: 60)]
    private string $serie = '';

    #[ORM\Column(length: 60)]
    private string $motor = '';

    #[ORM\Column(length: 40)]
    private string $vin = '';

    #[ORM\Column(length: 40)]
    private string $provider = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $placa)
    {
        $this->placa = strtoupper($placa);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlaca(): string
    {
        return $this->placa;
    }

    public function getMarca(): string
    {
        return $this->marca;
    }

    public function getModelo(): string
    {
        return $this->modelo;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function getSerie(): string
    {
        return $this->serie;
    }

    public function getMotor(): string
    {
        return $this->motor;
    }

    public function getVin(): string
    {
        return $this->vin;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setData(string $marca, string $modelo, string $color, string $serie, string $motor, string $vin, string $provider): void
    {
        $this->marca = $marca;
        $this->modelo = $modelo;
        $this->color = $color;
        $this->serie = $serie;
        $this->motor = $motor;
        $this->vin = $vin;
        $this->provider = $provider;
    }
}
