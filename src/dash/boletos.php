<?php
//vencimento real e níveis de alerta
require_once __DIR__ . '/../boletos/vencimento.php';
require_once __DIR__ . '/../boletos/nivel.php';

//query feriados
$stmtFeriados = $pdo->query("SELECT data FROM ml_boletos.feriados");
$feriados = $stmtFeriados->fetchAll(PDO::FETCH_COLUMN);

//query boletos
$sqlBoletos = "
    SELECT data_vencimento, descricao, valor, agendado
    FROM ml_boletos.boletos
    WHERE pago = 0 AND deleted_at IS NULL
";
$boletosBrutos = $pdo->query($sqlBoletos)->fetchAll(PDO::FETCH_ASSOC);

//tratamento dos boletos (vencimento real, nível de alerta, filtro de <= 15 dias e formatação visual)
$boletos = [];
$hojeBoletos = boletoHoje(); // fuso da loja; o $today do posts.php está em UTC

foreach ($boletosBrutos as $bol) {
    $vencimentoReal = calcularVencimentoReal($bol['data_vencimento'], $feriados);
    $dias = boletoDias($vencimentoReal, $hojeBoletos);

    if ($dias <= 15) {
        $nivel = boletoNivel(false, (bool)$bol['agendado'], $dias);
        $estilo = BOLETO_NIVEIS[$nivel];

        $bol['data_vencimento_real'] = $vencimentoReal;
        $bol['date_fmt'] = (new DateTime($vencimentoReal))->format('d/m/Y');
        $bol['valor_fmt'] = number_format($bol['valor'], 2, ',', '.');
        $bol['status_class'] = $bol['agendado'] ? 'bg-blue-900 text-blue-300' : 'bg-gray-600 text-gray-200';
        $bol['status_text'] = $bol['agendado'] ? 'Agendado' : 'Não agendado';
        $bol['bg_color'] = $estilo['fundo'];
        $bol['border_class'] = $estilo['borda'];
        $bol['vencido_tag'] = '<span class="' . $estilo['selo'] . ' text-xs font-bold px-2 py-0.5 rounded-full" title="' . $estilo['rotulo'] . '">' . boletoPrazo($dias) . '</span>';

        $boletos[] = $bol;
    }
}

//order by data real
usort($boletos, function($a, $b) {
    return strcmp($a['data_vencimento_real'], $b['data_vencimento_real']);
});
?>
