<?php
declare(strict_types=1);

/**
 * Quando é que um código de confirmação (marcação, senha) pode aparecer no ecrã em vez de só ir por email.
 * Partilhado pela API (api/v1) e pela gestão (gestao/). Opções em config/credenciais.php:
 *  - 'api_dev_devolver_codigo' => true    → sempre (desenvolvimento local);
 *  - 'api_codigo_se_email_falhar' => true → só quando o email falhou (servidor da UA: não deixa sair pedidos
 *    para o Brevo, por isso o email nunca chega). Em produção ficam as duas desligadas.
 */
function deveDevolverCodigo(array $cred, bool $emailEnviado): bool {
    if (($cred['api_dev_devolver_codigo'] ?? false) === true) return true;
    return !$emailEnviado && ($cred['api_codigo_se_email_falhar'] ?? false) === true;
}

/** O mesmo, lendo as credenciais (para a gestão, que não usa o _lib.php da API). */
function mostrarCodigoNoEcra(bool $emailEnviado): bool {
    static $cred = null;
    if ($cred === null) $cred = require __DIR__ . '/credenciais.php';
    return deveDevolverCodigo(is_array($cred) ? $cred : [], $emailEnviado);
}
