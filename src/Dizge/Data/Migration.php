<?php

declare(strict_types=1);

namespace Dizge\Data;

/** Explicit schema change contract. Migration SQL is a reviewed trusted-code boundary. */
interface Migration
{
    public function id(): string;
    public function up(Database $database): void;
    public function down(Database $database): void;
}
