<?php
// Cores do terminal e formatação
function ansiToHtml($text) {
    $colors = [
        '30' => 'text-gray-500', '31' => 'text-red-400',   '32' => 'text-green-400', 
        '33' => 'text-yellow-400','34' => 'text-blue-400',  '35' => 'text-purple-400', 
        '36' => 'text-cyan-400',  '37' => 'text-gray-100',  '90' => 'text-gray-400',
        '91' => 'text-red-300',   '92' => 'text-green-300', '93' => 'text-yellow-300',
        '94' => 'text-blue-300',  '95' => 'text-purple-300','96' => 'text-cyan-300',
        '97' => 'text-white'
    ];

    $text = htmlspecialchars($text);
    $text = preg_replace('/\x1b\[0m/', '</span>', $text);
    $text = preg_replace_callback('/\x1b\[(\d+)(;\d+)?m/', function($matches) use ($colors) {
        $code = $matches[1];
        $class = $colors[$code] ?? 'text-gray-300';
        return "<span class=\"$class\">";
    }, $text);

    return $text;
}

$dockerLogs = shell_exec('docker logs --tail 500 ml-jrf 2>&1');

//getContent
function getLogsContent(){
    global $dockerLogs;

    if ($dockerLogs !== null) {
        $dockerLogs = ansiToHtml($dockerLogs);
    } else {
        $dockerLogs = "Erro ao obter logs...";
    }
    return $dockerLogs;
}

