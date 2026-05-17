<?php
require_once 'db_config.php';
require_once 'pokeapi_to_db.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: application/json');

try {
    // 1. まだ図鑑にいないIDを1つ決める
    $sql_find = "SELECT n AS missing_id FROM (
                    SELECT a.N + b.N * 10 + c.N * 100 + 1 AS n
                    FROM
                        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                        UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a,
                        (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                        UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b,
                        (SELECT 0 AS N UNION SELECT 1) c
                ) nums
                WHERE n <= 151
                AND n NOT IN (SELECT id FROM pokemons)
                ORDER BY RAND()
                LIMIT 1";

    $stmt = $pdo->query($sql_find);
    $res = $stmt->fetch();

    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'すべてのポケモンを集めました！']);
        exit;
    }

    $id = $res['missing_id'];

    // 2. 関数を呼び出してAPI取得＆DB保存
    $name = fetchAndSavePokemonById($pdo, $id, $type_id_map);

    if ($name) {
        echo json_encode([
            'success' => true,
            'id' => $id,
            'name' => $name
        ]);
    } else {
        throw new Exception("データの保存に失敗しました。");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
