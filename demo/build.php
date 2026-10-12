<?php

declare(strict_types=1);

/*
 * Builds the site published at https://ruvelo.github.io/filament-security-alerts/.
 *
 * Filament pages are Livewire components: they need a server to answer every
 * click, so they can't be exported as static files the way the package's own
 * demo is. The site is a gallery of the screenshots in art/ (made from a live
 * Halyard panel by demo/screenshots.sh), linking to the package's live demo.
 *
 *   php demo/build.php <site-dir>     # then copy art/ into <site-dir>/art
 */

const REPO = 'https://github.com/Ruvelo/filament-security-alerts';
const PACKAGE_DEMO = 'https://ruvelo.github.io/laravel-security-alerts/';

$site = rtrim($argv[1] ?? __DIR__.'/../build/site', '/');
is_dir($site) || mkdir($site, 0777, true);

$shots = [
    ['screenshot-page', 'Exploited first', 'Every open finding, with the flaw attackers are using at the top: CISA’s listing date and its patch-by date, the severity, the installed version and the one that fixes it. Titles open the advisory.'],
    ['screenshot-exploited', 'Filter to what matters', 'Severity, ecosystem (Composer or npm) and “exploited in the wild”. Search covers package names, titles, CVEs and advisory IDs.'],
    ['screenshot-dashboard', 'On the dashboard, red when it should be', 'Counts by severity, the exploited flaw named, and when the last check ran. The widget turns red when anything is exploited, and warns if no check ran for 48 hours.'],
    ['screenshot-clear', 'And when it’s all fixed', 'A check that finds nothing says so, and the navigation badge goes away.'],
];

$e = fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

