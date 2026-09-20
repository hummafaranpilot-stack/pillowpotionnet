# Kinzeno Prelander

Standalone prelander built from `https://get-kinzeno.com/kinzeno/product` (captured 2026-09-20).

## Run

```bash
./serve.sh          # http://localhost:8777
./serve.sh 3000     # custom port
```

Use the server, not `file://` — the videos and ES modules need HTTP.

## Affiliate link

Every CTA (10 buttons) points to:

```
https://get-kinzeno.com/kinzeno/product?affid=275&sub4=utm_source_pillowpotion_kz
```

To change it, find-and-replace that URL in `index.html`.

## What was removed

- All non-CTA links — footer legal pages, support, social, phone links, language switcher.
  Only the 10 CTAs plus in-page `#faq` / `#reviews` scroll anchors remain.
- The cookie consent banner, entirely.
- Tracking: Google Tag Manager, Meta Pixel, Sentry, Clarity, Octocom chat, Everflow.

No external hosts are referenced. Everything loads from `assets/`.

## Layout

- `index.html`
- `assets/build/` — JS modules and one CSS chunk (original filenames; imports resolve between them)
- `assets/css`, `assets/img`, `assets/fonts`, `assets/video`
