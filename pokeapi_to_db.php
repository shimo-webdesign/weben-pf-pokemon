<?php
header("Content-Type: text/plain; charset=UTF-8");

// DB接続 db_config.php を読み込み
require_once 'db_config.php';

// DBからタイプ表を取得して、連想配列に変換する
$stmt_types = $pdo->query("SELECT en_type, id FROM types");
$type_id_map = $stmt_types->fetchAll(PDO::FETCH_KEY_PAIR);

// 分類名からIDを取得（なければ登録）する関数
function getGenusId($pdo, $genus_jp, $genus_en)
{
    if (empty($genus_jp)) return null;

    // すでにDBに存在するか確認
    $stmt_genus = $pdo->prepare("SELECT id FROM genera WHERE jp_genus = ?");
    $stmt_genus->execute([$genus_jp]);
    $id = $stmt_genus->fetchColumn();

    if ($id) {
        return $id; // 既存のIDを返す
    } else {
        // 新しく登録
        $stmt_genus = $pdo->prepare("INSERT INTO genera (en_genus, jp_genus) VALUES (?, ?)");
        $stmt_genus->execute([$genus_en, $genus_jp]);
        return $pdo->lastInsertId(); // 新しく発行されたIDを返す
    }
}

// 特性名からIDを取得（なければ登録）する関数
function getAbilityId($pdo, $ability_data)
{
    $en_name = $ability_data['ability']['name'];
    $url = $ability_data['ability']['url'];

    // すでにDBに存在するか確認（英語名で検索してIDを取得）
    $stmt_ability  = $pdo->prepare("SELECT id FROM abilities WHERE en_ability = ?");
    $stmt_ability->execute([$en_name]);
    $id = $stmt_ability->fetchColumn();

    if ($id) {
        return $id; // 登録済みならそのIDを返す
    } else {
        // DBになければAPIを叩いて日本語名を取得
        $res_json = @file_get_contents($url);
        if ($res_json === false) return null;

        $res = json_decode($res_json, true);
        $jp_name = $en_name; // 見つからなかった時の予備

        foreach ($res['names'] as $name_entry) {
            if ($name_entry['language']['name'] == 'ja') {
                $jp_name = $name_entry['name'];
                break;
            }
        }

        // DBに新規保存
        $insert = $pdo->prepare("INSERT INTO abilities (en_ability, jp_ability) VALUES (?, ?)");
        $insert->execute([$en_name, $jp_name]);

        return $pdo->lastInsertId(); // 新しく発行されたIDを返す
    }
}

