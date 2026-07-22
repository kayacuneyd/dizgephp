<?php

declare(strict_types=1);

use App\Actions\FrameworkStatus;
use App\Http\Controllers\FrameworkController;
use Dizge\Cache\Cache;
use Dizge\Cache\FileStore;
use Dizge\Jobs\FileQueue;
use Dizge\Jobs\JobRegistry;
use Dizge\Mail\Mailer;
use Dizge\Mail\NativeTransport;
use Dizge\Mail\Message;
use Dizge\Mail\QueuedMailer;
use Dizge\Security\ApiTokenStore;

app()->container()->singleton(FrameworkStatus::class, fn (Dizge\Container $container): FrameworkStatus => new FrameworkStatus(app()));
app()->container()->singleton(FrameworkController::class, fn (Dizge\Container $container): FrameworkController => new FrameworkController($container->get(FrameworkStatus::class)));
app()->container()->singleton(Cache::class, fn (Dizge\Container $container): Cache => new Cache(new FileStore(app()->basePath() . '/storage/cache')));
app()->container()->singleton(JobRegistry::class, fn (Dizge\Container $container): JobRegistry => new JobRegistry());
app()->container()->singleton(FileQueue::class, fn (Dizge\Container $container): FileQueue => new FileQueue(app()->basePath() . '/storage/jobs'));
app()->container()->singleton(Mailer::class, fn (Dizge\Container $container): Mailer => new Mailer(new NativeTransport((string) (getenv('DIZGE_MAIL_FROM') ?: 'noreply@localhost'))));
app()->container()->singleton(QueuedMailer::class, fn (Dizge\Container $container): QueuedMailer => new QueuedMailer($container->get(FileQueue::class)));
app()->container()->singleton(ApiTokenStore::class, fn (Dizge\Container $container): ApiTokenStore => new ApiTokenStore(db()));
app()->container()->get(JobRegistry::class)->register('mail.send', function (array $payload): void {
    app()->container()->get(Mailer::class)->send(Message::fromArray($payload));
});
