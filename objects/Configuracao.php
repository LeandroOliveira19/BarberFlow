<?php
declare(strict_types=1);
// Classe das definições da barbearia (tabelas configuracoes e barbearias) — modelo CRUD em POO (atividade 06)
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }

class Configuracao {
    private PDO $conn;
    private string $table_name = 'configuracoes';
    private int $barbearia_id;

    public function __construct(PDO $db, int $barbeariaId) {
        $this->conn = $db;
        $this->barbearia_id = $barbeariaId;
    }

    /** @return array<string,string> chave => valor */
    public function read(): array {
        $stmt = $this->conn->prepare("SELECT chave, valor FROM {$this->table_name} WHERE barbearia_id = ?");
        $stmt->execute([$this->barbearia_id]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Grava (cria ou atualiza) cada chave. */
    public function save(array $valores): void {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table_name} (barbearia_id, chave, valor) VALUES (?, ?, ?)
                                      ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        foreach ($valores as $chave => $valor) {
            $stmt->execute([$this->barbearia_id, (string)$chave, (string)$valor]);
        }
    }

    /** Nome e morada (tabela barbearias). */
    public function dadosBarbearia(): array {
        $stmt = $this->conn->prepare("SELECT nome, morada FROM barbearias WHERE id = ?");
        $stmt->execute([$this->barbearia_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['nome' => '', 'morada' => ''];
    }

    public function atualizarBarbearia(string $nome, string $morada): void {
        $stmt = $this->conn->prepare("UPDATE barbearias SET nome = ?, morada = ? WHERE id = ?");
        $stmt->execute([$nome, $morada, $this->barbearia_id]);
    }
}
