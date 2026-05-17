<?php
require_once 'db_config.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: application/json');

// POSTデータが空でないかチェック
$id = isset($_POST['id']) ? $_POST['id'] : null;
$newName = isset($_POST['name']) ? trim($_POST['name']) : null;

// IDがない、または名前が空の場合はエラーを返す
if (!$id || $newName === "") {
    echo json_encode(['success' => false, 'error' => 'IDまたは名前が正しくありません。']);
    exit;
}

try {
    // 1. データベースの更新
    $sql = "UPDATE pokemons SET name = :name WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $newName,
        ':id'   => $id
    ]);

    // 2. 実際に更新されたか確認（IDが存在しない場合などの対策）
    if ($stmt->rowCount() >= 0) {
        // rowCountは「変更がない（同じ名前を保存した）」場合 0 になるため、>= 0 で判定
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => '更新に失敗しました。']);
    }
} catch (Exception $e) {
    // エラーが発生した場合は詳細を返す
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
