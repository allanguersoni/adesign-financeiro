<?php
/**
 * app/config/env.php
 * Carrega variáveis de ambiente de um arquivo .env na raiz do projeto
 */

$env_path_prod = __DIR__ . '/../.env';
$env_path_dev  = __DIR__ . '/../../.env';

// Pega o primeiro que existir
$env_file = file_exists($env_path_prod) ? $env_path_prod : (file_exists($env_path_dev) ? $env_path_dev : null);

if ($env_file) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0) continue; // Ignora comentários
        
        list($name, $value) = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);
        
        // Remove aspas simples e duplas do valor
        $value = trim($value, "\"'");
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Helper rápido para pegar variáveis
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }
}