$figures = '';
foreach ($shots as [$file, $title, $text]) {
    $figures .= <<<HTML
        <section class="shot">
            <div class="shot-copy">
                <h2>{$e($title)}</h2>
                <p>{$e($text)}</p>
            </div>
            <a href="art/{$file}.png"><img src="art/{$file}.png" width="1440" height="900" loading="lazy" alt="{$e($title)}: a screenshot of the Halyard admin panel"></a>
        </section>

    HTML;
}

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Filament Security Alerts by Ruvelo</title>
    <meta name="description" content="A Filament plugin for ruvelo/laravel-security-alerts: edit pages with a live preview, read the history, restore old versions, all from your admin panel.">
    <meta property="og:image" content="art/banner.png">
    <link rel="icon" href="ruvelo-mark.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400&display=swap">
    <style>
        :root {
            --fsa-bg: #ffffff; --fsa-subtle: #f8f8fc; --fsa-muted: #f0f0f7; --fsa-line: #e4e4ef;
            --fsa-text: #16162a; --fsa-text-2: #4b4b63; --fsa-text-3: #74748b;
            --fsa-accent: #3d4eff; --fsa-accent-hover: #2b38d6; --fsa-accent-soft: #eef0ff; --fsa-on-accent: #ffffff; --fsa-lilac: #a78bfa;
            --fsa-sans: "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            --fsa-mono: "Geist Mono", ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            color-scheme: light dark;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --fsa-bg: #11111c; --fsa-subtle: #171725; --fsa-muted: #1f1f30; --fsa-line: #2a2a3f;
                --fsa-text: #f1f1f8; --fsa-text-2: #b6b6cc; --fsa-text-3: #8787a3;
                --fsa-accent: #8f9bff; --fsa-accent-hover: #b3bbff; --fsa-accent-soft: #1e2150; --fsa-on-accent: #0b0b1a; --fsa-lilac: #c4b5fd;
            }
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; background: var(--fsa-bg); color: var(--fsa-text); font: 400 16px/1.6 var(--fsa-sans); -webkit-font-smoothing: antialiased; }
        a { color: var(--fsa-accent); }
        a:focus-visible { outline: 2px solid var(--fsa-accent); outline-offset: 3px; border-radius: 4px; }
        .stripe { height: 3px; background: linear-gradient(90deg, #3d4eff, #a78bfa); }
        .notice { background: var(--fsa-accent-soft); color: var(--fsa-text-2); font-size: .875rem; line-height: 1.5; padding: .6rem 16px; text-align: center; }
        .notice a { font-weight: 600; }
        .wrap { max-width: 72rem; margin: 0 auto; padding: 0 16px; }
        header.top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.25rem 0; }
        .brand { display: flex; align-items: center; gap: .6rem; color: var(--fsa-text); text-decoration: none; font-weight: 600; letter-spacing: -.02em; }
        .brand img { width: 28px; height: 28px; }
        .brand span { color: var(--fsa-text-3); font-weight: 500; }
        nav.links { display: flex; gap: 1.25rem; font-size: .9375rem; }
        nav.links a { color: var(--fsa-text-2); text-decoration: none; }
        nav.links a:hover { color: var(--fsa-accent); }
        @media (max-width: 40rem) { .brand span { display: none; } nav.links { gap: .9rem; font-size: .875rem; } nav.links a { white-space: nowrap; } }
        .hero { position: relative; padding: 4rem 0 3rem; }
        .hero::before { content: ""; position: absolute; inset: 0 -50vw; z-index: -1; background-image: linear-gradient(var(--fsa-line) 1px, transparent 1px), linear-gradient(90deg, var(--fsa-line) 1px, transparent 1px); background-size: 44px 44px; mask-image: linear-gradient(to bottom, rgb(0 0 0 / .5), transparent 70%); }
        h1 { font-size: clamp(2.4rem, 6vw, 4.25rem); line-height: 1.02; font-weight: 600; letter-spacing: -.045em; margin: 0 0 1.25rem; max-width: 16ch; }
        .lede { font-size: 1.1875rem; color: var(--fsa-text-2); max-width: 40rem; margin: 0 0 2rem; }
        .install { display: inline-flex; max-width: 100%; overflow-x: auto; font: 400 .9375rem/1 var(--fsa-mono); background: var(--fsa-muted); border-radius: 14px; padding: 1rem 1.25rem; white-space: nowrap; }
        .install::before { content: "$\\00a0"; color: var(--fsa-text-3); }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.5rem; }
        .button { display: inline-flex; align-items: center; min-height: 44px; padding: 0 1.1rem; border-radius: 8px; font-weight: 500; text-decoration: none; border: 1px solid var(--fsa-line); color: var(--fsa-text); }
        .button.primary { background: var(--fsa-accent); border-color: var(--fsa-accent); color: var(--fsa-on-accent); box-shadow: 0 8px 20px -10px color-mix(in srgb, var(--fsa-accent) 80%, transparent); }
        .button.primary:hover { background: var(--fsa-accent-hover); }
        .shot { display: grid; gap: 1.25rem; padding: 3rem 0; border-top: 1px solid var(--fsa-line); }
        @media (min-width: 60rem) { .shot { grid-template-columns: 18rem 1fr; gap: 2.5rem; align-items: start; } }
        .shot h2 { font-size: 1.375rem; font-weight: 600; letter-spacing: -.02em; margin: 0 0 .5rem; }
        .shot p { color: var(--fsa-text-2); margin: 0; }
        .shot img, .pair img { display: block; width: 100%; height: auto; border: 1px solid var(--fsa-line); border-radius: 14px; }
        .shot img { box-shadow: 0 24px 60px -32px color-mix(in srgb, #3d4eff 60%, transparent); }
        .pair { display: grid; gap: 1.25rem; grid-template-columns: 1fr; padding: 3rem 0; border-top: 1px solid var(--fsa-line); }
        @media (min-width: 48rem) { .pair { grid-template-columns: 3fr 1fr; align-items: start; } }
        .pair figcaption { color: var(--fsa-text-3); font-size: .875rem; margin-top: .5rem; }
        .pair figure { margin: 0; }
        footer { border-top: 1px solid var(--fsa-line); padding: 2rem 0 3rem; color: var(--fsa-text-3); font-size: .875rem; }
    </style>
</head>
<body>
    <div class="stripe"></div>
    <div class="notice">These are screenshots: Filament panels need a server, so there’s no live demo of the plugin. <a href="{$e(PACKAGE_DEMO)}">Try the package itself</a>, or <a href="{$e(REPO)}#readme">install the plugin</a>.</div>
    <div class="wrap">
        <header class="top">
            <a class="brand" href="./"><img src="ruvelo-mark.svg" alt="">Filament Security Alerts <span>by Ruvelo</span></a>
            <nav class="links" aria-label="Project">
                <a href="{$e(REPO)}#readme">Docs</a>
                <a href="{$e(PACKAGE_DEMO)}">Package demo</a>
                <a href="{$e(REPO)}">GitHub</a>
            </nav>
        </header>

        <main>
            <section class="hero">
                <h1>Know when a dependency turns dangerous.</h1>
                <p class="lede">A panel plugin for <a href="https://github.com/Ruvelo/laravel-security-alerts">ruvelo/laravel-security-alerts</a>. See which installed packages have known vulnerabilities, exploited ones first, check again with one click, and keep an eye on it from the dashboard.</p>
                <code class="install">composer require ruvelo/filament-security-alerts</code>
                <div class="actions">
                    <a class="button primary" href="{$e(REPO)}#readme">Read the docs</a>
                    <a class="button" href="{$e(PACKAGE_DEMO)}">Try the package demo</a>
                </div>
            </section>

{$figures}
            <section class="pair" aria-label="Dark mode and mobile">
                <figure><img src="art/screenshot-dark.png" width="1440" height="900" loading="lazy" alt="The findings page in dark mode"><figcaption>Dark mode follows the panel.</figcaption></figure>
                <figure><img src="art/screenshot-mobile.png" width="390" height="844" loading="lazy" alt="The findings page on a phone"><figcaption>And it works on a phone.</figcaption></figure>
            </section>
        </main>

        <footer>
            Built by <a href="https://github.com/francoisbultez">François Bultez</a> at <a href="https://github.com/Ruvelo">Ruvelo</a>, and everyone who contributes. MIT licensed.
        </footer>
    </div>
</body>
</html>

HTML;

file_put_contents($site.'/index.html', $html);
copy(__DIR__.'/ruvelo-mark.svg', $site.'/ruvelo-mark.svg');

fwrite(STDOUT, "Built the gallery into {$site}\n");
