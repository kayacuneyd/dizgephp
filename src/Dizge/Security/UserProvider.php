<?php
declare(strict_types=1);
namespace Dizge\Security;
interface UserProvider { public function findByIdentifier(string $identifier): ?UserRecord; }
