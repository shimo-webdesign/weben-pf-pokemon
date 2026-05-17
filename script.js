// ★ closeFilterModal から参照できるようグローバルスコープに宣言
let snapshot = null;
const selected = { genus: new Set(), type: new Set(), ability: new Set() };
let freewordInput;
let freewordClear;

/**
 * 一覧画面で前を向く、後ろを向く
 */
document.addEventListener('DOMContentLoaded', () => {

    // ふりむきボタン
    const turnBtn = document.getElementById('turn-all-button');

    turnBtn.addEventListener('click', () => {
        const currentCards = document.querySelectorAll('.card');
        if (currentCards.length === 0) return;

        const isTurned = currentCards[0].classList.toggle('is-turned');

        currentCards.forEach((card, index) => {
            if (index === 0) return;
            card.classList.toggle('is-turned', isTurned);
        });

        const isAnyTurned = Array.from(currentCards).some(card => card.classList.contains('is-turned'));
        turnBtn.textContent = isAnyTurned ? 'またね〜' : 'おいで〜';
    });

    // カスタムセレクト
    const customSelect = document.getElementById('custom-select');
    const label = document.getElementById('custom-select__label');
    const options = customSelect.querySelectorAll('.custom-select__option');

    let currentValue = sessionStorage.getItem('sort') || 'id-asc';

    options.forEach(o => {
        o.classList.toggle('is-selected', o.dataset.value === currentValue);
    });

    applySortOrder(currentValue);

    customSelect.querySelector('.custom-select__trigger').addEventListener('click', () => {
        customSelect.classList.toggle('is-open');
    });

    options.forEach(option => {
        option.addEventListener('click', () => {
            currentValue = option.dataset.value;
            if (label) label.textContent = option.textContent;

            options.forEach(o => o.classList.remove('is-selected'));
            option.classList.add('is-selected');

            customSelect.classList.remove('is-open');
            sessionStorage.setItem('sort', currentValue);
            applySortOrder(currentValue);
        });
    });

    document.addEventListener('click', (e) => {
        if (!customSelect.contains(e.target)) {
            customSelect.classList.remove('is-open');
        }
    });

    function applySortOrder(value) {
        const container = document.querySelector('.container');
        const cards = Array.from(container.querySelectorAll('.card'));

        const getData = (card) => {
            const raw = card.getAttribute('onclick')
                .replace(/^openModal\(/, '').replace(/\)$/, '');
            try { return JSON.parse(raw); } catch (e) { return {}; }
        };

        cards.sort((a, b) => {
            if (value === 'id-asc') return a.dataset.id - b.dataset.id;
            if (value === 'name-asc') return a.querySelector('.name').textContent.localeCompare(b.querySelector('.name').textContent, 'ja');

            const [key] = value.split('-');
            const dataA = getData(a);
            const dataB = getData(b);
            return (Number(dataB[key]) || 0) - (Number(dataA[key]) || 0);
        });

        cards.forEach(card => container.appendChild(card));
        container.classList.add('is-ready');
    }


    // フィルター機能
    // ★ グローバル宣言した変数をここで初期化
    freewordInput = document.getElementById('filter-freeword');
    freewordClear = document.getElementById('filter-freeword-clear');

    const filterBtn = document.getElementById('filter-btn');
    const filterModal = document.getElementById('filterModal');
    const badge = document.getElementById('filter-badge');
    const resetBtn = document.getElementById('filter-reset-btn');
    const applyBtn = document.getElementById('filter-apply-btn');

    // ★ セッションストレージから復元
    const savedFilter = sessionStorage.getItem('filter');
    if (savedFilter) {
        const parsed = JSON.parse(savedFilter);
        Object.keys(parsed).forEach(k => {
            if (k === '_freeword') return;
            parsed[k].forEach(v => selected[k].add(v));
        });
        // チップのis-selectedを反映
        document.querySelectorAll('.filter-chip').forEach(chip => {
            const category = chip.dataset.filter;
            const value = chip.dataset.value;
            if (selected[category] && selected[category].has(value)) {
                chip.classList.add('is-selected');
            }
        });
        // フリーワード復元
        if (parsed._freeword) {
            freewordInput.value = parsed._freeword;
            freewordClear.style.display = 'flex';
        }
        applyFilter();
        updateBadge();
    }

    // フリーワード ✕ボタン
    freewordInput.addEventListener('input', () => {
        freewordClear.style.display = freewordInput.value ? 'flex' : 'none';
    });

    freewordClear.addEventListener('click', () => {
        freewordInput.value = '';
        freewordClear.style.display = 'none';
        freewordInput.focus();
        // applyFilter() はモーダルを閉じる（検索ボタン）まで適用しない（案A）
        saveFilter();
        updateBadge();
    });

    // --- アコーディオン開閉 ---
    document.querySelectorAll('.filter-section-title').forEach(title => {
        title.addEventListener('click', () => {
            const section = title.dataset.section;
            const wrap = document.getElementById('wrap-' + section);
            title.classList.toggle('is-open');
            wrap.classList.toggle('is-open');
        });
    });

    // ★ モーダルを開くときにスナップショットを保存
    filterBtn.addEventListener('click', () => {
        snapshot = {
            genus: new Set(selected.genus),
            type: new Set(selected.type),
            ability: new Set(selected.ability),
            freeword: freewordInput.value,
        };
        filterModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    });

    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const category = chip.dataset.filter;
            const value = chip.dataset.value;
            if (selected[category].has(value)) {
                selected[category].delete(value);
                chip.classList.remove('is-selected');
            } else {
                selected[category].add(value);
                chip.classList.add('is-selected');
            }
            saveFilter();
            updateBadge();
        });
    });

    resetBtn.addEventListener('click', () => {
        Object.keys(selected).forEach(k => selected[k].clear());
        document.querySelectorAll('.filter-chip.is-selected')
            .forEach(c => c.classList.remove('is-selected'));
        freewordInput.value = '';
        freewordClear.style.display = 'none';
        // applyFilter();
        saveFilter();
        updateBadge();
    });

    // ★ 確定（apply=true）で閉じる
    applyBtn.addEventListener('click', () => {
        applyFilter();
        saveFilter();
        closeFilterModal(true);
    });

    // フリーワード入力中は件数だけ更新（適用は検索ボタン押下）
    freewordInput.addEventListener('input', () => {
        updateBadge();
    });

    function updateBadge() {
        const freeword = freewordInput.value.trim();
        const total = selected.genus.size + selected.type.size + selected.ability.size + (freeword ? 1 : 0);
        const matchCount = countMatched();

        badge.textContent = matchCount;
        badge.style.display = total > 0 ? 'flex' : 'none';
        filterBtn.classList.toggle('is-active', total > 0);

        const applyBadge = document.getElementById('apply-badge');
        if (total > 0) {
            applyBadge.textContent = matchCount;
            applyBadge.style.display = 'flex';
        } else {
            applyBadge.style.display = 'none';
        }
    }

    // ★ 共通関数：条件に合うカードの配列を返す
    function getMatchedCards() {
        const hasGenus = selected.genus.size > 0;
        const hasType = selected.type.size > 0;
        const hasAbility = selected.ability.size > 0;
        const freeword = freewordInput.value.trim();
        const hasFreeword = freeword !== '';

        return Array.from(document.querySelectorAll('.card')).filter(card => {
            const raw = card.getAttribute('onclick')
                .replace(/^openModal\(/, '')
                .replace(/\)$/, '');
            let data;
            try { data = JSON.parse(raw); } catch (e) { return false; }

            const genusOk = !hasGenus || selected.genus.has(data.jp_genus);
            const typeOk = !hasType || selected.type.has(data.type1) || (data.type2 && selected.type.has(data.type2));
            const abilityOk = !hasAbility || selected.ability.has(data.jp_ability);
            const freewordOk = !hasFreeword ||
                (data.name && data.name.includes(freeword)) ||
                (data.jp_text && data.jp_text.includes(freeword));

            return genusOk && typeOk && abilityOk && freewordOk;
        });
    }

    function countMatched() {
        return getMatchedCards().length;
    }

    function applyFilter() {
        const matched = new Set(getMatchedCards());
        document.querySelectorAll('.card').forEach(card => {
            card.classList.toggle('is-filtered-out', !matched.has(card));
        });
    }

    function saveFilter() {
        const toSave = {};
        Object.keys(selected).forEach(k => {
            toSave[k] = Array.from(selected[k]);
        });
        toSave._freeword = freewordInput.value.trim();
        sessionStorage.setItem('filter', JSON.stringify(toSave));
    }
});



