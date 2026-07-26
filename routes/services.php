<?php

declare(strict_types=1);

use App\Actions\FrameworkStatus;
use App\Actions\SendContactMail;
use App\Http\Controllers\FrameworkController;
use App\Http\Controllers\PromoController;
use App\Http\Controllers\ContactController;
use Dizge\Cache\Cache;
use Dizge\Cache\FileStore;
use Dizge\Jobs\FileQueue;
use Dizge\Jobs\JobRegistry;
use Dizge\Mail\Mailer;
use Dizge\Mail\NativeTransport;
use Dizge\Mail\Message;
use Dizge\Mail\QueuedMailer;
use Dizge\Cms\ExtensionRegistry;
use Dizge\Cms\ContactSubmissionRepository;
use Dizge\Security\ApiTokenStore;

app()->container()->singleton(FrameworkStatus::class, fn (Dizge\Container $container): FrameworkStatus => new FrameworkStatus(app()));
app()->container()->singleton(FrameworkController::class, fn (Dizge\Container $container): FrameworkController => new FrameworkController($container->get(FrameworkStatus::class)));

app()->container()->singleton(PromoController::class, function (Dizge\Container $container): PromoController {
    $base = app()->basePath() . '/views/promo/content/';
    $docs = [
        require $base . 'docs_overview.php',
        require $base . 'docs_routing.php',
        require $base . 'docs_views.php',
        require $base . 'docs_security.php',
        require $base . 'docs_database.php',
        require $base . 'docs_devinim.php',
        require $base . 'docs_cli.php',
        require $base . 'docs_deployment.php',
    ];
    $tutorials = [
        require $base . 'tutorial_getting_started.php',
        require $base . 'tutorial_crud.php',
        require $base . 'tutorial_deployment.php',
    ];
    return new PromoController($docs, $tutorials);
});

app()->container()->singleton(SendContactMail::class, fn (Dizge\Container $container): SendContactMail => new SendContactMail(
    $container->get(Mailer::class),
    ['kayacuneyd@gmail.com', 'info@kayacuneyt.com'],
    new ContactSubmissionRepository(db(), (int) (getenv('DIZGECMS_CONTACT_RETENTION_DAYS') ?: 30)),
));

app()->container()->singleton(ContactController::class, fn (Dizge\Container $container): ContactController => new ContactController($container->get(SendContactMail::class)));

app()->container()->singleton(Cache::class, fn (Dizge\Container $container): Cache => new Cache(new FileStore(app()->basePath() . '/storage/cache')));
app()->container()->singleton(JobRegistry::class, fn (Dizge\Container $container): JobRegistry => new JobRegistry());
app()->container()->singleton(FileQueue::class, fn (Dizge\Container $container): FileQueue => new FileQueue(app()->basePath() . '/storage/jobs'));
app()->container()->singleton(Mailer::class, fn (Dizge\Container $container): Mailer => new Mailer(new NativeTransport((string) (getenv('DIZGE_MAIL_FROM') ?: 'noreply@localhost'))));
app()->container()->singleton(QueuedMailer::class, fn (Dizge\Container $container): QueuedMailer => new QueuedMailer($container->get(FileQueue::class)));
app()->container()->singleton(ApiTokenStore::class, fn (Dizge\Container $container): ApiTokenStore => new ApiTokenStore(db()));
app()->container()->get(JobRegistry::class)->register('mail.send', function (array $payload): void {
    app()->container()->get(Mailer::class)->send(Message::fromArray($payload));
});
app()->container()->singleton(ExtensionRegistry::class, fn (Dizge\Container $container): ExtensionRegistry => new ExtensionRegistry(app()->basePath()));
app()->container()->get(ExtensionRegistry::class)->boot();
