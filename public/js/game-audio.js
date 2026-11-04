
(() => {
    // BGMファイル
    const bgm = new Audio('/audio/battle-bgm.mp3');
    bgm.loop = true;
    bgm.preload = 'auto';

    let audioContext = null;
    let masterGain = null;
    let sfxGain = null;

    let enabled = true;
    let volume = 0.3;

    // 効果音用の音声機能
    function initializeAudio() {
        if (audioContext) {
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }
            return;
        }

        const AudioContextClass =
            window.AudioContext || window.webkitAudioContext;

        if (!AudioContextClass) return;

        audioContext = new AudioContextClass();

        masterGain = audioContext.createGain();
        sfxGain = audioContext.createGain();

        sfxGain.connect(masterGain);
        masterGain.connect(audioContext.destination);

        masterGain.gain.value = enabled ? 1 : 0;
        sfxGain.gain.value = 0.8;
    }

    // 効果音を鳴らす
    function tone(frequency, duration, type = 'square',
                  gainValue = 0.35) {
        if (!enabled) return;

        initializeAudio();
        if (!audioContext) return;

        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        const now = audioContext.currentTime;

        oscillator.type = type;
        oscillator.frequency.setValueAtTime(frequency, now);

        gain.gain.setValueAtTime(0.001, now);
        gain.gain.exponentialRampToValueAtTime(
            Math.max(gainValue, 0.002), now + 0.015
        );
        gain.gain.exponentialRampToValueAtTime(
            0.001, now + duration
        );

        oscillator.connect(gain);
        gain.connect(sfxGain);

        oscillator.start(now);
        oscillator.stop(now + duration + 0.03);
    }

    // BGM開始
    function startBgm() {
        if (!enabled) return;

        bgm.volume = volume;

        bgm.play().catch(error => {
            console.warn('BGMを再生できませんでした:', error);
        });
    }

    // BGM停止
    function stopBgm() {
        bgm.pause();
        bgm.currentTime = 0;
    }

    // 音量調整
    function setVolume(value) {
        volume = Math.max(0, Math.min(1, Number(value)));
        bgm.volume = volume;
    }

    // 音声ON/OFF
    function toggleSound() {
        enabled = !enabled;

        initializeAudio();

        if (masterGain && audioContext) {
            masterGain.gain.setTargetAtTime(
                enabled ? 1 : 0,
                audioContext.currentTime,
                0.03
            );
        }

        const button = document.getElementById('soundButton');

        if (enabled) {
            startBgm();
            if (button) button.textContent = '🔊 音声 ON';
        } else {
            stopBgm();
            if (button) button.textContent = '🔇 音声 OFF';
        }
    }

    // タワー設置音
    function playTowerPlace() {
        tone(520, 0.09, 'square', 0.12);
        window.setTimeout(() => {
            tone(780, 0.12, 'square', 0.1);
        }, 80);
    }

    // 攻撃音
    function playAttack() {
        tone(180, 0.08, 'sawtooth', 0.13);
    }

    // 敵撃破音
    function playEnemyDefeated() {
        tone(660, 0.1, 'triangle', 0.13);

        window.setTimeout(() => {
            tone(880, 0.18, 'triangle', 0.13);
        }, 100);
    }

    // ゲーム本体から呼び出せるようにする
    window.gameAudio = {
        startBgm,
        stopBgm,
        toggleSound,
        setVolume,
        playTowerPlace,
        playAttack,
        playEnemyDefeated
    };

    // 音声ボタン
    const soundButton = document.getElementById('soundButton');

    if (soundButton) {
        soundButton.addEventListener('click', toggleSound);
    }

    // 音量スライダー
    const volumeSlider = document.getElementById('volumeSlider');

    if (volumeSlider) {
        volumeSlider.addEventListener('input', () => {
            setVolume(Number(volumeSlider.value) / 15);
        });
    }

    // 最初のクリックでBGMを開始
    document.addEventListener('click', function startOnFirstClick() {
        if (enabled) {
            initializeAudio();
            startBgm();
        }

        document.removeEventListener('click', startOnFirstClick);
    });
})();
