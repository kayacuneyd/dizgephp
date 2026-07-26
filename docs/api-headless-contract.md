# DizgeCMS Headless Public API Contract

Bu sözleşme `/api/v1` altında versioned, public-only headless okuma yüzeyini tanımlar. Tüm
başarılı cevaplar `{ "data": ... }` zarfını kullanır; bulunamayan kaynaklar framework'ün
`application/problem+json` 404 yanıtını kullanır.

## Public kaynaklar

- `GET /api/v1/site`: ad, açıklama, public ana sayfa, logo/favicon teslim bilgisi ve sosyal
  bağlantılar. Gizli ayarlar veya private storage isimleri yoktur.
- `GET /api/v1/menus/primary` ve `GET /api/v1/menus/footer`: güvenli URL'ler ve yalnızca
  yayınlanmış içerik hedefleri.
- `GET /api/v1/pages`, `GET /api/v1/pages/{slug}`.
- `GET /api/v1/articles`, `GET /api/v1/articles/{slug}`.
- `GET /api/v1/media/{id}`: `id`, delivery `url`, `mime`, `size`.
- `GET /api/v1/search?q=...`.

İçerik cevapları `id`, `type`, `title`, `slug`, `url`, `canonical_url`, `body_html`,
`meta_description`, `status`, `published_at` ve `updated_at` alanlarını içerir. Public endpoint
yalnızca `published` ve `published_at` değeri olan içeriği döndürür.

## CORS, cache ve preview

Varsayılan same-origin erişimdir. Ayrı bir HTTPS frontend için `DIZGECMS_PUBLIC_ORIGINS` içine
virgülle ayrılmış tam origin eklenir; yalnız allowlist eşleştiğinde public okuma cevabı
`Access-Control-Allow-Origin` ve `Vary: Origin` taşır. Credentials, wildcard, preview ve tokenlı
yazma endpoint'leri CORS açmaz. Public medya teslimi bir günlük cache başlığıyla gelir.

`GET /api/v1/preview/{token}` kısa ömürlü, veritabanında hash’lenmiş ve iptal edilebilir Studio
preview tokenı ister. Token yalnızca `DIZGECMS_PREVIEW_SECRET` ayarlıysa üretilir; bunu frontend
ayarına veya tarayıcı depolamasına koymayın.

## Güvenlik sınırı

Bu endpoint'ler yönetim tokenı gerektirmez; bu nedenle taslak, review, approved veya archived
içerik hiçbir koşulda döndürülmez. Yazma endpoint'leri ayrı scoped bearer-token sözleşmesinde
kalır.
