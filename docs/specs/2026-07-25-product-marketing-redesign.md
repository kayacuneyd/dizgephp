# DizgeCMS Product Marketing Redesign Spec

## Goal

`dizgecms.digitaltamam.com` sitesini framework vitrini olmaktan çıkarıp, indirilebilir ve
shared-hosting uyumlu hybrid CMS ürününün satış/aktivasyon yüzeyi hâline getirmek. Ziyaretçi üç
net yolu anlamalı: indir-kur, Studio ile site yönet, DevinimJS ile headless frontend kur.

## Source Inventory

2026-07-25 crawl: `/`, `/docs`, `/tutorials`, `/download`, `/about`, `/contact` — altı sayfa,
hata yok. Mevcut ana sayfa ve download içeriği `dizgephp` clone, CRUD scaffold ve framework
terminolojisine odaklı. CMS özellikleri yalnızca yüzeysel geçiyor.

## Target Sitemap

- `/` — ürün değer önerisi, üç kullanım yolu, canlı Studio ve indirme CTA.
- `/features` — Studio, workflow, medya, tema/eklenti, güvenlik, SEO, API.
- `/headless` — REST contract, DevinimJS starter, same-origin/separate-origin mimarisi.
- `/install` — CLI, ZIP, web wizard, shared-hosting gereksinimleri ve güvenlik notları.
- `/download` — release package, SHA-256, sürüm notları, GitHub yerine DizgeCMS paketi.
- `/docs` — kurulum, Studio, API, tema/eklenti ve operasyon dokümanlarına yönlendirme.
- `/migrate-wordpress` — WXR import sınırları ve güvenli geçiş rehberi.
- `/about`, `/contact` — korunur, CMS odağıyla yeniden yazılır.

## Page Responsibilities

- Ana sayfa: “WordPress benzeri kullanım + headless özgürlük + shared hosting” mesajını tek
ekranda kurar; `Release’i indir`, `Canlı Studio`, `Headless rehber` CTA’ları bulunur.
- Install: `php bin/dizge install`, web wizard’ın güvenli etkinleştirilmesi, document-root ve
backup notlarını gösterir.
- Features: hiçbir özelliği vaat olarak değil, mevcut/planlanan etiketiyle sunar.
- Headless: public API, draft preview, CORS allowlist ve token sınırlarını açıklar.

## Reusable Content

- Feature card: Studio, workflow, medya, API, shared hosting, security.
- Install step card: indir, checksum, asset publish, install, Studio login.
- Architecture diagram: Studio → CMS core/API → classic theme veya DevinimJS frontend.
- Trust strip: PHP 8.1+, SQLite, build-free, HTTPS, scoped tokens.
- Release panel: version, checksum, CLI command, release notes link.

## Content To Drop Or Rewrite

- Ana sayfadaki framework CRUD örneği ve `dizgephp` clone komutları rewrite edilir.
- “30 saniyede başla” CMS install akışına dönüşür.
- DevinimJS Bridge ayrıntısı ana sayfadan Headless/Docs sayfasına taşınır.
- `docs` ve `tutorials` korunur fakat CMS bilgi mimarisine göre kategorize edilir.

## SEO And Redirects

- Mevcut `/`, `/docs`, `/tutorials`, `/download`, `/about`, `/contact` URL’leri korunur.
- Yeni `/features`, `/headless`, `/install`, `/migrate-wordpress` sitemap’e eklenir.
- Canonical alan adı `https://dizgecms.digitaltamam.com` kalır.
- Başlık/descriptions ürün terminolojisine güncellenir; robots ve sitemap korunur.

## Visual Direction

Sakin, teknik fakat ürün odaklı. Mevcut CKCSS görsel dili korunur; ana sayfada büyük ürün
mesajı, terminal/Studio/API örnekleri ve üç net CTA kullanılır. Mobilde CTA’lar dikey, kod
blokları yatay kaydırılabilir, Studio ekran görüntüleri lazy-load edilir.

## Implementation Notes

- DizgePHP server rendering, CKCSS ve DevinimJS progressive enhancement korunur.
- Her sayfa explicit route ve SEO metadata ile eklenir.
- Release linkleri gerçek yerel package/checksum kaynaklarına veya yayınlanmış release URL’sine
bağlanmadan placeholder olarak bırakılmaz.
- İddialar `docs/project-memory.md` ve test kanıtlarıyla eşleşmelidir.

## Open Questions

- Release paketi için public kalıcı indirme URL’si ve sürüm yayınlama politikası.
- Canlı Studio demo hesabının anonim erişim politikası.
- Ürün ekran görüntülerinin/marka görsellerinin nihai kaynakları.