/**
 * モーダルを開く関数
 * @param {Object} pokemon PHPから渡されたポケモンのデータ
 */
function openModal(pokemon) {
    // 1. 基本情報の流し込み
    document.getElementById('modal-id').textContent = 'No.' + String(pokemon.id).padStart(4, '0');
    document.getElementById('modal-img').src = 'images/pokemon/dream/' + pokemon.id + '.svg';
    document.getElementById('modal-img').alt = pokemon.name;
    document.getElementById('modal-name').textContent = pokemon.name;
    document.getElementById('modal-genus').textContent = pokemon.jp_genus;

    // ★ 名前編集UIのリセット
    const nameText = document.getElementById('modal-name');
    const nameInput = document.getElementById('modal-name-input');
    const editBtn = document.getElementById('edit-name-btn');
    const saveBtn = document.getElementById('save-name-btn');

    nameText.style.display = 'inline-block';
    nameInput.style.display = 'none';
    editBtn.style.display = 'inline-block';
    saveBtn.style.display = 'none';

    const description = pokemon.jp_text
        ? pokemon.jp_text.replace(/\\n/g, '\n')
        : 'ずかん説明は準備中です。';
    document.getElementById('modal-description').innerText = description;

    // 2. 高さ・重さ・特性の反映
    const height = (pokemon.height / 10).toFixed(1);
    const weight = (pokemon.weight / 10).toFixed(1);
    document.getElementById('modal-height').textContent = height + ' m';
    document.getElementById('modal-weight').textContent = weight + ' kg';
    document.getElementById('modal-ability').textContent = pokemon.jp_ability || '---';

    // 3. タイプの生成（バッジ形式）
    const typeContainer = document.getElementById('modal-types');
    let typeHtml = `<span class="type" style="background-color: #${pokemon.color1 || 'A8A878'};">${pokemon.type1}</span>`;
    if (pokemon.type2) {
        typeHtml += `<span class="type" style="background-color: #${pokemon.color2 || 'A8A878'};">${pokemon.type2}</span>`;
    }
    typeContainer.innerHTML = typeHtml;

    // 4. ステータスバーの更新
    const statKeys = ['hp', 'attack', 'defense', 'special_attack', 'special_defense', 'speed'];
    statKeys.forEach(key => {
        const val = pokemon[key] || 0;
        const valElement = document.getElementById(`val-${key}`);
        if (valElement) valElement.textContent = val;

        const bar = document.getElementById(`bar-${key}`);
        if (bar) {
            const percent = Math.min((val / 200) * 100, 100);
            setTimeout(() => {
                bar.style.width = percent + '%';
            }, 100);
        }
    });

    // 5. 名前の編集ボタンの挙動を設定
    editBtn.onclick = function () {
        nameInput.value = nameText.textContent;
        nameText.style.display = 'none';
        nameInput.style.display = 'inline-block';
        nameInput.focus();

        editBtn.style.display = 'none';
        saveBtn.style.display = 'inline-block';

        let shouldSave = false;

        saveBtn.onmousedown = function () {
            shouldSave = true;
            nameInput.blur();
        };

        nameInput.onkeydown = function (e) {
            if (e.key === 'Enter') {
                shouldSave = true;
                nameInput.blur();
            }
        };

        nameInput.onblur = function () {
            editBtn.style.display = 'inline-block';
            saveBtn.style.display = 'none';

            if (shouldSave) {
                const newName = nameInput.value.trim();
                if (newName !== '' && newName !== nameText.textContent) {
                    saveNewName(pokemon.id, newName);
                } else {
                    nameText.style.display = 'inline-block';
                    nameInput.style.display = 'none';
                }
            } else {
                nameText.style.display = 'inline-block';
                nameInput.style.display = 'none';
            }
        };
    };

    // 6. 削除ボタンの挙動を設定
    const deleteBtn = document.getElementById('delete-btn');
    deleteBtn.onclick = function () {
        // 名前変更済みの場合に備えて、表示中の名前で上書き
        pokemon.name = document.getElementById('modal-name').textContent;
        openConfirmModal(pokemon);
    };

    // 7. モーダルを表示
    document.getElementById('pokemonModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}


/**
 * 名前をDBに保存する関数
 */
function saveNewName(id, newName) {
    const formData = new FormData();
    formData.append('id', id);
    formData.append('name', newName);

    fetch('update_name.php', {
        method: 'POST',
        body: formData
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modal-name').textContent = newName;
                document.getElementById('modal-name').style.display = 'inline-block';
                document.getElementById('modal-name-input').style.display = 'none';
                showToast('名前を変更しました！');

                const allCards = document.querySelectorAll('.card');
                allCards.forEach(card => {
                    if (card.dataset.id == id) {
                        const nameEl = card.querySelector('.name');
                        if (nameEl) nameEl.textContent = newName;

                        const onclickAttr = card.getAttribute('onclick');
                        const updatedAttr = onclickAttr.replace(/"name":"[^"]*"/, `"name":"${newName}"`);
                        card.setAttribute('onclick', updatedAttr);
                    }
                });
            } else {
                showToast('エラー: ' + data.error);
            }
        })
        .catch(err => {
            console.error(err);
            showToast('通信に失敗しました');
        });
}


/**
 * 削除関連
 */
function openConfirmModal(pokemon) {
    const confirmModal = document.getElementById('confirmModal');
    const msg = document.getElementById('confirm-message');
    const executeBtn = document.getElementById('btn-execute-delete');
    msg.innerText = `${pokemon.name} `;

    executeBtn.onclick = function () {
        deletePokemon(pokemon.id);
    };

    confirmModal.style.display = 'block';
}

function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

function deletePokemon(id) {
    const executeBtn = document.getElementById('btn-execute-delete');
    executeBtn.disabled = true;

    const formData = new FormData();
    formData.append('id', id);

    fetch('delete_pokemon.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeConfirmModal();

                const modalImg = document.getElementById('modal-img');
                modalImg.classList.add('is-releasing');

                const closeBtn = document.querySelector('.close-button');
                if (closeBtn) closeBtn.style.display = 'none';
                document.getElementById('delete-btn').style.visibility = 'hidden';

                const mainModal = document.getElementById('pokemonModal');

                setTimeout(() => {
                    mainModal.classList.add('modal-fade-out');
                }, 1300);

                setTimeout(() => {
                    location.reload();
                }, 1800);
            } else {
                alert('野生にかえりませんでした: ' + data.error);
                executeBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            executeBtn.disabled = false;
        });
}