function fetchAndSavePokemonById($pdo, $id, $type_id_map)
{

    // for ($i = 114; $i <= 151; $i++) {

    /* ポケモン基本情報の取得 ----------------------------- */
    $api_url = "https://pokeapi.co/api/v2/pokemon/{$id}";
    $response = @file_get_contents($api_url);

    if ($response === false) {
        // APIが取得できなかった場合の処理
        throw new Exception("API(pokemon)の取得に失敗しました。ID: {$id}");
        // die("データの取得に失敗しました。URLを確認してください。");
        // continue;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // JSONのパースに失敗した場合
        die("JSONの解析に失敗しました。");
        // continue;
    }

    // No.、画像URL、重さ、高さのデータをセット
    $id = $data['id'];
    // $image = $data['sprites']['front_default'];
    $height = $data['height'];
    $weight = $data['weight'];

    /* ポケモン拡張情報の取得 ----------------------------- */
    $api_url_species = "https://pokeapi.co/api/v2/pokemon-species/{$id}";
    $response_species = @file_get_contents($api_url_species);

    if ($response_species === false) {
        // APIが取得できなかった場合の処理
        throw new Exception("API(species)の取得に失敗しました。ID: {$id}");
        // die("データの取得に失敗しました。URLを確認してください。");
        // continue;
    }
    $data_species = json_decode($response_species, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // JSONのパースに失敗した場合
        die("JSONの解析に失敗しました。");
        // continue;
    }

    /* 名前 ----------------------------- */
    $japanese_name = ""; // 初期値を設定
    foreach ($data_species['names'] as $name_data) {
        // 言語が日本語(ja)のものを探す
        if ($name_data['language']['name'] == 'ja') {
            $japanese_name = $name_data['name']; // 名前（日本語）のデータをセット
            break;
        }
    }

    /* 分類 ----------------------------- */
    $genus_jp = "";
    $genus_en = ""; // 英語名用の変数を追加
    if (isset($data_species['genera'])) {
        foreach ($data_species['genera'] as $genus_data) {
            // 日本語名を取得
            if ($genus_data['language']['name'] == 'ja') {
                $genus_jp = $genus_data['genus'];
            }
            // 英語名を取得
            if ($genus_data['language']['name'] == 'en') {
                $genus_en = $genus_data['genus'];
            }
        }
    }
    // 日本語名と英語名の両方を渡す
    $genus_id = getGenusId($pdo, $genus_jp, $genus_en);

    /* タイプ ----------------------------- */
    $type1_id = isset($data['types'][0]) ? ($type_id_map[$data['types'][0]['type']['name']] ?? null) : null;
    $type2_id = isset($data['types'][1]) ? ($type_id_map[$data['types'][1]['type']['name']] ?? null) : null;

    /* 特性 ----------------------------- */
    // $data['abilities'] の最初の要素を使ってIDを取得
    $ability_id = null;
    if (isset($data['abilities'][0])) {
        $ability_id = getAbilityId($pdo, $data['abilities'][0]);
    }

    /* 説明 ----------------------------- */
    $desc_ja = "";
    $desc_en = "";

    if (isset($data_species['flavor_text_entries'])) {
        foreach ($data_species['flavor_text_entries'] as $entry) {
            $lang = $entry['language']['name'];
            // 改行やタブをスペースに変換
            $text = str_replace(["\n", "\r", "\f", "\t"], ' ', $entry['flavor_text']);

            if ($lang == 'ja' && empty($desc_ja)) {
                $desc_ja = $text;
            } elseif ($lang == 'en' && empty($desc_en)) {
                $desc_en = $text;
            }

            // 両方取得できたら効率化のためループを抜ける
            if (!empty($desc_ja) && !empty($desc_en)) break;
        }
    }

    /* データの保存 ----------------------------- */
    // 親テーブル（pokemons）の保存
    $sql_pokemons = "INSERT INTO pokemons (id, name, genus_id, type1_id, type2_id, height, weight, ability_id)
            VALUES (:id, :name, :genus_id, :type1_id, :type2_id, :height, :weight, :ability_id)
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                genus_id = VALUES(genus_id),
                type1_id = VALUES(type1_id),
                type2_id = VALUES(type2_id),
                height = VALUES(height), 
                weight = VALUES(weight),
                ability_id = VALUES(ability_id)";
    $stmt_pokemons = $pdo->prepare($sql_pokemons);
    $stmt_pokemons->execute([
        ':id'           => $id,
        ':name'         => $japanese_name,
        ':genus_id'     => $genus_id,
        ':type1_id'     => $type1_id,
        ':type2_id'     => $type2_id,
        ':height'       => $height,
        ':weight'       => $weight,
        ':ability_id'   => $ability_id
    ]);

    // 子テーブル（images）の保存
    $sql_images = "INSERT INTO images (id, front_url, back_url, dream_url) 
                   VALUES (:id, :front_url, :back_url, :dream_url)
                   ON DUPLICATE KEY UPDATE 
                        front_url = VALUES(front_url),
                        back_url = VALUES(back_url),
                        dream_url = VALUES(dream_url)";
    $stmt_images = $pdo->prepare($sql_images);
    $stmt_images->execute([
        ':id'   => $id,
        ':front_url'    => $data['sprites']['front_default'],
        ':back_url'     => $data['sprites']['back_default'],
        ':dream_url'    => $data['sprites']['other']['dream_world']['front_default']
    ]);

    $stmt = $pdo->prepare("INSERT INTO images (id, front_url, back_url, dream_url) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                front_url=VALUES(front_url),
                back_url=VALUES(back_url),
                dream_url=VALUES(dream_url)");
    $stmt->execute([$id, $data['sprites']['front_default'], $data['sprites']['back_default'], $data['sprites']['other']['dream_world']['front_default']]);

    // 子テーブル（descriptions）の保存
    $sql_descriptions = "INSERT INTO descriptions (id, en_text, jp_text) 
                   VALUES (:id, :en_text, :jp_text)
                   ON DUPLICATE KEY UPDATE 
                        en_text = VALUES(en_text),
                        jp_text = VALUES(jp_text)";
    $stmt_descriptions = $pdo->prepare($sql_descriptions);
    $stmt_descriptions->execute([
        ':id'   => $id,
        ':en_text'  => $desc_en,
        ':jp_text'  => $desc_ja
    ]);

    // 子テーブル（status）の保存
    $sql_status = "INSERT INTO status (id, hp, attack, defense, special_attack, special_defense, speed) 
                   VALUES (:id, :hp, :atk, :def, :sp_atk, :sp_def, :spd)
                   ON DUPLICATE KEY UPDATE
                        hp = VALUES(hp),
                        attack = VALUES(attack),
                        defense = VALUES(defense),
                        special_attack = VALUES(special_attack),
                        special_defense = VALUES(special_defense),
                        speed = VALUES(speed)";
    $stmt_status = $pdo->prepare($sql_status);
    $stmt_status->execute([
        ':id'     => $id,
        ':hp'     => $data['stats'][0]['base_stat'],
        ':atk'    => $data['stats'][1]['base_stat'],
        ':def'    => $data['stats'][2]['base_stat'],
        ':sp_atk' => $data['stats'][3]['base_stat'],
        ':sp_def' => $data['stats'][4]['base_stat'],
        ':spd'    => $data['stats'][5]['base_stat']
    ]);

    // echo "ID: {$id} {$japanese_name} のデータを保存しました。\n"; // 進捗を表示
    return $japanese_name;
    usleep(200000); // 0.2秒待機（サーバーへの負荷軽減）
    // }
}
