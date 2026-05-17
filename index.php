<?php
require_once 'db_config.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    // JOINを使って、IDに対応する日本語名を各マスタテーブルから取得する
    $sql = "SELECT 
                p.id, 
                p.name, 
                p.height,
                p.weight,
                g.jp_genus, 
                t1.jp_type AS type1,
                t1.color_code AS color1, 
                t2.jp_type AS type2, 
                t2.color_code AS color2,
                a.jp_ability,
                i.front_url,
                i.back_url,
                i.dream_url,
                MAX(d.jp_text) AS jp_text,
                -- start_status
                s.hp,
                s.attack,
                s.defense,
                s.special_attack,
                s.special_defense,
                s.speed
                -- end_status

            FROM pokemons p
            LEFT JOIN genera    g  ON p.genus_id = g.id
            LEFT JOIN types     t1 ON p.type1_id = t1.id
            LEFT JOIN types     t2 ON p.type2_id = t2.id
            LEFT JOIN abilities a  ON p.ability_id = a.id
            LEFT JOIN images    i  ON p.id = i.id
            LEFT JOIN descriptions d  ON p.id = d.id
            LEFT JOIN status        s  ON p.id = s.id
            GROUP BY p.id
            ORDER BY p.id ASC";

    $stmt = $pdo->query($sql);
    $pokemons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("エラーが発生しました: ");
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ポケモンびより</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="reset.css">
    <link rel="stylesheet" href="style.css">
    <link rel="dns-prefetch" href="https://raw.githubusercontent.com">
    <script src="script.js" defer></script>
</head>

