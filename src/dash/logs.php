<?php
// Últimas linhas do docker logs do ml-jrf, com as cores ANSI viradas classes do Tailwind.

function dash_ansi_html(string $texto): string
{
    $cores = [
        '30' => 'text-gray-500', '31' => 'text-red-400', '32' => 'text-green-400',
        '33' => 'text-yellow-400', '34' => 'text-blue-400', '35' => 'text-purple-400',
        '36' => 'text-cyan-400', '37' => 'text-gray-100', '90' => 'text-gray-400',
        '91' => 'text-red-300', '92' => 'text-green-300', '93' => 'text-yellow-300',
        '94' => 'text-blue-300', '95' => 'text-purple-300', '96' => 'text-cyan-300',
        '97' => 'text-white',
    ];
    $texto = htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $texto = preg_replace('/\x1b\[0m/', '</span>', $texto);
    return preg_replace_callback('/\x1b\[(\d+)(;\d+)?m/', fn($m) => '<span class="' . ($cores[$m[1]] ?? 'text-gray-300') . '">', $texto);
}

function dash_logs(int $linhas = 300): string
{
    $saida = shell_exec('docker logs --tail ' . $linhas . ' ml-jrf 2>&1');
    return $saida === null ? 'Erro ao obter logs...' : dash_ansi_html($saida);
}
