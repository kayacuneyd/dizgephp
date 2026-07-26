# DizgeCMS WordPress Alternatifi Ürün Yol Haritası

Bu yol haritası, DizgeCMS'i geliştiriciye yönelik CMS çekirdeğinden, teknik olmayan
site sahibinin kurup yönetebileceği indirilebilir bir web sitesi ürününe taşımak için
kullanılır.

## Hedef ürün

Kullanıcı kurulumdan sonra yönetici paneline girer; hazır bir site ve varsayılan sayfalar
ile başlar, sayfa ve makale ekler, metin/görsel düzenler, menü oluşturur, taslak kaydeder,
önizler ve yayınlar. Geliştirici aynı kurulumu tema, eklenti, CLI ve API ile genişletebilir.

## Durum özeti

- CMS çekirdeği: tamamlandı / kullanılabilir.
- Geliştirici uzatma modeli: tamamlandı / ilk sürüm.
- Son kullanıcı ürün deneyimi: temel Studio akışı kullanılabilir; blok editörü, medya yönetimi ve gelişmiş menüler geliştirme gerektirir.
- Ayrı staging kurulumu: `127.0.0.1:8088` üzerinde izole edildi; public DNS + TLS staging hâlâ gereklidir.
- Headless/Hybrid MVP: ilk dikey dilim tamamlandı; API dokümantasyonu ve yayın otomasyonu geliştirme gerektirir.

## Headless/Hybrid MVP izlek planı

Bu izlek, klasik Studio + tema kullanımını korurken aynı içeriklerin DevinimJS veya başka
bir frontend tarafından REST API ile tüketilmesini sağlar. Hedef tamamen headless'a geçmek
değil, tek bir içerik çekirdeğinden iki yayınlama biçimi sunmaktır.

### Headless MVP kabul senaryosu

Studio'da yayınlanan bir sayfa veya makale, DevinimJS starter frontend tarafından API'den
alınmalı; site ayarları, menü ve görsel bilgisiyle birlikte render edilmelidir. Taslak içerik
public API'den görünmemeli; yetkili ve kısa ömürlü preview akışıyla incelenebilmelidir.

### Headless MVP sırası

1. API sözleşmesi: site, sayfa, makale, menü, medya, arama ve preview cevap formatları.
2. Site ayarları ve menü modeli: başlık, açıklama, logo, ana sayfa ve navigasyon.
3. Public/draft ayrımı: token scope, CORS, rate limit ve imzalı preview.
4. DevinimJS starter frontend: ana sayfa, sayfa, makale listesi/detayı, menü, medya ve SEO.
5. Shared-hosting paketi: Node.js zorunluluğu olmadan statik/build edilmiş frontend sunumu.
6. API dokümantasyonu, contract testleri ve örnek entegrasyon.

Headless MVP'de GraphQL, webhook, SSR, çoklu frontend yönetimi, CDN görsel servisi ve mobil
SDK kapsam dışıdır. Bu kararın mimari kaydı `docs/adr/0009-headless-hybrid-api-contract.md`.

## Fazlar

### Faz 1 — Kurulum ve ürün başlangıcı

Öncelik: P0

- `php bin/dizge install` ile tek komut kurulumu.
- Web tabanlı first-run wizard: sistem kontrolü, veritabanı, site adı, yönetici hesabı.
- `.env.example`, izinler, migration, başlangıç teması ve asset kurulumu.
- Varsayılan sayfalar: Ana Sayfa, Hakkımızda, Hizmetler, Blog, İletişim.
- İlk menü ve ana sayfa ayarlarının otomatik oluşturulması.
- Tekrarlı çalıştırmada mevcut kurulumu bozmayan idempotent davranış.

Kabul ölçütü: Temiz PHP 8.1+ ortamında kullanıcı, README'deki kısa komutla çalışan bir
site ve Studio hesabı oluşturabilmeli.

### Faz 2 — Site ayarları ve navigasyon

Öncelik: P0

- Site adı, açıklama, logo, favicon, iletişim bilgileri ve sosyal bağlantılar.
- Ana sayfa ve yazılar sayfası seçimi.
- Kalıcı bağlantı ve SEO varsayılanları.
- Menü oluşturma, silme, yeniden adlandırma, sıralama ve öğe ekleme.
- Sayfa, makale, özel URL ve menü hiyerarşisi desteği.

