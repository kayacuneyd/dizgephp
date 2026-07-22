<?php
declare(strict_types=1);
namespace Dizge\Bridge;
/** Explicit component attribute patch; never exposes internal component state. */
final class Patch
{
    /** @param array<string, scalar|array|null> $props */
    public function __construct(private array $props) {}
    /** @return array{props: array<string, scalar|array|null>} */
    public function toArray(): array { return ['props' => $this->props]; }
}