// モンスターボール
function catchNewPokemon() {
    const ball = document.getElementById('monsterball-float');
    if (ball.classList.contains('is-throwing')) return;

    ball.classList.add('is-throwing');

    const container = document.querySelector('.container');
    const header = document.querySelector('.header');
    container.style.pointerEvents = 'none';
    header.style.pointerEvents = 'none';

    const animationDelay = new Promise(resolve => setTimeout(resolve, 1000));
    const fetchData = fetch('get_pokemon.php').then(response => response.json());

    Promise.all([fetchData, animationDelay])
        .then(([data]) => {
            if (data.success) {
                showCatchEffect(data.name, data.id);
                setTimeout(() => {
                    location.reload();
                }, 5200);
            } else {
                showToast(data.message || 'ポケモンが見つかりませんでした...');
                ball.classList.remove('is-throwing');
                container.style.pointerEvents = '';
                header.style.pointerEvents = '';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('通信エラーが発生しました。');
            ball.classList.remove('is-throwing');
            container.style.pointerEvents = '';
            header.style.pointerEvents = '';
        });
}

/**
 * トースト通知を表示する関数
 */
function showToast(message) {
    const container = document.getElementById('toast-container');
    container.innerHTML = '';

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 500);
    }, 2500);
}

function showCatchEffect(name, id) {
    const overlay = document.createElement('div');
    overlay.className = 'catch-overlay is-active';
    document.body.appendChild(overlay);

    overlay.innerHTML = `
        <div class="catch-ball-wrap" id="catchBallWrap">
            <div class="catch-ball" id="catchBall">
                <div class="catch-ball-top" id="catchBallTop"></div>
                <div class="catch-ball-center">
                    <div class="catch-ball-btn" id="catchBallBtn"></div>
                </div>
            </div>
        </div>
        <div class="catch-flash" id="catchFlash"></div>
        <img class="catch-pokemon-img" id="catchImg" src="images/pokemon/dream/${id}.svg" alt="${name}">
        <div class="catch-label" id="catchLabel">${name} をつかまえた！</div>
    `;

    const ballWrap = document.getElementById('catchBallWrap');
    const ball = document.getElementById('catchBall');
    const ballTop = document.getElementById('catchBallTop');
    const ballBtn = document.getElementById('catchBallBtn');
    const flash = document.getElementById('catchFlash');

    function wait(ms) { return new Promise(r => setTimeout(r, ms)); }
    function css(el, styles) { Object.assign(el.style, styles); }

    async function run() {
        // ① ボール落下
        css(ballWrap, { transform: 'translate(-50%, -200px) scale(0.5)', opacity: '1', transition: 'none' });
        await wait(50);
        css(ballWrap, { transform: 'translate(-50%, -50%)', transition: 'transform 0.45s cubic-bezier(0.4,0,1,1)' });
        await wait(500);

        // ② ガクガク × 3
        for (let i = 0; i < 3; i++) {
            css(ball, { transform: 'rotate(-22deg)', transition: 'transform 0.12s ease-in-out' });
            await wait(130);
            css(ball, { transform: 'rotate(22deg)', transition: 'transform 0.12s ease-in-out' });
            await wait(130);
            css(ball, { transform: 'rotate(0deg)', transition: 'transform 0.1s ease-in-out' });
            await wait(200);
        }

        await wait(200);

        // ③ ボタンが青く光る
        css(ballBtn, { background: '#66d9ff', boxShadow: '0 0 8px #66d9ff', transition: 'all 0.2s' });
        await wait(400);

        // ④ パカッと開く
        css(ballTop, { transform: 'rotateX(-120deg)', transition: 'transform 0.25s ease-in' });
        await wait(200);

        // ⑤ フラッシュ
        flash.classList.add('catch-flash--go');
        await wait(300);
        flash.classList.remove('catch-flash--go');

        // ⑥ ボールを消す
        css(ballWrap, { opacity: '0', transition: 'opacity 0.2s' });
        await wait(200);
        ballWrap.style.display = 'none';

        // ⑦ 星エフェクト
        const cx = window.innerWidth / 2;
        const cy = window.innerHeight / 2;
        spawnRing(overlay, cx, cy, 200, 'fill', 0);
        spawnRing(overlay, cx, cy, 290, 'blue', 100);

        await wait(100);

        const ORB_COLORS = [
            'rgba(255,255,255,0.95)',
            'rgba(220,240,255,0.9)',
            'rgba(100,180,255,0.9)',
            'rgba(160,210,255,0.85)',
            'rgba(255,255,255,0.8)',
        ];
        const COUNT = 16;
        for (let i = 0; i < COUNT; i++) {
            const size = randBetween(16, 22);
            const angle = (360 / COUNT) * i + randBetween(-10, 10);
            const dist = randBetween(220, 320);
            const tx = Math.cos(angle * Math.PI / 180) * dist;
            const ty = Math.sin(angle * Math.PI / 180) * dist;
            const color = ORB_COLORS[Math.floor(Math.random() * ORB_COLORS.length)];
            const orb = document.createElement('div');
            orb.className = 'catch-orb';
            orb.style.cssText = `
                width:${size}px; height:${size}px;
                left:${cx - size / 2}px; top:${cy - size / 2}px;
                background:${color};
                box-shadow:0 0 ${size * 1.2}px ${color};
                --tx:${tx}px; --ty:${ty}px;
                --dur:${randBetween(0.55, 0.85)}s;
                --delay:${randBetween(0, 0.06)}s;
            `;
            overlay.appendChild(orb);
            orb.addEventListener('animationend', () => orb.remove());
        }

        // ⑧ ポケモン登場
        await wait(400);
        overlay.classList.add('is-pokemon-visible');

        // ⑨ フェードアウト
        await wait(3400);
        overlay.classList.add('is-leaving');
        await wait(600);
        overlay.remove();
    }

    run();
}

