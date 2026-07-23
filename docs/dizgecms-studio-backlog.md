# DizgeCMS Studio backlog

Bu liste, ilk referans uygulama sürümünden sonra yapılacak işleri içerir. Öncelik sırası
günlük kullanım, içerik üretimi ve operasyon güvenliği dengesine göre belirlenmiştir.

## Sıradaki işler

1. **Rol odaklı Studio deneyimi**
   - Yalnızca içerik düzenleme yetkisi olan rollere düzenlenebilir form göster.
   - Reviewer ve publisher için içerik/önizleme ile durum geçişlerini ayrı, sade bir
     çalışma yüzeyi yap.
   - Revision karşılaştırmasını alan bazında ve okunabilir fark görünümüyle geliştir.

2. **Medya deneyimi**
   - İçerik düzenleyicisinde yüklenmiş medyayı seçilebilir bir liste olarak göster.
   - Kapak görseli ve zorunlu/önerilen alt metin akışını ekle.
   - Mevcut private-storage, MIME doğrulama ve SVG reddi güvenlik modelini koru.

3. **Gerçek içerik ve yayın kontrolü**
   - İlk sayfa ve makaleleri `published` durumuna alıp public URL, canonical ve sitemap
     çıktısını doğrula.
   - Public URL kuralı: sayfalar `/demo/pages/{slug}`, makaleler
     `/demo/articles/{slug}`. Yalnızca `published` içerik görünür.

4. **İçerik üretim akışı**
   - Paylaşılabilir taslak önizleme bağlantıları ekle.
   - Planlı yayın zamanı ve içerik üretim ekranına yerleşik temel SEO yönlendirmesi ekle.

5. **Operasyon ve yönetim**
   - Yedekten geri dönüş tatbikatını düzenli bir operasyon prosedürüne dönüştür.
   - Audit kayıtları için yalnızca yöneticiye açık Studio görünümü ekle.
   - Production hata sayfalarını kullanıcı dostu, ayrıntı sızdırmayan hâle getir.

6. **Arama ve AI bağlamı**
   - SQLite FTS5 aramayı gerçek içerik hacminde ölç ve iyileştir.
   - İhtiyaç kanıtlandığında, public-only sınırını koruyan opsiyonel vector adapter
     tasarla; şu an ekleme.

## Korunacak sınırlar

- `/demo` referans uygulamadır; ana framework landing ve `/health` davranışı değişmez.
- SQLite varsayılandır; build sistemi, ORM veya harici editör zorunlu değildir.
- Public kayıt/yorum, medya kütüphanesi, iframe/embed, queue/e-posta ve çoklu ekip
  rolleri bu backlog’un dışında kalır; ayrı kapsam kararı gerektirir.
