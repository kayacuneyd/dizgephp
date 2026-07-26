# DizgeCMS + DevinimJS headless starter

Bu klasör Node.js gerektirmeyen statik bir frontend örneğidir. Aynı DizgeCMS kurulumu altında
sunuluyorsa `data-api-base=""` bırakılır. Ayrı domaine kopyalanırsa `index.html` içindeki
`data-api-base` değerini CMS origin'ine ayarlayın ve CMS ortamında bu frontend origin'ini
`DIZGECMS_PUBLIC_ORIGINS` değişkenine tam HTTPS origin olarak ekleyin.

Kullanılan public API: `/api/v1/site`, `/api/v1/menus/primary`, `/api/v1/pages/{slug}`,
`/api/v1/articles/{slug}`, `/api/v1/search` ve `/api/v1/media/{id}`. Starter hiyerarşik menüleri,
arama sonucunu ve CMS-origin medya URL'lerini işler. Tarayıcıda yönetim tokenı, Studio oturumu veya
preview sırrı tutulmaz.

## Yayınlama kontrolü

1. Bu klasörü aynı CMS origininde bir static dizine veya ayrı bir HTTPS static hosta kopyalayın.
2. Ayrı host kullanılıyorsa CMS’de `DIZGECMS_PUBLIC_ORIGINS=https://frontend.example` ayarlayın;
   wildcard veya credentials kullanmayın.
3. Tarayıcıdan ana sayfa, iç içe menü, bir sayfa, bir makale, görsel ve arama akışını kontrol edin.

MVP kapsamı dışı: GraphQL, webhook, SSR, çoklu frontend kontrol düzlemi, görsel CDN ve mobil SDK.

Shared hostingte bu klasörü statik olarak yayınlayın; DevinimJS module yolları CMS ile aynı origin
değilse kendi published DevinimJS dağıtımınıza göre güncellenmelidir.
