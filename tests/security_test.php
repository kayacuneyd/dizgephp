<?php

declare(strict_types=1);

require dirname(__DIR__) . '/dizge.php';

use Dizge\Devinim\Renderer;

function security_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$renderer = new Renderer();
$escaped = $renderer->render('search', ['label' => '<script>alert(1)</script>']);
security_expect(str_contains($escaped, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'String props must be escaped.');
security_expect(!str_contains($escaped, '<script>alert(1)</script>'), 'Raw script markup must never reach a prop attribute.');

foreach ([
    fn () => $renderer->render('unknown'),
    fn () => $renderer->render('counter', ['notAProp' => 1]),
    fn () => $renderer->render('counter', ['attrs' => ['onclick' => 'alert(1)']]),
    fn () => $renderer->render('counter', ['children' => '<img src=x onerror=alert(1)>']),
] as $unsafeOperation) {
    try {
        $unsafeOperation();
        throw new RuntimeException('Unsafe adapter input must be rejected.');
    } catch (InvalidArgumentException) {
    }
}

echo "DizgePHP security tests passed.\n";
