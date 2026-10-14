@php
    use App\Support\Locales;

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
            <link rel="canonical" href="{{ url()->current() }}">
        @else
            @foreach ($alternates as $hreflang => $url)
                <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $url }}">
            @endforeach

            @if ($alternates !== [])
                <link rel="alternate" hreflang="x-default" href="{{ $alternates[Locales::html(Locales::fallback())] ?? reset($alternates) }}">
                <link rel="canonical" href="{{ $alternates[$localeConfig['html']] ?? url()->current() }}">
            @endif
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
