<?php
declare(strict_types=1);
// Classe da entidade Bancada (tabelas bancadas e bancada_materiais) — modelo CRUD em POO (atividade 06)
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }

class Bancada {
    private PDO $conn;
    private string $table_name = 'bancadas';
    private int $barbearia_id;

    public function __construct(PDO $db, int $barbeariaId) {
        $this->conn = $db;
        $this->barbearia_id = $barbeariaId;
    }

    /** Todas as bancadas (ativas e inativas) com o nome do barbeiro. */
    public function read(): array {
        $stmt = $this->conn->prepare("
            SELECT b.*, u.nome AS barbeiro_nome
            FROM {$this->table_name} b
            LEFT JOIN utilizadores u ON b.barbeiro_id = u.id
            WHERE b.barbearia_id = ?
            ORDER BY b.id ASC
        ");
        $stmt->execute([$this->barbearia_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function readOne(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table_name} WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$id, $this->barbearia_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Cria a bancada; sem nome usa "Bancada N" (N = maior id + 1). */
    public function create(string $nome, ?int $barbeiroId): int {
        if ($nome === '') {
            $stmt = $this->conn->prepare("SELECT MAX(id) FROM {$this->table_name} WHERE barbearia_id = ?");
            $stmt->execute([$this->barbearia_id]);
            $nome = 'Bancada ' . (((int)$stmt->fetchColumn()) + 1);
        }
        $stmt = $this->conn->prepare("INSERT INTO {$this->table_name} (barbearia_id, nome, barbeiro_id) VALUES (?, ?, ?)");
        $stmt->execute([$this->barbearia_id, $nome, $barbeiroId]);
        return (int)$this->conn->lastInsertId();
    }

    /** Atualiza nome e barbeiro; um barbeiro só pode ter uma bancada (sai da anterior). */
    public function update(int $id, string $nome, ?int $barbeiroId): void {
        if ($barbeiroId) {
            $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET barbeiro_id = NULL WHERE barbeiro_id = ? AND barbearia_id = ? AND id != ?");
            $stmt->execute([$barbeiroId, $this->barbearia_id, $id]);
        }
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET nome = ?, barbeiro_id = ? WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$nome, $barbeiroId, $id, $this->barbearia_id]);
    }

    /** Ativar/desativar ("apagar" = desativar). */
    public function setAtiva(int $id, bool $ativa): bool {
        $stmt = $this->conn->prepare("UPDATE {$this->table_name} SET ativo = ? WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$ativa ? 1 : 0, $id, $this->barbearia_id]);
        return $this->readOne($id) !== null;
    }

    /** Barbeiros ativos sem bancada (mais o da bancada $incluirBancadaId, para o formulário de edição). */
    public function barbeirosLivres(int $incluirBancadaId = 0): array {
        $stmt = $this->conn->prepare("
            SELECT u.id, u.nome FROM utilizadores u
            LEFT JOIN {$this->table_name} b ON u.id = b.barbeiro_id AND b.ativo = 1
            WHERE u.barbearia_id = ? AND u.tipo = 'barbeiro' AND u.ativo = 1 AND (b.id IS NULL OR b.id = ?)
        ");
        $stmt->execute([$this->barbearia_id, $incluirBancadaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Materiais de cada bancada agrupados por tipo: [bancada_id][tipo][] */
    public function materiaisPorBancada(): array {
        $stmt = $this->conn->prepare("
            SELECT bm.bancada_id, bm.quantidade, bm.quantidade_minima, m.nome, m.unidade, m.tipo
            FROM bancada_materiais bm
            JOIN materiais m ON bm.material_id = m.id
            WHERE m.barbearia_id = ? AND m.ativo = 1
            ORDER BY m.nome
        ");
        $stmt->execute([$this->barbearia_id]);
        $porBancada = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $porBancada[(int)$r['bancada_id']][$r['tipo'] ?? 'material'][] = $r;
        }
        return $porBancada;
    }
}
