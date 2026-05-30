<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Referencia fluida a una ruta registrada (para asignar nombre, etc.).
 */
final class Route
{
    public function __construct(private Router $router, private int $index)
    {
    }

    public function name(string $name): self
    {
        $this->router->setName($this->index, $name);
        return $this;
    }
}
