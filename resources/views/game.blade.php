
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MILITARY DEFENSE</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 20px;
            background: #111820;
            color: #edf3e8;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        h1 {
            color: #d5e7a5;
            letter-spacing: 3px;
            text-align: center;
        }

        .status {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-around;
            padding: 14px;
            background: #25332c;
            border: 1px solid #607254;
            border-radius: 8px;
        }

        .status span { color: #ffe18a; font-weight: bold; }

        .game-area {
            margin-top: 16px;
            border: 3px solid #71815e;
            border-radius: 6px;
            overflow: hidden;
            background: #293d2b;
        }

        canvas {
            display: block;
            width: 100%;
            height: auto;
            cursor: crosshair;
        }

        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        button {
            padding: 12px 16px;
            border: 1px solid #71815e;
            border-radius: 5px;
            background: #344b38;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover { background: #506d47; }
        button:disabled { opacity: 0.4; cursor: not-allowed; }

        #message {
            min-height: 28px;
            color: #ffe18a;
            margin-top: 14px;
        }

        .help {
            color: #c5d0c0;
            font-size: 14px;
            line-height: 1.8;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>MILITARY DEFENSE</h1>

    <div class="status">
        <div>資金：$<span id="money">300</span></div>
        <div>基地耐久：<span id="lives">20</span></div>
        <div>ウェーブ：<span id="wave">0</span></div>
        <div>撃破数：<span id="kills">0</span></div>
    </div>

    <div class="game-area">
        <canvas id="gameCanvas" width="960" height="600"></canvas>
    </div>

    
<div class="controls">
    <button id="buildButton">機関銃 ($100)</button>
    <button id="cannonButton">砲台 ($180)</button>
    <button id="rocketButton">ロケット砲 ($250)</button>
    <button id="sniperButton">狙撃砲 ($200)</button>
    <button id="upgradeButton" disabled>タワー強化 ($120)</button>
    <button id="waveButton">戦闘開始</button>
</div>

<div class="controls">
    <button id="soundButton">音声 ON</button>
    <label>
        音量
        <input id="volumeSlider"
               type="range"
               min="0"
               max="100"
               value="100">
    </label>
    
</div>


    <p id="message">戦闘準備完了。タワーを設置してください。</p>

    <p class="help">
        【遊び方】<br>
        1. 「機関銃タワー設置」を押し、道路以外の場所をクリックします。<br>
        2. 設置したタワーをクリックして選択します。<br>
        3. 「タワー強化」で選択したタワーを強化できます。<br>
        4. 「戦闘開始」で敵が進軍します。5ウェーブごとにボスが出現します。<br>
        ※ 敵を倒すと資金が増えます。敵が基地に到達すると耐久が減ります。
    </p>
</div>

<script>
const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');

const moneyText = document.getElementById('money');
const livesText = document.getElementById('lives');
const waveText = document.getElementById('wave');
const killsText = document.getElementById('kills');
const message = document.getElementById('message');
const buildButton = document.getElementById('buildButton');
const upgradeButton = document.getElementById('upgradeButton');
const waveButton = document.getElementById('waveButton');

// ゲームの基本データ
let money = 300;
let lives = 20;
let wave = 0;
let kills = 0;
let towers = [];
let enemies = [];
let bullets = [];
let effects = [];
let selectedTower = null;
let placingTower = false;
let selectedTowerType = 'gun';

const towerTypes = {
    gun: {
        name: '機関銃',
        cost: 100,
        damage: 15,
        range: 145,
        fireRate: 0.55,
        color: '#9ba58b'
    },
    cannon: {
        name: '砲台',
        cost: 180,
        damage: 45,
        range: 180,
        fireRate: 1.5,
        color: '#b58a55'
    },
    rocket: {
        name: 'ロケット砲',
        cost: 250,
        damage: 70,
        range: 200,
        fireRate: 2.0,
        color: '#b75b45'
    },
    sniper: {
        name: '狙撃砲',
        cost: 200,
        damage: 90,
        range: 260,
        fireRate: 2.2,
        color: '#6d91ad'
    }
};
let waveActive = false;
let gameOver = false;
let spawnQueue = [];
let spawnTimer = 0;
let lastTime = 0;
let nextId = 1;

// 敵が進む道路の位置
const path = [
    { x: 0, y: 130 },
    { x: 180, y: 130 },
    { x: 180, y: 300 },
    { x: 390, y: 300 },
    { x: 390, y: 160 },
    { x: 650, y: 160 },
    { x: 650, y: 440 },
    { x: 850, y: 440 },
    { x: 900, y: 520 }
];

function updateStatus() {
    moneyText.textContent = money;
    livesText.textContent = lives;
    waveText.textContent = wave;
    killsText.textContent = kills;

    upgradeButton.disabled =
        !selectedTower || money < 120 || gameOver;
}

function say(text) {
    message.textContent = text;
}

function distance(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

// 道路上の位置かどうか調べる
function nearRoad(x, y) {
    for (let i = 0; i < path.length - 1; i++) {
        const a = path[i];
        const b = path[i + 1];
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        const length2 = dx * dx + dy * dy;

        let t = ((x - a.x) * dx + (y - a.y) * dy)
            / length2;

        t = Math.max(0, Math.min(1, t));

        const px = a.x + t * dx;
        const py = a.y + t * dy;

        if (Math.hypot(x - px, y - py) < 37) {
            return true;
        }
    }
    return false;
}

// 道路を描く
function drawPath() {
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    ctx.beginPath();
    ctx.moveTo(path[0].x, path[0].y);

    for (let i = 1; i < path.length; i++) {
        ctx.lineTo(path[i].x, path[i].y);
    }

    ctx.strokeStyle = '#18251d';
    ctx.lineWidth = 62;
    ctx.stroke();

    ctx.strokeStyle = '#766e51';
    ctx.lineWidth = 54;
    ctx.stroke();

    ctx.setLineDash([16, 16]);
    ctx.strokeStyle = '#aaa184';
    ctx.lineWidth = 2;
    ctx.stroke();
    ctx.setLineDash([]);

    // 基地
    ctx.fillStyle = '#273b4a';
    ctx.fillRect(845, 490, 95, 75);
    ctx.strokeStyle = '#a9c0c7';
    ctx.lineWidth = 3;
    ctx.strokeRect(845, 490, 95, 75);

    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 16px Arial';
    ctx.fillText('BASE', 867, 532);

    // 進軍方向
    ctx.fillStyle = '#f0df8d';
    ctx.font = 'bold 14px Arial';
    ctx.fillText('ENEMY ROUTE', 20, 95);
}

// 背景を描く
function drawBackground() {
    ctx.fillStyle = '#344c32';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // 地面の模様
    for (let x = 20; x < 960; x += 60) {
        for (let y = 20; y < 600; y += 60) {
            ctx.fillStyle =
                ((x + y) % 120 === 0) ? '#3a5537' : '#30472f';
            ctx.fillRect(x, y, 26, 18);
        }
    }

    // 草木・岩
    for (let i = 0; i < 24; i++) {
        const x = (i * 137 + 53) % 950;
        const y = (i * 83 + 25) % 570;

        if (!nearRoad(x, y)) {
            ctx.fillStyle = '#263a29';
            ctx.beginPath();
            ctx.arc(x, y, 8, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#526344';
            ctx.beginPath();
            ctx.arc(x - 2, y - 2, 4, 0, Math.PI * 2);
            ctx.fill();
        }
    }
}

// タワーを描く
function drawTower(tower) {
    ctx.save();

    // 選択中のタワーの射程範囲
    if (tower === selectedTower) {
        ctx.beginPath();
        ctx.arc(tower.x, tower.y, tower.range, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(180,220,120,0.10)';
        ctx.fill();
        ctx.strokeStyle = '#d8ec9b';
        ctx.lineWidth = 2;
        ctx.stroke();
    }

    // タワーの種類ごとの色
    const colors = {
        gun: '#6f9661',
        cannon: '#929ba5',
        rocket: '#b65c45',
        sniper: '#527fa8'
    };

    const color = colors[tower.type] || '#9ba58b';

    // 台座
    ctx.fillStyle = '#18251f';
    ctx.fillRect(tower.x - 19, tower.y - 19, 38, 38);

    ctx.fillStyle = color;
    ctx.fillRect(tower.x - 15, tower.y - 15, 30, 30);

    // 台座の中央部分
    ctx.fillStyle = '#26372c';
    ctx.fillRect(tower.x - 10, tower.y - 10, 20, 20);

    // 砲塔の向き
    ctx.translate(tower.x, tower.y);
    ctx.rotate(tower.angle || 0);

    // 種類別の武器
    if (tower.type === 'gun') {

        // 機関銃：銃身2本
        ctx.fillStyle = '#253329';
        ctx.fillRect(-7, -8, 16, 16);

        ctx.fillStyle = '#17221b';
        ctx.fillRect(2, -7, 25, 4);
        ctx.fillRect(2, 3, 25, 4);

        ctx.fillStyle = '#b5c49c';
        ctx.fillRect(24, -7, 5, 4);
        ctx.fillRect(24, 3, 5, 4);

    } else if (tower.type === 'cannon') {

        // 大砲：太く長い砲身
        ctx.fillStyle = '#3e4a50';
        ctx.fillRect(-11, -11, 22, 22);

        ctx.fillStyle = '#202a30';
        ctx.fillRect(0, -7, 29, 14);

        ctx.fillStyle = '#c1c8cd';
        ctx.fillRect(25, -6, 6, 12);

    } else if (tower.type === 'rocket') {

        // ロケット：4本の発射筒
        ctx.fillStyle = '#49332d';
        ctx.fillRect(-10, -10, 20, 20);

        ctx.fillStyle = '#222c2c';
        ctx.fillRect(-2, -11, 22, 6);
        ctx.fillRect(-2, 5, 22, 6);

        ctx.fillStyle = '#e58a54';
        ctx.fillRect(17, -10, 5, 5);
        ctx.fillRect(17, 6, 5, 5);

        ctx.fillStyle = '#222c2c';
        ctx.fillRect(-11, -10, 6, 20);

    } else if (tower.type === 'sniper') {

        // 狙撃：細く長い銃身と照準器
        ctx.fillStyle = '#263d55';
        ctx.fillRect(-9, -8, 17, 16);

        ctx.fillStyle = '#172c42';
        ctx.fillRect(0, -2, 34, 4);

        ctx.fillStyle = '#b7d2e9';
        ctx.fillRect(29, -2, 6, 4);

        // 照準器
        ctx.fillStyle = '#101b29';
        ctx.fillRect(-1, -10, 12, 4);

        ctx.fillStyle = '#8eb9d8';
        ctx.fillRect(2, -11, 6, 2);

    } else {

        // その他の種類の場合
        ctx.fillStyle = '#1b2820';
        ctx.fillRect(0, -4, 27, 8);
    }

    ctx.restore();

    // レベル表示
    ctx.fillStyle = '#ffffff';
    ctx.font = '12px Arial';
    ctx.fillText('Lv.' + tower.level, tower.x - 13, tower.y + 31);
}


// 敵を描く
function drawEnemy(enemy) {
    ctx.save();
    ctx.translate(enemy.x, enemy.y);

    // ボスは大きく表示
    const size = enemy.boss ? 1.65 : 1;

    ctx.scale(size, size);

    // 車体
    ctx.fillStyle = enemy.boss ? '#732f32' :
        (enemy.type === 'armor' ? '#5a6450' : '#746b45');

    ctx.fillRect(-15, -11, 30, 22);

    // 履帯
    ctx.fillStyle = '#202820';
    ctx.fillRect(-17, -13, 34, 5);
    ctx.fillRect(-17, 8, 34, 5);

    // 砲塔・前方
    ctx.fillStyle = enemy.boss ? '#ed7770' : '#a9b18b';
    ctx.fillRect(-7, -7, 14, 14);
    ctx.fillRect(0, -3, 20, 6);

    ctx.restore();

    // HPゲージ
    const barWidth = enemy.boss ? 52 : 34;
    const ratio = Math.max(0, enemy.hp / enemy.maxHp);

    ctx.fillStyle = '#371c1c';
    ctx.fillRect(enemy.x - barWidth / 2, enemy.y - 25, barWidth, 5);

    ctx.fillStyle = enemy.boss ? '#ff5656' : '#8ee06b';
    ctx.fillRect(
        enemy.x - barWidth / 2,
        enemy.y - 25,
        barWidth * ratio,
        5
    );

    if (enemy.boss) {
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 12px Arial';
        ctx.fillText('BOSS', enemy.x - 18, enemy.y - 32);
    }
}

// 敵を出現させる
function spawnEnemy(type) {
    const boss = type === 'boss';
    const armor = type === 'armor';

    const hp = boss ? 500 + wave * 120 :
        armor ? 100 + wave * 20 : 45 + wave * 12;

    enemies.push({
        id: nextId++,
        x: path[0].x,
        y: path[0].y,
        segment: 0,
        hp: hp,
        maxHp: hp,
        speed: boss ? 25 : armor ? 34 : 48,
        reward: boss ? 150 : armor ? 25 : 12,
        boss: boss,
        type: type
    });
}

// ウェーブ開始
function startWave() {
    if (waveActive || gameOver) return;

    wave++;
    waveActive = true;
    spawnQueue = [];

    const count = 5 + wave * 2;

    for (let i = 0; i < count; i++) {
        if (wave >= 3 && i % 4 === 3) {
            spawnQueue.push('armor');
        } else {
            spawnQueue.push('normal');
        }
    }

    if (wave % 5 === 0) {
        spawnQueue.push('boss');
        say('警告！ボス車両が接近中！');
    } else {
        say('ウェーブ ' + wave + '：敵が進軍しています！');
    }

    spawnTimer = 0;
    waveButton.disabled = true;
    updateStatus();
}

// 敵を移動させる
function updateEnemies(dt) {
    for (let i = enemies.length - 1; i >= 0; i--) {
        const enemy = enemies[i];
        let move = enemy.speed * dt;

        while (move > 0 && enemy.segment < path.length - 1) {
            const target = path[enemy.segment + 1];
            const d = distance(enemy, target);

            if (d <= move) {
                enemy.x = target.x;
                enemy.y = target.y;
                move -= d;
                enemy.segment++;
            } else {
                enemy.x += (target.x - enemy.x) / d * move;
                enemy.y += (target.y - enemy.y) / d * move;
                move = 0;
            }
        }

        if (enemy.segment >= path.length - 1) {
            lives -= enemy.boss ? 5 : 1;
            enemies.splice(i, 1);

            if (lives <= 0) {
                lives = 0;
                gameOver = true;
                waveActive = false;
                say('基地が陥落しました。ページを再読み込みして再挑戦できます。');
                waveButton.disabled = true;
            }
        }
    }
}

// タワーの自動射撃
function updateTowers(dt) {
    for (const tower of towers) {
        tower.cooldown -= dt;

        let target = null;
        let bestDistance = Infinity;

        for (const enemy of enemies) {
            const d = distance(tower, enemy);

            if (d <= tower.range && d < bestDistance) {
                target = enemy;
                bestDistance = d;
            }
        }

        if (target) {
            tower.angle = Math.atan2(
                target.y - tower.y,
                target.x - tower.x
            );

            if (tower.cooldown <= 0) {
                bullets.push({
                    x: tower.x,
                    y: tower.y,
                    target: target,
                    damage: tower.damage,
                    speed: 430
                });

                tower.cooldown = tower.fireRate;

                effects.push({
                    x: tower.x + Math.cos(tower.angle) * 24,
                    y: tower.y + Math.sin(tower.angle) * 24,
                    life: 0.08,
                    maxLife: 0.08,
                    type: 'muzzle'
                });
            }
        }
    }
}

// 弾丸を移動させる
function updateBullets(dt) {
    for (let i = bullets.length - 1; i >= 0; i--) {
        const bullet = bullets[i];
        const target = bullet.target;

        if (!enemies.includes(target)) {
            bullets.splice(i, 1);
            continue;
        }

        const d = distance(bullet, target);
        const step = bullet.speed * dt;

        if (d <= step + 8) {
            target.hp -= bullet.damage;

            //攻撃音
            window.gameAudio.playAttack();

            effects.push({
                x: target.x,
                y: target.y,
                life: 0.22,
                maxLife: 0.22,
                type: 'impact'
            });

            if (target.hp <= 0) {
                money += target.reward;
                kills++;
                
                // 敵を倒したときの音
                window.gameAudio.playEnemyDefeated();

                effects.push({
                    x: target.x,
                    y: target.y,
                    life: 0.45,
                    maxLife: 0.45,
                    type: 'explosion'
                });

                enemies.splice(enemies.indexOf(target), 1);
            }

            bullets.splice(i, 1);
        } else {
            bullet.x += (target.x - bullet.x) / d * step;
            bullet.y += (target.y - bullet.y) / d * step;
        }
    }
}

// エフェクトの時間を進める
function updateEffects(dt) {
    for (let i = effects.length - 1; i >= 0; i--) {
        effects[i].life -= dt;

        if (effects[i].life <= 0) {
            effects.splice(i, 1);
        }
    }
}

// 画面を描画する
function draw() {
    drawBackground();
    drawPath();

    for (const tower of towers) drawTower(tower);
    for (const enemy of enemies) drawEnemy(enemy);

    // 弾丸
    for (const bullet of bullets) {
        ctx.beginPath();
        ctx.arc(bullet.x, bullet.y, 4, 0, Math.PI * 2);
        ctx.fillStyle = '#ffe98a';
        ctx.fill();

        ctx.beginPath();
        ctx.arc(bullet.x, bullet.y, 7, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255,220,100,0.18)';
        ctx.fill();
    }

    // 銃口・着弾・爆発
    for (const effect of effects) {
        const ratio = effect.life / effect.maxLife;

        if (effect.type === 'muzzle') {
            ctx.fillStyle = '#fff2a0';
            ctx.beginPath();
            ctx.arc(effect.x, effect.y, 7 * ratio, 0, Math.PI * 2);
            ctx.fill();
        } else {
            const radius = effect.type === 'explosion'
                ? 25 * (1 - ratio) + 5
                : 12 * (1 - ratio) + 2;

            ctx.beginPath();
            ctx.arc(effect.x, effect.y, radius, 0, Math.PI * 2);
            ctx.fillStyle = effect.type === 'explosion'
                ? 'rgba(255,100,30,' + ratio + ')'
                : 'rgba(255,230,120,' + ratio + ')';
            ctx.fill();

            ctx.strokeStyle = '#ffcf6c';
            ctx.lineWidth = 2;
            ctx.stroke();
        }
    }

    if (gameOver) {
        ctx.fillStyle = 'rgba(0,0,0,0.72)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.fillStyle = '#ff7777';
        ctx.font = 'bold 48px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('BASE DESTROYED', 480, 285);
        ctx.textAlign = 'left';
    }
}

// ゲームループ
function gameLoop(timestamp) {
    const dt = lastTime === 0
        ? 0
        : Math.min((timestamp - lastTime) / 1000, 0.05);

    lastTime = timestamp;

    if (!gameOver) {
        if (waveActive) {
            spawnTimer -= dt;

            if (spawnQueue.length > 0 && spawnTimer <= 0) {
                spawnEnemy(spawnQueue.shift());
                spawnTimer = 0.85;
            }

            updateEnemies(dt);
            updateTowers(dt);
            updateBullets(dt);
            updateEffects(dt);

            if (spawnQueue.length === 0 && enemies.length === 0) {
                waveActive = false;
                waveButton.disabled = false;
                money += 50;
                say('ウェーブ ' + wave + ' クリア！報酬 $50');
            }
        }

        // 戦闘中以外も残った弾丸・演出を処理
        if (!waveActive) {
            updateBullets(dt);
            updateEffects(dt);
        }
    }

    updateStatus();
    draw();
    requestAnimationFrame(gameLoop);
}

// キャンバスをクリックしたとき
canvas.addEventListener('click', function(event) {
    if (gameOver) return;

    const rect = canvas.getBoundingClientRect();
    const x = (event.clientX - rect.left) * canvas.width / rect.width;
    const y = (event.clientY - rect.top) * canvas.height / rect.height;

    if (placingTower) {
        if (money < 100) {
            say('資金が足りません！');
            return;
        }

        if (nearRoad(x, y)) {
            say('道路の上には設置できません！');
            return;
        }

        if (towers.some(t => distance(t, {x, y}) < 43)) {
            say('タワー同士が近すぎます！');
            return;
        }

        const data = towerTypes[selectedTowerType];

        towers.push({
            x: x,
            y: y,
            type: selectedTowerType,
            name: data.name,
            color: data.color,
            range: data.range,
            damage: data.damage,
            fireRate: data.fireRate,
            cooldown: 0,
            level: 1,
            angle: 0
        });

        // タワー設置音
        window.gameAudio.playTowerPlace();

        money -= data.cost;
        selectedTower = towers[towers.length - 1];
        placingTower = false;
        buildButton.textContent = '機関銃タワー設置 ($100)';
        say('機関銃タワーを設置しました！');
        updateStatus();
        return;
    }

    selectedTower = null;

    for (const tower of towers) {
        if (distance(tower, {x, y}) < 24) {
            selectedTower = tower;
            break;
        }
    }

    upgradeButton.disabled =
        !selectedTower || money < 120;

    say(selectedTower
        ? 'タワーを選択中。強化費用は $120 です。'
        : 'タワーを設置するか、既存タワーを選択してください。');
});

// 設置ボタン
function selectTowerType(type) {
    if (gameOver) return;

    selectedTowerType = type;
    placingTower = true;

    const data = towerTypes[type];

    buildButton.textContent =
        data.name + 'を設置 ($' + data.cost + ')';

    say(data.name + 'を設置する場所をクリックしてください。');
}

    buildButton.addEventListener('click', function() {
        selectTowerType('gun');
    });

    document.getElementById('cannonButton').addEventListener('click', function() {
        selectTowerType('cannon');
    });

    document.getElementById('rocketButton').addEventListener('click', function() {
        selectTowerType('rocket');
    });

    document.getElementById('sniperButton').addEventListener('click', function() {
        selectTowerType('sniper');
    });

// 強化ボタン
upgradeButton.addEventListener('click', function() {
    if (!selectedTower || money < 120 || gameOver) return;

    money -= 120;
    selectedTower.level++;
    selectedTower.damage += 10;
    selectedTower.range += 15;
    selectedTower.fireRate = Math.max(
        0.20,
        selectedTower.fireRate * 0.90
    );

    say('タワーをレベル ' + selectedTower.level + ' に強化しました！');
    updateStatus();
});

// 戦闘開始ボタン
waveButton.addEventListener('click', startWave);

updateStatus();
draw();
requestAnimationFrame(gameLoop);
</script>
<script src="{{ asset('js/game-audio.js') }}"></script>
</body>
</html>
