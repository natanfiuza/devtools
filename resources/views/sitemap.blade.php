{{--
    Sitemap XML (docs/SPEC.md secao 7).

    As entradas chegam prontas de App\Support\Sitemap; aqui so o formato.

    A declaracao XML precisa sair por aqui: escrita direto no arquivo, o `<?`
    abre o PHP do Blade.
--}}
{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@foreach ($url['alternates'] as $hreflang => $href)
        <xhtml:link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}" />
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $url['xdefault'] }}" />
    </url>
@endforeach
</urlset>