function spawnRing(parent, cx, cy, size, type, delayMs) {
    const ring = document.createElement('div');
    ring.className = `catch-ring catch-ring--${type}`;
    ring.style.cssText = `
        width:${size}px; height:${size}px;
        left:${cx}px; top:${cy}px;
        animation-delay:${delayMs}ms;
    `;
    parent.appendChild(ring);
    ring.addEventListener('animationend', () => ring.remove());
}

function randBetween(a, b) {
    return Math.random() * (b - a) + a;
}


/**
 * モーダルを閉じる関数
 */
function closeModal() {
    const modal = document.getElementById('pokemonModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';

    document.getElementById('modal-name').style.display = 'inline-block';
    document.getElementById('modal-name-input').style.display = 'none';

    const bars = document.querySelectorAll('[id^="bar-"]');
    bars.forEach(bar => bar.style.width = '0%');
}


/**
 * フィルターモーダルを閉じる関数
 * apply=true のとき確定、false のときキャンセル（スナップショットに戻す）
 */
function closeFilterModal(apply = false) {
    if (!apply && snapshot) {
        // ★ キャンセル：スナップショットに戻す
        selected.genus = new Set(snapshot.genus);
        selected.type = new Set(snapshot.type);
        selected.ability = new Set(snapshot.ability);
        freewordInput.value = snapshot.freeword;
        freewordClear.style.display = snapshot.freeword ? 'flex' : 'none';

        // チップのis-selectedを同期
        document.querySelectorAll('.filter-chip').forEach(chip => {
            const category = chip.dataset.filter;
            const value = chip.dataset.value;
            chip.classList.toggle('is-selected', selected[category].has(value));
        });

        // バッジ・一覧・sessionStorageもスナップショット時点に戻す
        const freeword = freewordInput.value.trim();
        const total = selected.genus.size + selected.type.size + selected.ability.size + (freeword ? 1 : 0);
        const badge = document.getElementById('filter-badge');
        const filterBtn = document.getElementById('filter-btn');
        const applyBadge = document.getElementById('apply-badge');

        const matched = new Set(
            Array.from(document.querySelectorAll('.card')).filter(card => {
                const raw = card.getAttribute('onclick')
                    .replace(/^openModal\(/, '').replace(/\)$/, '');
                let data;
                try { data = JSON.parse(raw); } catch (e) { return false; }
                const genusOk = !selected.genus.size || selected.genus.has(data.jp_genus);
                const typeOk = !selected.type.size || selected.type.has(data.type1) || (data.type2 && selected.type.has(data.type2));
                const abilityOk = !selected.ability.size || selected.ability.has(data.jp_ability);
                const hasFreeword = freeword !== '';
                const freewordOk = !hasFreeword ||
                    (data.name && data.name.includes(freeword)) ||
                    (data.jp_text && data.jp_text.includes(freeword));
                return genusOk && typeOk && abilityOk && freewordOk;
            })
        );

        document.querySelectorAll('.card').forEach(card => {
            card.classList.toggle('is-filtered-out', !matched.has(card));
        });

        const matchCount = matched.size;
        badge.textContent = matchCount;
        badge.style.display = total > 0 ? 'flex' : 'none';
        filterBtn.classList.toggle('is-active', total > 0);
        if (total > 0) {
            applyBadge.textContent = matchCount;
            applyBadge.style.display = 'flex';
        } else {
            applyBadge.style.display = 'none';
        }

        const toSave = {};
        Object.keys(selected).forEach(k => { toSave[k] = Array.from(selected[k]); });
        toSave._freeword = freeword;
        sessionStorage.setItem('filter', JSON.stringify(toSave));
    }

    snapshot = null;
    document.getElementById('filterModal').style.display = 'none';
    if (document.getElementById('pokemonModal').style.display !== 'block') {
        document.body.style.overflow = 'auto';
    }
}

/**
 * 背景クリックで閉じる処理
 */
window.addEventListener('click', (event) => {
    const modal = document.getElementById('pokemonModal');
    const confirmModal = document.getElementById('confirmModal');
    const filterModal = document.getElementById('filterModal');

    if (confirmModal.style.display === 'block') return;

    if (event.target === modal) {
        closeModal();
    }
    if (event.target === filterModal) {
        closeFilterModal(); // 引数なし = キャンセル扱い
    }
});