<?php declare(strict_types=1); $title = 'İletişim'; $description = 'DizgePHP ekibiyle iletişime geçin. Soru, öneri ve katkılarınız için.'; $active = 'contact'; ob_start(); ?>
<section class="dp-section">
  <div class="ck-container ck-stack" style="--ck-stack-space:2rem">
    <header class="dp-section-header">
      <p class="dp-kicker">İLETİŞİM</p>
      <h2>Bize ulaşın</h2>
      <p>Sorularınız, önerileriniz veya katkılarınız için. En kısa sürede dönüş yapacağız.</p>
    </header>

    <?php if ($success ?? false): ?>
    <div class="ck-alert ck-alert--success" role="status" style="max-inline-size:36rem;margin-inline:auto;inline-size:100%">
      <strong>Mesajınız iletildi.</strong> En kısa sürede dönüş yapacağız.
    </div>
    <?php endif; ?>

    <?php if (($errors ?? []) !== []): ?>
    <div class="ck-alert ck-alert--danger" role="alert" style="max-inline-size:36rem;margin-inline:auto;inline-size:100%">
      <strong>Formda hatalar var.</strong> Lütfen aşağıdaki alanları kontrol edin.
    </div>
    <?php endif; ?>

    <form method="post" action="/contact" class="dp-contact-form ck-stack" style="--ck-stack-space:1.25rem" novalidate>
      <?= csrf_field() ?>

      <label>
        Adınız
        <input type="text" name="name" required maxlength="100" value="<?= e((string) ($old['name'] ?? '')) ?>" placeholder="Adınız">
        <?php if (isset($errors['name'])): ?>
        <p class="dp-field-error"><?= e(implode(' ', $errors['name'])) ?></p>
        <?php endif; ?>
      </label>

      <label>
        E-posta
        <input type="email" name="email" required maxlength="200" value="<?= e((string) ($old['email'] ?? '')) ?>" placeholder="ornek@eposta.com">
        <?php if (isset($errors['email'])): ?>
        <p class="dp-field-error"><?= e(implode(' ', $errors['email'])) ?></p>
        <?php endif; ?>
      </label>

      <label>
        Konu
        <input type="text" name="subject" required maxlength="200" value="<?= e((string) ($old['subject'] ?? '')) ?>" placeholder="Mesaj konusu">
        <?php if (isset($errors['subject'])): ?>
        <p class="dp-field-error"><?= e(implode(' ', $errors['subject'])) ?></p>
        <?php endif; ?>
      </label>

      <label>
        Mesajınız
        <textarea name="message" required maxlength="5000" placeholder="Mesajınızı buraya yazın..."><?= e((string) ($old['message'] ?? '')) ?></textarea>
        <?php if (isset($errors['message'])): ?>
        <p class="dp-field-error"><?= e(implode(' ', $errors['message'])) ?></p>
        <?php endif; ?>
      </label>

      <button type="submit" class="ck-button">Gönder</button>
    </form>
  </div>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/layout.php';
