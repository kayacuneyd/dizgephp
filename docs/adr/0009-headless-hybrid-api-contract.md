# ADR-0009: Headless/Hybrid API sözleşmesi

## Durum

Kabul edildi — 2026-07-25

## Bağlam

DizgeCMS hem teknik olmayan kullanıcıların Studio + tema ile site kurmasını hem de
DevinimJS gibi ayrı bir frontend'in aynı içerikleri tüketmesini desteklemelidir. Tamamen
headless'a geçmek mevcut klasik kullanım değerini azaltır; yalnızca mevcut HTML rotalarına
bağlanmak ise frontend bağımsızlığını sınırlı bırakır.

## Karar

DizgeCMS hybrid CMS olarak ilerleyecek:

- İçerik, medya, site ayarları ve navigasyon tek çekirdek veri modelinde tutulacak.
- Klasik Studio ve aktif tema public HTML üretmeye devam edecek.
- Headless frontend'ler versioned REST API üzerinden public içerik okuyacak.
- Public API yalnızca yayınlanmış veriyi döndürecek.
- Yönetim yazma işlemleri scoped bearer token ile korunacak.
- Preview ayrı, kısa ömürlü ve imzalı bir akış olacak; admin token tarayıcı frontend'ine
  konulmayacak.
- İlk dağıtım shared-hosting uyumlu, build edilmiş veya build-free frontend olacak; Node.js
  SSR MVP zorunluluğu olmayacak.

İlk sözleşme endpoint grupları:

- `GET /api/v1/site`
- `GET /api/v1/pages` ve `GET /api/v1/pages/{slug}`
- `GET /api/v1/articles` ve `GET /api/v1/articles/{slug}`
- `GET /api/v1/menus/{location}`
- `GET /api/v1/media/{id}`
- `GET /api/v1/search`
- Yetkili preview endpoint'i

## Sonuçlar

- Tek CMS kurulumu klasik veya headless yayınlama biçiminde kullanılabilir.
- API cevapları sürümlenebilir ve DevinimJS starter ile contract test edilebilir.
- CORS, rate limit, preview token ve medya URL politikaları API threat modeline eklenmelidir.
- GraphQL, webhook, SSR, multi-tenant ve marketplace bu MVP'nin dışındadır.

## Alternatifler

- **Sadece klasik CMS:** Son kullanıcı akışı kolay kalır, ayrı frontend entegrasyonu zayıf olur.
- **Tamamen headless:** API sadeliği artar, hazır site kurulum deneyimi ve shared-hosting
  uyumluluğu gereksiz yere zorlaşır.
- **WordPress plugin/theme uyumluluğu:** Kapsamı ve güvenlik yüzeyini kontrol edemediğimiz
  için kabul edilmedi.
