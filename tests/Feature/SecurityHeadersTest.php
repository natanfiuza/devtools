<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Headers de seguranca e CSP (ADR-052).
|
| A politica e a mesma em toda resposta; o nonce muda a cada uma e e o mesmo
| que o Blade escreve no script inline do tema e nos tags do @vite. HSTS so
| sai em HTTPS. `report-only` e o rollback operacional; `off` desliga apenas o
| header da CSP, nunca os demais headers.
*/

beforeEach(function () {
    config([
        'security.csp.mode' => 'enforce',
        'security.force_https' => false,
        'security.hsts.max_age' => 31536000,
        'security.hsts.include_subdomains' => true,
    ]);
});

/** Nonce embutido no header da CSP. */
function securityNonce(string $header): string
{
    preg_match("/'nonce-([^']+)'/", $header, $matches);

    return $matches[1] ?? '';
}

it('emite a CSP em enforce com as diretivas que o site usa', function () {
    $response = $this->get('/pt-br')->assertOk();

    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self' 'nonce-")
        ->toContain('https://www.googletagmanager.com')
        ->toContain('https://challenges.cloudflare.com')
        ->toContain("style-src 'self' 'unsafe-inline'")
        ->toContain("img-src 'self' data:")
        ->toContain("font-src 'self' data:")
        ->toContain("connect-src 'self'")
        ->toContain('frame-src https://challenges.cloudflare.com')
        ->toContain("frame-ancestors 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("object-src 'none'");
});

it('usa o mesmo nonce no header e no script inline do tema', function () {
    $response = $this->get('/pt-br')->assertOk();

    $nonce = securityNonce((string) $response->headers->get('Content-Security-Policy'));

    expect($nonce)->not->toBe('')
        // O tema e aplicado antes do primeiro paint por um script inline; sem
        // o nonce ele seria bloqueado e a pagina piscaria no tema errado.
        ->and($response->getContent())->toContain('<script nonce="'.$nonce.'"');
});

it('gera um nonce diferente a cada resposta', function () {
    $first = securityNonce((string) $this->get('/pt-br')->headers->get('Content-Security-Policy'));
    $second = securityNonce((string) $this->get('/pt-br')->headers->get('Content-Security-Policy'));

    expect($first)->not->toBe('')
        ->and($second)->not->toBe('')
        ->and($first)->not->toBe($second);
});

it('separa o header que constrange o navegador dos demais', function () {
    $response = $this->get('/pt-br')->assertOk();

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Permissions-Policy'))->toBe('camera=(), microphone=(), geolocation=(), payment=()')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('so envia HSTS em requisicao HTTPS', function () {
    $plain = $this->get('/pt-br')->assertOk();
    $secure = $this->get('https://localhost/pt-br')->assertOk();

    expect($plain->headers->get('Strict-Transport-Security'))->toBeNull()
        ->and($secure->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains');
});

it('respeita o max-age e o includeSubDomains configurados', function () {
    config(['security.hsts.max_age' => 600, 'security.hsts.include_subdomains' => false]);

    expect($this->get('https://localhost/pt-br')->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=600');
});

it('nao envia HSTS quando o max-age esta desligado', function () {
    config(['security.hsts.max_age' => 0]);

    expect($this->get('https://localhost/pt-br')->headers->get('Strict-Transport-Security'))->toBeNull();
});

it('em report-only troca o header de enforce pelo de relatorio', function () {
    config(['security.csp.mode' => 'report-only']);

    $response = $this->get('/pt-br')->assertOk();

    expect($response->headers->get('Content-Security-Policy-Report-Only'))
        ->toContain("default-src 'self'")
        ->and($response->headers->get('Content-Security-Policy'))->toBeNull();
});

it('em off nao emite CSP mas mantem os demais headers', function () {
    config(['security.csp.mode' => 'off']);

    $response = $this->get('/pt-br')->assertOk();

    expect($response->headers->get('Content-Security-Policy'))->toBeNull()
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))->toBeNull()
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('forca https nas URLs geradas quando configurado', function () {
    config(['security.force_https' => true]);

    $html = $this->get('http://localhost/pt-br')->assertOk()->getContent();

    expect($html)
        ->toContain('<link rel="canonical" href="https://localhost/pt-br">')
        ->not->toContain('<link rel="canonical" href="http://localhost/pt-br">');
});
