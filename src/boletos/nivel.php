<?php
// Níveis de alerta dos boletos. A mesma regra está em alerta/main.py (aviso do Discord):
// mudou aqui, mude lá também.
//   critico  vencido (agendado ou não)
//   urgente  não agendado, vence em 0 a 3 dias
//   atencao  não agendado, vence em 4 a 7 dias; ou agendado e vence hoje
//   ok       o resto
//   pago     já pago (fica fora dos níveis)

const BOLETO_FUSO = 'America/Sao_Paulo';

// Data de hoje no fuso da loja (o PHP do container roda em UTC).
function boletoHoje(): string
{
    return (new DateTime('now', new DateTimeZone(BOLETO_FUSO)))->format('Y-m-d');
}

// Dias entre hoje e o vencimento real: negativo = vencido, 0 = hoje.
function boletoDias(string $vencimentoReal, string $hoje): int
{
    $a = new DateTime($hoje);
    $b = new DateTime($vencimentoReal);
    return (int)$a->diff($b)->format('%r%a');
}

function boletoNivel(bool $pago, bool $agendado, int $dias): string
{
    if ($pago) return 'pago';
    if ($dias < 0) return 'critico';
    if (!$agendado && $dias <= 3) return 'urgente';
    if (!$agendado && $dias <= 7) return 'atencao';
    if ($agendado && $dias === 0) return 'atencao';
    return 'ok';
}

// Texto curto do prazo: "venceu há 2d", "hoje", "amanhã", "em 5d".
function boletoPrazo(int $dias): string
{
    if ($dias < 0) return 'venceu há ' . abs($dias) . 'd';
    if ($dias === 0) return 'hoje';
    if ($dias === 1) return 'amanhã';
    return 'em ' . $dias . 'd';
}

// Classes Tailwind de cada nível (borda esquerda, fundo e selo). As cores batem com as do Discord.
const BOLETO_NIVEIS = [
    'critico' => ['rotulo' => 'Crítico', 'borda' => 'border-l-red-500',    'fundo' => 'bg-red-900/30',    'selo' => 'bg-red-500 text-white animate-pulse'],
    'urgente' => ['rotulo' => 'Urgente', 'borda' => 'border-l-orange-500', 'fundo' => 'bg-orange-900/25', 'selo' => 'bg-orange-500 text-white'],
    'atencao' => ['rotulo' => 'Atenção', 'borda' => 'border-l-yellow-500', 'fundo' => 'bg-yellow-900/20', 'selo' => 'bg-yellow-500 text-gray-900'],
    'ok'      => ['rotulo' => 'Ok',      'borda' => 'border-l-blue-500',   'fundo' => 'bg-gray-800',      'selo' => 'bg-blue-500/15 text-blue-300'],
    'pago'    => ['rotulo' => 'Pago',    'borda' => 'border-l-gray-600',   'fundo' => 'bg-gray-800',      'selo' => 'bg-gray-700 text-gray-400'],
];
