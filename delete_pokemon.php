<?php
require_once 'db_config.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    try {
        $id = (int)$_POST['id'];
        // 外部キー制約がある場合を考慮し、関連データも削除するか、
        // データベース側で ON DELETE CASCADE を設定しておく
        $sql = "DELETE FROM pokemons WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
