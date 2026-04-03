<?php
/**
 * app/config/settings.php
 * =======================
 * Helper global para leitura e gravação de configurações
 * armazenadas na tabela `configuracoes`.
 *
 * Cache em $_SESSION para evitar queries repetidas na mesma requisição.
 *
 * Funções disponíveis:
 *   setting(string $chave, $default)   → lê config com cache de sessão
 *   save_setting(string $chave, $valor) → INSERT/UPDATE via ON DUPLICATE KEY
 *   flush_settings_cache()             → invalida todo o cache de sessão
 */

/**
 * Lê uma configuração do banco com cache automático em sessão.
 *
 * @param  string $chave   Chave da configuração (coluna `chave`)
 * @param  mixed  $default Valor retornado se a chave não existir
 * @return mixed
 */
function setting(string $chave, mixed $default = null): mixed
{
    // Garante que o array de cache exista
    if (!isset($_SESSION['_settings_cache'])) {
        $_SESSION['_settings_cache'] = [];
    }

    // Retorna do cache se já foi buscado antes
    if (array_key_exists($chave, $_SESSION['_settings_cache'])) {
        return $_SESSION['_settings_cache'][$chave];
    }

    // Busca no banco
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1"
        );
        $stmt->execute([$chave]);
        $row   = $stmt->fetch();
        $valor = ($row !== false) ? $row['valor'] : $default;
    } catch (PDOException) {
        // Tabela ainda não existe (antes da migration) ou outro erro
        $valor = $default;
    }

    // Armazena no cache
    $_SESSION['_settings_cache'][$chave] = $valor;
    return $valor;
}

/**
 * Grava ou atualiza uma configuração no banco.
 * Invalida o cache da chave afetada automaticamente.
 *
 * @param  string      $chave Chave da configuração
 * @param  string|null $valor Novo valor (null apaga o valor)
 * @return void
 */
function save_setting(string $chave, ?string $valor): void
{
    global $pdo;

    $pdo->prepare("
        INSERT INTO configuracoes (chave, valor)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
    ")->execute([$chave, $valor]);

    // Atualiza o cache imediatamente para consistência na mesma requisição
    if (isset($_SESSION['_settings_cache'])) {
        $_SESSION['_settings_cache'][$chave] = $valor;
    }
}

/**
 * Invalida todo o cache de configurações da sessão.
 * Útil após salvar múltiplos valores de uma vez.
 */
function flush_settings_cache(): void
{
    unset($_SESSION['_settings_cache']);
}
