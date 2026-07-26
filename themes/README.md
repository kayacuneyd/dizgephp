# DizgeCMS themes

Themes own presentation, layout, tokens and browser assets. They do not own content persistence,
authorization or workflow decisions. The starter theme will expose responsive server-rendered
templates and SEO slots while remaining usable without JavaScript.

Each theme declares id, name and version in a local theme.json. Theme templates receive published
content and explicit SEO slots only; they cannot access the database or authorization services
directly.

List and select an installed theme from the CLI:

    bin/dizge theme:list
    bin/dizge theme:use starter
