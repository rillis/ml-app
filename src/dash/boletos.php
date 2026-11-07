<?php
//vencimento real
require_once __DIR__ . '/../boletos/vencimento.php';

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

//tratamento dos boletos (vencimento real, filtro de <= 15 dias e formatação visual)
$boletos = [];
$hojeObj = new DateTime($today ?? date('Y-m-d'));

foreach ($boletosBrutos as $bol) {
    $vencimentoReal = calcularVencimentoReal($bol['data_vencimento'], $feriados);
    $vencObj = new DateTime($vencimentoReal);
    
    $diff = $hojeObj->diff($vencObj);
    $dias = (int)$diff->format('%R%a');
    
    if ($dias <= 15) {
        $bol['data_vencimento_real'] = $vencimentoReal;
        $bol['date_fmt'] = $vencObj->format('d/m/Y');
        $bol['valor_fmt'] = number_format($bol['valor'], 2, ',', '.');
        $bol['status_class'] = $bol['agendado'] ? 'bg-blue-900 text-blue-300' : 'bg-yellow-900 text-yellow-300';
        $bol['status_text'] = $bol['agendado'] ? 'Agendado' : 'Pendente';
        $bol['bg_color'] = $bol['agendado'] ? 'bg-gray-900' : 'bg-gray-700';
        
        $dateCompare = $vencObj->format('Y-m-d');
        if (($today ?? date('Y-m-d')) >= $dateCompare) {
            $bol['border_class'] = 'border-red-500';
            $bol['vencido_tag'] = '<span class="bg-red-900 text-red-300 text-xs font-semibold px-2 py-0.5 rounded">Vencido</span>';
        } else {
            $bol['border_class'] = $bol['agendado'] ? 'border-blue-500' : 'border-yellow-500';
            $bol['vencido_tag'] = '';
        }

        $boletos[] = $bol;
    }
}

//order by data real
usort($boletos, function($a, $b) {
    return strcmp($a['data_vencimento_real'], $b['data_vencimento_real']);
});
?>