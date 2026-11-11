<?php
declare(strict_types=1);

/**
 * Helper de movimentos de stock (auditoria).
 * Regista quem mexeu em quê, quando e porquê.
 *
 * tipos: 'entrada' | 'transferencia' | 'consumo' | 'perda' | 'ajuste'
 *   - entrada:        entrada de stock geral (compra / reposição)
 *   - transferencia:  stock geral -> bancada de um barbeiro
 *   - consumo:        gasto ao realizar um serviço (futuro)
 *   - perda:          quebra/danificação
 *   - ajuste:         correção manual de stock
 */

function registarMovimento(
    PDO $pdo,
    int $barbeariaId,
    int $materialId,
    ?int $bancadaId,
    int $utilizadorId,
    string $tipo,
    float $quantidade,
    ?string $motivo = null
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO movimentos_stock (barbearia_id, material_id, bancada_id, utilizador_id, tipo, quantidade, motivo)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$barbeariaId, $materialId, $bancadaId, $utilizadorId, $tipo, $quantidade, $motivo]);
}
