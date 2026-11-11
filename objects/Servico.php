<?php
declare(strict_types=1);
// Classe da entidade Serviço (tabela servicos) — modelo CRUD em POO (atividade 06)
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }

class Servico {
    private PDO $conn;
    private string $table_name = 'servicos';
    private int $barbearia_id;

    public function __construct(PDO $db, int $barbeariaId) {
        $this->conn = $db;
        $this->barbearia_id = $barbeariaId;
    }

    /** Serviços ativos da barbearia, por nome. */
    public function read(): array {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} WHERE barbearia_id = ? AND ativo = 1 ORDER BY nome");
        $stmt->execute([$this->barbearia_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function readOne(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$id, $this->barbearia_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @param array{nome:string, duracao_minutos:int, preco:float, descricao:string} $d */
    public function create(array $d): int {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table_name} (barbearia_id, nome, duracao_minutos, preco, descricao) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$this->barbearia_id, $d['nome'], $d['duracao_minutos'], $d['preco'], $d['descricao']]);
        return (int)$this->conn->lastInsertId();
    }

    /** Atualiza e volta a ativar (igual ao comportamento original). */
    public function update(int $id, array $d): bool {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET nome = ?, duracao_minutos = ?, preco = ?, descricao = ?, ativo = 1 WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$d['nome'], $d['duracao_minutos'], $d['preco'], $d['descricao'], $id, $this->barbearia_id]);
        return $stmt->rowCount() > 0 || $this->readOne($id) !== null;
    }

    /** "Apagar" = desativar (as marcações antigas continuam a referir o serviço). */
    public function delete(int $id): bool {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET ativo = 0 WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$id, $this->barbearia_id]);
        return $stmt->rowCount() > 0;
    }
}
