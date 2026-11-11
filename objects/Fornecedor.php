<?php
declare(strict_types=1);
// Classe da entidade Fornecedor (tabela fornecedores) — modelo CRUD em POO (atividade 06)
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }

class Fornecedor {
    private PDO $conn;
    private string $table_name = 'fornecedores';
    private int $barbearia_id;

    public function __construct(PDO $db, int $barbeariaId) {
        $this->conn = $db;
        $this->barbearia_id = $barbeariaId;
    }

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

    /** @param array{nome:string, contacto:string, telefone:string, email:string, nif:string} $d */
    public function create(array $d): int {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table_name} (barbearia_id, nome, contacto, telefone, email, nif) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$this->barbearia_id, $d['nome'], $d['contacto'], $d['telefone'], $d['email'], $d['nif']]);
        return (int)$this->conn->lastInsertId();
    }

    public function update(int $id, array $d): bool {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET nome = ?, contacto = ?, telefone = ?, email = ?, nif = ? WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$d['nome'], $d['contacto'], $d['telefone'], $d['email'], $d['nif'], $id, $this->barbearia_id]);
        return $this->readOne($id) !== null;
    }
}
