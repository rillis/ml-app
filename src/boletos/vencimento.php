<?php
// Vencimento efetivo: passa para o próximo dia útil se cair em fim de semana ou feriado.
// Recebe os feriados já carregados, para não consultar o banco a cada boleto.
function calcularVencimentoReal($dataBase, $feriados)
{
    $dt = new DateTime($dataBase);

    while (true) {
        $diaSemana = (int)$dt->format('w'); // 0 = Domingo, 6 = Sábado

        if ($diaSemana === 0 || $diaSemana === 6) {
            $dt->modify('+1 day');
            continue;
        }

        if (in_array($dt->format('Y-m-d'), $feriados)) {
            $dt->modify('+1 day');
            continue;
        }

        break;
    }

    return $dt->format('Y-m-d');
}