<body>
    <!-- ヘッダー -->
    <header class="header">
        <div class="header-container">
            <!-- 左側 -->
            <div class="turn-container">
                <button id="turn-all-button" class="turn-btn">おいで〜</button>
            </div>
            <!-- タイトル -->
            <div class="header-title-container">
                <h1 class="header-title">ポケモンびより</h1>
                <span class="title-count"><?= count($pokemons); ?> / 151</span>
            </div>
            <!-- 右側 -->
            <div class="header-right">
                <!-- ソート -->
                <div class="sort-container">
                    <div class="custom-select" id="custom-select">
                        <div class="custom-select__trigger" aria-label="表示順の並び替え">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="none" stroke="#555" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M7 18V6M7 6L3 10M7 6l4 4" />
                                <path d="M18 6v12M18 18l-4-4M18 18l4-4" />
                            </svg>
                        </div>
                        <ul class="custom-select__dropdown">
                            <li class="custom-select__option" data-value="id-asc">No. 順</li>
                            <li class="custom-select__option" data-value="name-asc">50音 順</li>
                            <li class="custom-select__option" data-value="height-desc">高さ 順</li>
                            <li class="custom-select__option" data-value="weight-desc">重さ 順</li>
                            <li class="custom-select__option" data-value="hp-desc">HP 順</li>
                            <li class="custom-select__option" data-value="attack-desc">こうげき 順</li>
                            <li class="custom-select__option" data-value="defense-desc">ぼうぎょ 順</li>
                            <li class="custom-select__option" data-value="special_attack-desc">とくこう 順</li>
                            <li class="custom-select__option" data-value="special_defense-desc">とくぼう 順</li>
                            <li class="custom-select__option" data-value="speed-desc">すばやさ 順</li>
                        </ul>
                    </div>
                </div>
                <!-- フィルター -->
                <button id="filter-btn" class="filter-btn" aria-label="絞り込み">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <span id="filter-badge" class="filter-badge" style="display:none;"></span>
                </button>
            </div>
        </div>
    </header>
    <!-- main -->
    <main class="main">
        <!-- モンスター一覧 -->
        <div class="container">
            <?php foreach ($pokemons as $pokemon): ?>
                <?php
                // JSに渡すための安全なJSON変換
                $safe_json = htmlspecialchars(json_encode($pokemon, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
                ?>
                <div class="card" data-id="<?php echo $pokemon['id']; ?>" onclick="openModal(<?php echo $safe_json; ?>)">
                    <!-- No. -->
                    <div class="id">No.<?php echo str_pad($pokemon['id'], 4, '0', STR_PAD_LEFT); ?></div>
                    <!-- 画像：前と後ろを両方出力 -->
                    <div class="img-container">
                        <img src="images/pokemon/front/<?php echo $pokemon['id']; ?>.png" class="img-front" alt="<?php echo htmlspecialchars($pokemon['name']); ?>" loading="lazy">
                        <img src="images/pokemon/back/<?php echo $pokemon['id']; ?>.png" class="img-back" alt="<?php echo htmlspecialchars($pokemon['name']); ?>" loading="lazy">
                        <!-- <img src="<?php echo htmlspecialchars($pokemon['front_url']); ?>" class="img-front" alt="<?php echo htmlspecialchars($pokemon['name']); ?>" loading="lazy"> 
                    <img src="<?php echo htmlspecialchars($pokemon['back_url']); ?>" class="img-back" alt="<?php echo htmlspecialchars($pokemon['name']); ?>" loading="lazy">-->
                    </div>
                    <!-- 名前 -->
                    <div class="name"><?php echo htmlspecialchars($pokemon['name']); ?></div>
                    <!-- 分類 -->
                    <div class="genus"><?php echo htmlspecialchars($pokemon['jp_genus']); ?></div>
                    <!-- タイプ -->
                    <div class="type-container">
                        <!-- タイプ1の表示 -->
                        <span class="type" style="background-color: #<?php echo $pokemon['color1'] ?? 'A8A878'; ?>;">
                            <?php echo htmlspecialchars($pokemon['type1']); ?>
                        </span>
                        <!-- タイプ2がある場合のみ表示 -->
                        <?php if ($pokemon['type2']): ?>
                            <span class="type" style="background-color: #<?php echo $pokemon['color2'] ?? 'A8A878'; ?>;">
                                <?php echo htmlspecialchars($pokemon['type2']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <!-- 特性 -->
                    <div class="ability">
                        特性 : <?php echo htmlspecialchars($pokemon['jp_ability'] ?? '---'); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- モンスター詳細のモーダル -->
    <div id="pokemonModal" class="modal" onclick="closeModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <span class="close-button" onclick="closeModal()">&times;</span>

            <div id="modal-body" class="modal-body">
                <!-- No. -->
                <div id="modal-id" class="id modal-id"></div>
                <!-- dream画像 -->
                <div class="modal-img-container">
                    <img id="modal-img" src="" alt="">
                </div>
                <!-- 名前 -->
                <div class="modal-name-container">
                    <div></div>
                    <div class="modal-name-inner">
                        <h2 id="modal-name" class="modal-name"></h2>
                        <input type="text" id="modal-name-input" class="modal-name-edit" maxlength="6">
                    </div>
                    <button id="edit-name-btn" class="edit-name-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                        </svg>
                    </button>
                    <button id="save-name-btn" class="edit-name-btn" style="display:none;">保存</button>
                </div>
                <!-- 分類 -->
                <div id="modal-genus" class="genus modal-genus"></div>
                <!-- タイプ -->
                <div id="modal-types" class="type-container"></div>
                <!-- 高さ・重さ・特性 -->
                <div class="modal-size-container">
                    <div>
                        <div class="modal-size-head">高さ</div>
                        <div id="modal-height" class="modal-size-content"></div>
                    </div>
                    <div class="modal-size-line"></div>
                    <div>
                        <div class="modal-size-head">重さ</div>
                        <div id="modal-weight" class="modal-size-content"></div>
                    </div>
                    <div class="modal-size-line"></div>
                    <div>
                        <div class="modal-size-head">特性</div>
                        <div id="modal-ability" class="modal-size-content"></div>
                    </div>
                </div>
                <!-- 説明文 -->
                <div id="modal-description" class="modal-description"></div>
                <!-- ステータス（グラフ） -->
                <div class="stats-container">
                    <?php
                    $stats = [
                        'hp' => 'HP',
                        'attack' => 'こうげき',
                        'defense' => 'ぼうぎょ',
                        'special_attack' => 'とくこう',
                        'special_defense' => 'とくぼう',
                        'speed' => 'すばやさ',
                    ];
                    foreach ($stats as $key => $label): ?>
                        <div class="stats-box">
                            <span class="stats-label"><?php echo $label; ?></span>
                            <div class="stas-bar">
                                <div id="bar-<?php echo $key; ?>" ; class="stas-bar-inner"></div>
                            </div>
                            <span id="val-<?php echo $key; ?>" class="stas-num"></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- かえす -->
                <div class="modal-delete-container">
                    <button id="delete-btn" class="modal-delete-btn">野生にかえす</button>
                </div>
            </div>
        </div>
    </div>

    <!-- かえす確認のモーダル -->
    <div id="confirmModal" class="modal confirm-modal" onclick="closeConfirmModal()">
        <div class="modal-content confirm-content" onclick="event.stopPropagation()">
            <div class="modal-body">
                <div class="confirm-text-container">
                    <span id="confirm-message" class="confirm-text-name"></span><span class="confirm-text1">を</span>
                    <p class="confirm-text2">野生にかえしてあげますか？<br><span class="confirm-text-caution">（一覧から削除されます）</span></p>
                </div>
                <div class="confirm-buttons">
                    <button class="btn-cancel" onclick="closeConfirmModal()">やめる</button>
                    <button id="btn-execute-delete" class="btn-delete">かえす</button>
                </div>
            </div>
        </div>
    </div>

    <!-- モンスターボール -->
    <div id="monsterball-float" class="monsterball-float" onclick="catchNewPokemon()">
        <div class="monsterball-base">
            <div class="monsterball-center">
                <button class="monsterball-center-button"></button>
            </div>
        </div>
        <div class="monsterball-shadow"></div>
    </div>

    <!-- トースト通知 -->
    <div id="toast-container" class="toast-container"></div>

    <!-- フィルターモーダル -->
    <div id="filterModal" class="modal filter-modal" onclick="closeFilterModal()">
        <div class="modal-content filter-modal-content" onclick="event.stopPropagation()">
            <span class="close-button" onclick="closeFilterModal()">&times;</span>
            <div class="filter-modal-header">
                <h2 class="filter-modal-title">ポケモンを探す</h2>

            </div>
            <div class="filter-modal-body">
                <!-- フリーワード -->
                <div class="filter-freeword-wrap">
                    <div class="filter-section-title">
                        <span>フリーワード</span>
                    </div>
                    <div class="filter-freeword-input-wrap">
                        <input type="text" id="filter-freeword" class="filter-freeword-input"
                            placeholder="なまえ・せつめいで探す" maxlength="20" autocomplete="off">
                        <button class="filter-freeword-clear" id="filter-freeword-clear" style="display:none;" aria-label="クリア">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2.5"
                                stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- 分類 -->
                <div class="filter-section-title" data-section="genus">
                    <span>分類</span>
                    <span class="filter-section-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="8" viewBox="0 0 12 8">
                            <path fill="#888" d="M6 8L0 0h12z" />
                        </svg>
                    </span>
                </div>
                <div class="filter-chips-wrap" id="wrap-genus">
                    <div class="filter-chips" id="filter-genus">
                        <?php
                        // 修正後
                        $generaStmt = $pdo->query("SELECT jp_genus FROM genera ORDER BY sort_order ASC");
                        $genera = array_column($generaStmt->fetchAll(PDO::FETCH_ASSOC), 'jp_genus');
                        // $genera = array_unique(array_column($pokemons, 'jp_genus'));
                        // sort($genera);
                        foreach ($genera as $g): ?>
                            <button class="filter-chip" data-filter="genus" data-value="<?php echo htmlspecialchars($g); ?>">
                                <?php echo htmlspecialchars($g); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- タイプ -->
                <div class="filter-section-title" data-section="type">
                    <span>タイプ</span>
                    <span class="filter-section-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="8" viewBox="0 0 12 8">
                            <path fill="#888" d="M6 8L0 0h12z" />
                        </svg>
                    </span>
                </div>
                <div class="filter-chips-wrap" id="wrap-type">
                    <div class="filter-chips" id="filter-type">
                        <?php
                        $typeStmt = $pdo->query("SELECT jp_type, color_code FROM types ORDER BY sort_order ASC");
                        $types = [];
                        foreach ($typeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                            $types[$row['jp_type']] = $row['color_code'];
                        }
                        foreach ($types as $type => $color): ?>
                            <button class="filter-chip filter-chip--type"
                                data-filter="type"
                                data-value="<?php echo htmlspecialchars($type); ?>"
                                style="background-color:#<?php echo $color; ?>; border-color:#<?php echo $color; ?>;">
                                <?php echo htmlspecialchars($type); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- 特性 -->
                <div class="filter-section-title" data-section="ability">
                    <span>特性</span>
                    <span class="filter-section-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="8" viewBox="0 0 12 8">
                            <path fill="#888" d="M6 8L0 0h12z" />
                        </svg>
                    </span>
                </div>
                <div class="filter-chips-wrap" id="wrap-ability">
                    <div class="filter-chips" id="filter-ability">
                        <?php
                        // 修正後
                        $abilitiesStmt = $pdo->query("SELECT jp_ability FROM abilities WHERE jp_ability IS NOT NULL ORDER BY sort_order ASC");
                        $abilities = array_column($abilitiesStmt->fetchAll(PDO::FETCH_ASSOC), 'jp_ability');
                        // $abilities = array_unique(array_filter(array_column($pokemons, 'jp_ability')));
                        // sort($abilities);
                        foreach ($abilities as $ab): ?>
                            <button class="filter-chip" data-filter="ability" data-value="<?php echo htmlspecialchars($ab); ?>">
                                <?php echo htmlspecialchars($ab); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="filter-footer">
                <button class="filter-reset-btn" id="filter-reset-btn">リセット</button>
                <button class="filter-apply-btn" id="filter-apply-btn">
                    探 す
                    <span id="apply-badge" class="apply-badge" style="display:none;"></span>
                </button>
            </div>
        </div>
    </div>

</body>

</html>