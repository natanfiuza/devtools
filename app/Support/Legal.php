<?php

namespace App\Support;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;

/**
 * Dados das paginas legais (privacidade e termos).
 *
 * O contato e a data de revisao vem de config/legal.php: trocar o e-mail ou
 * republicar o texto nao mexe no codigo, e o mesmo valor serve os 5 idiomas.
 * Os textos em si ficam em lang/{locale}/legal.php.
 */
final class Legal
{
    /**
     * Contato de privacidade. Sem LEGAL_CONTACT_EMAIL, cai no remetente dos
     * e-mails (config/mail.php).
     */
    public static function contactEmail(): ?string
    {
        $email = config('legal.contact_email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    /**
     * "Ultima atualizacao: <data>" no idioma da pagina - e nao no idioma do
     * config, nem no idioma de quem escreveu o texto.
     */
    public static function updatedLabel(): string
    {
        $label = trans('legal.updated', ['date' => self::updatedAt()->isoFormat('LL')]);

        return is_string($label) ? $label : '';
    }

    /**
     * Data da ultima revisao dos textos, ja no idioma da requisicao.
     */
    private static function updatedAt(): Carbon
    {
        $date = config('legal.updated_at');

        try {
            $revisao = Carbon::parse(is_string($date) && $date !== '' ? $date : 'now');
        } catch (InvalidFormatException) {
            // Data ausente ou invalida nao pode derrubar a pagina: cai para hoje.
            $revisao = Carbon::now();
        }

        // Solto de proposito: sem argumento, locale() e getter e devolve string
        // (a assinatura e static|string). O que interessa aqui e o efeito na
        // instancia - e ele muda o nome do mes em isoFormat().
        $revisao->locale(app()->getLocale());

        return $revisao;
    }
}
