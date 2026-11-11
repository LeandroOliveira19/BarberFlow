<?php
declare(strict_types=1);

/**
 * Lógica de reservas partilhável (API v1; o web pode passar a usá-la quando for retomado).
 * Extraída de publico/marcar.php e publico/verificar.php — mesmas regras e mensagens.
 * Sem I/O HTTP: os erros de negócio são lançados como ReservaErro.
 */

require_once __DIR__ . '/marcacoes.php';

final class ReservaErro extends RuntimeException {
    public function __construct(
        public readonly int $status,
        public readonly string $codigo,
        string $mensagem,
        public readonly array $extra = []
    ) {
        parent::__construct($mensagem);
    }
}

/** Valida "Y-m-d" dentro de [hoje, hoje + MAX_DIAS] (regra de validarEscolha em publico/marcar.php). */
function validarDataMarcacao(string $dt): DateTime {
    $dia = DateTime::createFromFormat('!Y-m-d', $dt);
    if (!$dia || $dia->format('Y-m-d') !== $dt) throw new ReservaErro(422, 'data_invalida', 'Escolhe uma data válida.');
    $hoje = new DateTime('today');
    $max  = (clone $hoje)->modify('+' . MAX_DIAS . ' days');
    if ($dia < $hoje || $dia > $max) {
        throw new ReservaErro(422, 'data_invalida', 'Só é possível marcar nos próximos ' . MAX_DIAS . ' dias.');
    }
    return $dia;
}

/** Serviço ativo da barbearia ou ReservaErro 404. */
function servicoDaBarbearia(PDO $pdo, int $bid, int $servicoId): array {
    $st = $pdo->prepare("SELECT id, nome, duracao_minutos, preco FROM servicos WHERE id = ? AND barbearia_id = ? AND ativo = 1");
    $st->execute([$servicoId, $bid]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    if (!$s) throw new ReservaErro(404, 'servico_nao_encontrado', 'Serviço não encontrado.');
    return $s;
}

/** Garante que o barbeiro é reservável nesta barbearia (mesma lista que o web mostra). */
function barbeiroDaBarbearia(PDO $pdo, int $bid, int $barbeiroId): array {
    foreach (barbeirosReservaveis($pdo, $bid) as $b) {
        if ((int)$b['id'] === $barbeiroId) return $b;
    }
    throw new ReservaErro(404, 'barbeiro_nao_encontrado', 'Barbeiro não encontrado.');
}
