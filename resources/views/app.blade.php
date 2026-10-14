@php
    use App\Support\Locales;
    use App\Support\Seo;

    // O tema e o idioma vem resolvidos no servidor (App\Http\Middleware\
    // ResolveLocale e HandleAppearance): o HTML ja sai com a classe do tema e
    // com o lang certos, sem flash de primeira pintura.
    $appearance = $appearance ?? 'system';
    $localeCode = app()->getLocale();
    $localeConfig = Locales::all()[$localeCode] ?? ['html' => $localeCode, 'dir' => 'ltr'];
    $alternates = Locales::alternates(request());

    // Telas de acesso e paginas de token nao entram no indice nem ganham
    // hreflang (App\Support\Seo); a prop vem compartilhada pelo Inertia.
    $noindex = (bool) ($page['props']['seo']['noindex'] ?? false);

    // A URL desta pagina no idioma dela: e o canonical e tambem o og:url.
    $canonical = $noindex
        ? url()->current()
        : ($alternates[$localeConfig['html']] ?? url()->current());

    // Metas de compartilhamento: so nas paginas indexaveis, que sao as unicas
    // feitas para circular. As de cada pagina (titulo e descricao) saem do
    // <Head> do Inertia, junto com o <title>.
    $og = $noindex ? null : Seo::openGraph(request());
@endphp
<!DOCTYPE html>
<html
    lang="{{ $localeConfig['html'] }}"
    dir="{{ $localeConfig['dir'] }}"
    data-appearance="{{ $appearance }}"
    @class(['dark' => $appearance === 'dark'])
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Sem preferencia salva, o tema segue o sistema: como isso so pode ser
             decidido no cliente, este script roda antes da primeira pintura.
             Com preferencia salva, quem decide e o servidor (classe no <html>). --}}
        @if ($appearance === 'system')
            <script>
                (function () {
                    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        document.documentElement.classList.add('dark');
                    }
                })();
            </script>
        @endif

        {{-- Cor do papel antes do CSS carregar (mesmos tokens de app.css). --}}
        <style>
            html { background-color: #fafafb; }
            html.dark { background-color: #0b0c0f; }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <meta name="theme-color" content="#fafafb" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0b0c0f" media="(prefers-color-scheme: dark)">

        @if ($noindex)
            <meta name="robots" content="noindex, follow">
            <link rel="canonical" href="{{ $canonical }}">
        @else
            @foreach ($alternates as $hreflang => $url)
                <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $url }}">
            @endforeach

            @if ($alternates !== [])
                <link rel="alternate" hreflang="x-default" href="{{ $alternates[Locales::html(Locales::fallback())] ?? reset($alternates) }}">
            @endif

            <link rel="canonical" href="{{ $canonical }}">

            <meta property="og:type" content="{{ $og['type'] }}">
            <meta property="og:site_name" content="{{ $og['siteName'] }}">
            <meta property="og:locale" content="{{ $og['locale'] }}">
            @foreach ($og['alternates'] as $alternate)
                <meta property="og:locale:alternate" content="{{ $alternate }}">
            @endforeach
            <meta property="og:url" content="{{ $canonical }}">
            <meta property="og:image" content="{{ $og['image']['url'] }}">
            <meta property="og:image:width" content="{{ $og['image']['width'] }}">
            <meta property="og:image:height" content="{{ $og['image']['height'] }}">
            <meta property="og:image:alt" content="{{ $og['image']['alt'] }}">
            <meta name="twitter:card" content="summary_large_image">
        @endif

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'devtools') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