Kabul ölçütü: Kullanıcı kod değiştirmeden temel kurumsal site navigasyonu kurabilmeli.

### Faz 3 — Medya kütüphanesi ve editör deneyimi

Öncelik: P0

- Yüklenmiş medyayı listeleme, arama, filtreleme ve silme.
- Editör içinde medya seçme ve kapak görseli atama.
- Alt metin, başlık ve açıklama alanları.
- Güvenli MIME, boyut ve private-storage sınırlarının korunması.
- Basit görsel/block editörü: başlık, paragraf, liste, bağlantı, görsel, alıntı ve ayırıcı.
- Taslak, autosave, önizleme ve yayınlama akışının sadeleştirilmesi.

Kabul ölçütü: Site sahibi HTML bilmeden bir sayfa oluşturup metin ve görsel ekleyerek
taslak kaydedebilmeli ve yayınlayabilmeli.

### Faz 4 — Tema ve görünüm yönetimi

Öncelik: P1

- Studio içinden tema listeleme ve etkin temayı değiştirme.
- Tema önizleme ve güvenli tema aktivasyonu.
- Logo, renk, tipografi ve temel layout ayarları.
- Tema şablonlarında sayfa, makale ve arşiv sorumluluklarının netleştirilmesi.
- Tema paketleme ve sürüm metadata'sı.

Kabul ölçütü: Kullanıcı çekirdek dosyalara dokunmadan starter temayı seçip temel görünümü
değiştirebilmeli.

### Faz 5 — Kullanıcılar, roller ve yayın operasyonu

Öncelik: P1

- Yönetici, editör, yazar, reviewer ve publisher rolleri.
- Kullanıcı oluşturma, devre dışı bırakma ve parola yenileme.
- Taslak → inceleme → onay → yayın akışı.
- Planlı yayın, paylaşılabilir taslak önizlemesi ve revizyon karşılaştırması.
- Audit görünümü ve ayrıntı sızdırmayan production hata ekranları.

Kabul ölçütü: Küçük bir ekip, yetkileri ayrılmış biçimde içerik üretebilmeli ve yayınlayabilmeli.

### Faz 6 — Dağıtım, güncelleme ve uyumluluk

Öncelik: P1

- Downloadable release ZIP ve sürüm manifesti.
- `curl`/kısa terminal kurulum seçeneği; güvenli checksum doğrulaması.
- Güncelleme öncesi backup, migration ve rollback prosedürü.
- Plugin/theme paket yükleme ve sürüm kontrolü.
- Shared hosting, VPS ve local development kurulum belgeleri.
- Temiz kurulumdan upgrade'e kadar otomatik test matrisi.

Kabul ölçütü: Kullanıcı release paketini indirip kurabilmeli, güncelleyebilmeli ve
başarısız güncellemede yedeğe dönebilmeli.

### Faz 7 — Release doğrulaması

Öncelik: P0 / her sürüm öncesi

- Ayrı staging ortamında install, upgrade, rollback ve backup restore denemesi.
- `sh bin/verify` ve `sh bin/benchmark --check`.
- Auth, CSRF, upload, route, REST scope ve plugin/theme threat-model testleri.
- Masaüstü/mobil Studio taraması.
- Public URL, canonical, robots, sitemap ve draft sızıntısı kontrolleri.
- Release notları ve bilinen sınırlamaların güncellenmesi.

## Uygulama sırası

Uygulama iki paralel fakat kontrollü hat üzerinden ilerleyecek: Faz 1 installer/ürün
başlangıcı ve Headless MVP'nin API sözleşmesi. İlk gerçek dikey dilim tamamlanmadan görsel
editör veya gelişmiş tema özelleştirmesine geçilmeyecek. Her faz sonunda izole test, ilgili
production-safe smoke test ve dokümantasyon güncellemesi yapılacak.

## Kapsam dışı

- Rastgele WordPress plugin/theme çalıştırma uyumluluğu.
- Çok kiracılı SaaS ve marketplace.
- PHP sandboxing.
- Çekirdeğe zorunlu Node.js, ORM veya harici editör bağımlılığı.
