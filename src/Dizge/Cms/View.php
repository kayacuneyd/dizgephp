<?php

declare(strict_types=1);

namespace Dizge\Cms;

use Dizge\Security\Identity;

/** Small trusted layout helper for the reference application. Data is escaped by route helpers first. */
final class View
{
    private static function siteUrl(string $path): string
    {
        $base = rtrim((string) (getenv('DIZGECMS_SITE_URL') ?: 'https://dizgecms.digitaltamam.com'), '/');
        return $base . ($path === '' || $path[0] === '/' ? $path : '/' . $path);
    }

    private static function themeStylesheet(?string $themeId = null): string
    {
        try {
            $registry = \app()->container()->get(ExtensionRegistry::class);
            $theme = $themeId === null ? $registry->activeTheme() : ($registry->themes()[$themeId] ?? null);
            if ($theme !== null && is_file($theme->stylesheetPath(\app()->basePath()))) {
                return '<link rel="stylesheet" href="/themes/' . \e($theme->id()) . '/theme.css">';
            }
        } catch (\Throwable) {
        }
        return '';
    }

    private static function activeThemeId(?string $themeId = null): string
    {
        if ($themeId !== null) return $themeId;
        try {
            return (string) \app()->container()->get(ExtensionRegistry::class)->activeThemeId();
        } catch (\Throwable) {
            return 'starter';
        }
    }

    private static function themeTokens(): string
    {
        try {
            $settings = (new SiteRepository(\db()))->settings();
            return '<style>:root{--ck-color-primary:' . \e($settings['theme_accent']) . ';--dizge-theme-content-measure:' . \e($settings['theme_content_measure']) . ';}</style>';
        } catch (\Throwable) {
            return '';
        }
    }

    public static function public(string $title, string $description, string $main, string $canonicalPath = '/demo', ?string $themePreview = null): string
    {
        return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . \e($title) . ' · DizgeCMS</title><meta name="description" content="' . \e($description) . '"><link rel="canonical" href="' . \e(self::siteUrl($canonicalPath)) . '"><link rel="icon" href="/assets/dizgecms-mark.svg" type="image/svg+xml">'
            . '<link rel="stylesheet" href="/assets/ckcss.min.css"><link rel="stylesheet" href="/assets/dizgecms.css">' . self::themeStylesheet($themePreview) . self::themeTokens() . '</head><body data-dizge-theme="' . \e(self::activeThemeId($themePreview)) . '">'
            . '<a class="ck-skip-link" href="#main">Ana içeriğe geç</a><header class="dc-header"><div class="ck-container dc-nav"><a class="dc-brand" href="/">Dizge<span>CMS</span></a><nav aria-label="Ana menü"><a href="/articles">Makaleler</a><a href="/search">Ara</a><a href="/studio">Studio</a></nav></div></header><main id="main">' . $main . '</main>'
            . '<footer class="dc-footer"><div class="ck-container"><strong>DizgeCMS Studio</strong><span>Workflow-first, SQLite destekli referans uygulama.</span></div></footer></body></html>';
    }

    public static function studio(string $title, string $main, ?Identity $identity): string
    {
        $email = $identity === null ? '' : (string) $identity->attribute('email', 'Admin');
        $nav = '<aside class="dc-studio-nav"><a class="dc-brand" href="/">Dizge<span>CMS</span></a><p>STUDIO</p><nav aria-label="Studio menüsü"><a href="/studio">İçerikler</a><a href="/studio/content/new">Yeni içerik</a><a href="/articles" target="_blank" rel="noopener">Public görünüm</a></nav><form method="post" action="/studio/logout"><input type="hidden" name="_token" value="' . \e(\csrf_token()) . '"><button class="dc-link-button" type="submit">Çıkış yap</button></form><small>' . \e($email) . '</small></aside>';
        return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . \e($title) . ' · DizgeCMS Studio</title><meta name="robots" content="noindex,nofollow"><link rel="icon" href="/assets/dizgecms-mark.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/ckcss.min.css"><link rel="stylesheet" href="/assets/dizgecms.css"></head><body><a class="ck-skip-link" href="#main">Ana içeriğe geç</a><div class="dc-studio">' . $nav . '<main id="main" class="dc-studio-main">' . $main . '</main></div></body></html>';
    }

    /** @param array<string, list<string>> $errors */
    public static function errors(array $errors): string
    {
        if ($errors === []) return '';
        $items = '';
        foreach ($errors as $fieldErrors) foreach ($fieldErrors as $error) $items .= '<li>' . \e($error) . '</li>';
        return '<div class="ck-alert ck-alert--danger" role="alert"><strong>Formu gözden geçir.</strong><ul>' . $items . '</ul></div>';
    }

    /** @param array<string,mixed> $content */
    public static function badge(array $content): string
    {
        return '<span class="ck-badge dc-status dc-status--' . \e((string) $content['status']) . '">' . \e((string) $content['status']) . '</span>';
    }
}
