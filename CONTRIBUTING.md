# Contributing

Thanks for taking the time to contribute.

## Reporting a bug

Open an issue at
<https://github.com/proudnerds-typo3/pn_category_filter/issues> and include:

- TYPO3 and PHP version
- The extension version
- The relevant FlexForm and TypoScript settings
- What you expected and what happened instead
- Browser console output when the problem is in the AJAX behaviour

## Pull requests

1. Branch off `main`.
2. Keep the change focused — one topic per pull request.
3. Follow the existing conventions:
   - PHP: PSR-12, `declare(strict_types=1)`, constructor injection, PHPStan
     level 5
   - Fluid: `<f:translate>` for every user-facing string, BEM class names,
     `data-*` attributes for JavaScript hooks
   - JavaScript: ES2025 classes with private fields, no semicolons
4. Add or update the `.xlf` labels for all three languages (en, nl, de) when you
   touch user-facing text.
5. Update `Documentation/` and `CHANGELOG.md` in the same pull request.

## JavaScript: two bundles

The frontend JavaScript exists twice on purpose:

| File | Used by |
|---|---|
| `Resources/Private/Assets/Scripts/` | The ES module sources, consumed by a bundler such as Vite |
| `Resources/Public/JavaScript/PnCategoryFilter.js` | A pre-built IIFE for sites without a build step |

The IIFE bundle is generated from the sources with esbuild; never edit it by
hand. After any change to `Resources/Private/Assets/`, rebuild it:

```bash
npx esbuild Resources/Private/Assets/PnCategoryFilter.entry.js --bundle \
  --format=iife --target=es2022 --charset=utf8 --legal-comments=none \
  '--banner:js=/* Generated from Resources/Private/Assets/ with esbuild, do not edit. See CONTRIBUTING.md. */' \
  --outfile=Resources/Public/JavaScript/PnCategoryFilter.js
```

A pull request that changes the sources without the rebuilt bundle will be
asked to add it.

## Releasing

1. Update `CHANGELOG.md` and `Documentation/Changelog.rst`.
2. Bump the version in `composer.json` **and** `ext_emconf.php`
   (`vendor/bin/tailor set-version <version>` does both), in
   `Documentation/guides.xml`.
3. Rebuild the IIFE bundle (see above) and check it on a page without a build
   step.
4. Tag the release (`vX.Y.Z`) and push the tag — Packagist picks it up through
   the webhook.
4. Upload to the TER manually, or with
   `vendor/bin/tailor ter:publish <version>`.
