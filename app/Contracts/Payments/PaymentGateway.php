<?php

namespace App\Contracts\Payments;

use App\Support\Payments\CheckoutRequest;
use App\Support\Payments\GatewayAuthorizedPayment;
use App\Support\Payments\GatewayPayment;
use App\Support\Payments\GatewayPixCharge;
use App\Support\Payments\GatewayPreapproval;
use App\Support\Payments\GatewayPreference;
use App\Support\Payments\PixChargeRequest;
use App\Support\Payments\PreapprovalRequest;

/**
 * O que o dominio precisa de um provedor de pagamento (F6).
 *
 * O contrato fica no vocabulario do app - sessao, periodo, doacao -, nao no do
 * provedor: trocar de gateway e escrever outra implementacao, nao reescrever
 * as actions. Nenhum metodo devolve o corpo cru da resposta.
 */
interface PaymentGateway
{
    /**
     * Ha credenciais para falar com o provedor? Sem elas, a tela esconde o
     * checkout online e oferece o atendimento manual.
     */
    public function isAvailable(): bool;

    /**
     * Cria a preferencia do Checkout Pro (pix, cartao ou boleto).
     */
    public function createPreference(CheckoutRequest $request): GatewayPreference;

    /**
     * Cria a preapproval da renovacao automatica no cartao.
     */
    public function createPreapproval(PreapprovalRequest $request): GatewayPreapproval;

    /**
     * Cria uma cobranca Pix avulsa para doacao.
     */
    public function createPixDonation(PixChargeRequest $request): GatewayPixCharge;

    /**
     * Busca o pagamento no provedor - nunca confia no corpo do webhook.
     */
    public function payment(string $id): GatewayPayment;

    /**
     * Busca a preapproval no provedor.
     */
    public function preapproval(string $id): GatewayPreapproval;

    /**
     * Busca a cobranca de uma preapproval (renovacao).
     */
    public function authorizedPayment(string $id): GatewayAuthorizedPayment;

    /**
     * Estorna integralmente o pagamento.
     */
    public function refund(string $paymentId): void;

    /**
     * Cancela a preapproval para interromper as proximas renovacoes.
     */
    public function cancelPreapproval(string $preapprovalId): void;
}
