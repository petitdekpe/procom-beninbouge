/*
 * Mini-jeu des pages d'erreur (templates/bundles/TwigBundle/Exception/error.html.twig),
 * dans l'esprit du dinosaure de Chrome : le marcheur du logo de Bénin Bouge
 * court sur la carte du Bénin et saute par-dessus cônes, panneaux « 404 » et
 * oiseaux. Espace / flèche haut / toucher = sauter (et démarrer ou rejouer),
 * flèche bas = se baisser.
 *
 * Script autonome (pas de Stimulus, pas d'importmap) : une page d'erreur doit
 * rester légère et marcher même quand le reste du site est en panne.
 * Le sprite (feuille de 2 images de foulée) vient de data-sprite.
 */
(function () {
    'use strict';

    var canvas = document.getElementById('bb-runner');
    if (!canvas || !canvas.getContext) {
        return;
    }

    var ctx = canvas.getContext('2d');
    var status = document.getElementById('bb-runner-status');

    // Monde logique 720 × 200 ; le canvas est mis à l'échelle de son conteneur.
    var W = 720;
    var H = 200;
    var GROUND = 168;
    var STAND_H = 66;
    var CELL_W = 183;
    var CELL_H = 239;
    var STORAGE_KEY = 'bb-runner-hi';

    var COLORS = { tomato: '#CF3732', gold: '#F7CA46', forest: '#3A8556', petrol: '#0E3F51' };

    var sprite = new Image();
    var spriteReady = false;
    var tinted = [];
    var tintedFor = '';
    sprite.onload = function () {
        spriteReady = true;
        draw();
    };
    sprite.src = canvas.getAttribute('data-sprite');

    var scale = 1;
    var state = 'idle'; // idle | running | over
    var last = 0;
    var frameCount = 0;
    var raf = 0;

    var player;
    var obstacles;
    var clouds;
    var speed;
    var score;
    var hiScore = 0;
    var spawnIn;
    var ink = '#111B14';
    var muted = 'rgba(17,27,20,.14)';
    var displayFont = 'Impact, "Arial Narrow", sans-serif';
    var bodyFont = 'system-ui, -apple-system, "Segoe UI", sans-serif';

    try {
        hiScore = parseInt(window.localStorage.getItem(STORAGE_KEY), 10) || 0;
    } catch (e) {
        hiScore = 0;
    }

    function readTheme() {
        var cs = window.getComputedStyle(document.body);
        ink = (cs.getPropertyValue('--bb-ink') || '').trim() || ink;
        displayFont = (cs.getPropertyValue('--bb-font-display') || '').trim() || displayFont;
        bodyFont = (cs.getPropertyValue('--bb-font-body') || '').trim() || bodyFont;
        muted = withAlpha(ink, 0.14);
    }

    function withAlpha(hex, alpha) {
        var m = /^#([0-9a-f]{6})$/i.exec(hex);
        if (!m) {
            return 'rgba(128,128,128,' + alpha + ')';
        }
        var n = parseInt(m[1], 16);

        return 'rgba(' + (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + alpha + ')';
    }

    // Colorie la silhouette (noire) à la couleur du texte : lisible en thème clair et sombre.
    function tintSprite() {
        if (!spriteReady || tintedFor === ink) {
            return;
        }

        tinted = [0, 1].map(function (i) {
            var c = document.createElement('canvas');
            c.width = CELL_W;
            c.height = CELL_H;
            var g = c.getContext('2d');
            g.drawImage(sprite, i * CELL_W, 0, CELL_W, CELL_H, 0, 0, CELL_W, CELL_H);
            g.globalCompositeOperation = 'source-in';
            g.fillStyle = ink;
            g.fillRect(0, 0, CELL_W, CELL_H);

            return c;
        });
        tintedFor = ink;
    }

    function resize() {
        var width = canvas.parentElement.clientWidth || W;
        var dpr = window.devicePixelRatio || 1;
        scale = width / W;
        canvas.width = Math.round(width * dpr);
        canvas.height = Math.round(width * (H / W) * dpr);
        canvas.style.height = Math.round(width * (H / W)) + 'px';
        ctx.setTransform(scale * dpr, 0, 0, scale * dpr, 0, 0);
        draw();
    }

    function reset() {
        player = { x: 56, y: GROUND, vy: 0, jumping: false, ducking: false, tilt: 0 };
        obstacles = [];
        clouds = [
            { x: 120, y: 34, w: 54 },
            { x: 420, y: 56, w: 40 },
            { x: 640, y: 26, w: 62 },
        ];
        speed = 5.6;
        score = 0;
        spawnIn = 380;
    }

    function startOrJump() {
        if (state === 'idle') {
            reset();
            state = 'running';
            setStatus('');
            last = performance.now();
            loop(last);
        } else if (state === 'over') {
            reset();
            state = 'running';
            setStatus('');
            last = performance.now();
            loop(last);
        }

        if (state === 'running' && !player.jumping) {
            player.vy = -10.6;
            player.jumping = true;
            player.ducking = false;
        }
    }

    function setStatus(text) {
        if (status) {
            status.textContent = text;
        }
    }

    // ---------- Obstacles ----------
    function spawn() {
        var roll = Math.random();
        var o;
        if (score > 140 && roll < 0.24) {
            // Oiseau à hauteur de tête : se baisser ou sauter.
            var high = Math.random() < 0.5;
            o = { type: 'bird', w: 42, h: 24, y: GROUND - (high ? 62 : 36) };
        } else if (roll < 0.58) {
            var count = Math.random() < 0.4 ? 2 : 1;
            o = { type: 'cone', w: 22 * count + (count - 1) * 4, h: 30, y: GROUND - 30, count: count };
        } else {
            o = { type: 'sign', w: 44, h: 64, y: GROUND - 64 };
        }
        o.x = W + 20;
        obstacles.push(o);
        spawnIn = 300 + Math.random() * 280 + speed * 22;
    }

    function drawCone(o) {
        for (var i = 0; i < o.count; i++) {
            var x = o.x + i * 26;
            ctx.fillStyle = COLORS.tomato;
            ctx.beginPath();
            ctx.moveTo(x + 11, o.y);
            ctx.lineTo(x + 20, o.y + 26);
            ctx.lineTo(x + 2, o.y + 26);
            ctx.closePath();
            ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.fillRect(x + 6, o.y + 11, 10, 4);
            ctx.fillStyle = ink;
            ctx.fillRect(x, o.y + 26, 22, 4);
        }
    }

    function drawSign(o) {
        ctx.fillStyle = ink;
        ctx.fillRect(o.x + 20, o.y + 30, 4, 34);
        ctx.fillStyle = COLORS.tomato;
        roundRect(o.x, o.y, 44, 34, 6);
        ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.font = '700 20px ' + displayFont;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('404', o.x + 22, o.y + 18);
    }

    function drawBird(o) {
        var flap = Math.floor(frameCount / 8) % 2 === 0;
        ctx.fillStyle = COLORS.petrol;
        ctx.beginPath();
        ctx.ellipse(o.x + 22, o.y + 14, 14, 8, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.beginPath(); // tête
        ctx.arc(o.x + 6, o.y + 11, 6, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = COLORS.gold; // bec
        ctx.beginPath();
        ctx.moveTo(o.x, o.y + 10);
        ctx.lineTo(o.x - 8, o.y + 13);
        ctx.lineTo(o.x, o.y + 15);
        ctx.closePath();
        ctx.fill();
        ctx.fillStyle = COLORS.petrol; // aile
        ctx.beginPath();
        ctx.moveTo(o.x + 18, o.y + 12);
        ctx.lineTo(o.x + 30, o.y + 12);
        ctx.lineTo(o.x + 26, o.y + (flap ? -2 : 26));
        ctx.closePath();
        ctx.fill();
    }

    function roundRect(x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    // ---------- Décor ----------
    // Silhouette stylisée du Bénin (large au nord, étroite vers la côte), très pâle.
    var MAP = [[12, 78], [30, 66], [60, 52], [85, 26], [105, 8], [125, 16], [140, 38], [157, 66], [160, 96], [148, 114], [122, 126], [104, 134], [100, 170], [104, 210], [106, 250], [108, 285], [100, 296], [60, 298], [54, 262], [52, 200], [48, 160], [38, 130], [36, 100], [12, 96]];

    function drawMap(x) {
        var s = 0.5;
        ctx.save();
        ctx.translate(x, GROUND - 300 * s + 4);
        ctx.scale(s, s);
        ctx.fillStyle = withAlpha(COLORS.forest, 0.2);
        ctx.beginPath();
        MAP.forEach(function (p, i) {
            if (i === 0) {
                ctx.moveTo(p[0], p[1]);
            } else {
                ctx.lineTo(p[0], p[1]);
            }
        });
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }

    function drawCloud(c) {
        ctx.fillStyle = muted;
        roundRect(c.x, c.y, c.w, 10, 5);
        ctx.fill();
        roundRect(c.x + c.w * 0.2, c.y - 6, c.w * 0.5, 10, 5);
        ctx.fill();
    }

    var groundOffset = 0;
    var mapX = 520;

    function drawGround() {
        ctx.fillStyle = ink;
        ctx.fillRect(0, GROUND, W, 2);
        ctx.fillStyle = muted;
        for (var x = -((groundOffset % 90) + 90); x < W; x += 90) {
            ctx.fillRect(x + 20, GROUND + 8, 24, 2);
            ctx.fillRect(x + 62, GROUND + 15, 10, 2);
            ctx.fillRect(x + 34, GROUND + 22, 16, 2);
        }
    }

    // ---------- Joueur ----------
    function drawPlayer() {
        if (!spriteReady) {
            return;
        }

        tintSprite();
        var frame = player.jumping || state !== 'running' ? 0 : Math.floor(frameCount / 6) % 2;
        var h = player.ducking && !player.jumping ? STAND_H * 0.62 : STAND_H;
        var w = (CELL_W / CELL_H) * STAND_H * (player.ducking && !player.jumping ? 1.18 : 1);

        ctx.save();
        ctx.translate(player.x + w / 2, player.y);
        if (state === 'over') {
            ctx.rotate(-1.35);
            ctx.translate(0, -2);
        }
        ctx.drawImage(tinted[frame], -w / 2, -h, w, h);
        ctx.restore();
    }

    function playerBox() {
        var h = player.ducking && !player.jumping ? STAND_H * 0.62 : STAND_H;
        var w = (CELL_W / CELL_H) * STAND_H;

        return { x: player.x + w * 0.22, y: player.y - h + h * 0.1, w: w * 0.56, h: h * 0.86 };
    }

    function hits(a, b) {
        return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
    }

    // ---------- Boucle ----------
    function update(dt) {
        speed = Math.min(13.5, 5.6 + score / 170);
        score += speed * dt * 0.1;
        groundOffset += speed * dt;
        mapX -= speed * dt * 0.35;
        if (mapX < -140) {
            mapX = W + 900 + Math.random() * 600;
        }

        clouds.forEach(function (c) {
            c.x -= speed * dt * 0.3;
            if (c.x + c.w < 0) {
                c.x = W + Math.random() * 120;
                c.y = 20 + Math.random() * 50;
            }
        });

        if (player.jumping) {
            player.vy += 0.58 * dt;
            player.y += player.vy * dt;
            if (player.y >= GROUND) {
                player.y = GROUND;
                player.vy = 0;
                player.jumping = false;
            }
        }

        spawnIn -= speed * dt;
        if (spawnIn <= 0) {
            spawn();
        }

        var box = playerBox();
        obstacles.forEach(function (o) {
            o.x -= speed * dt;
            var oBox = { x: o.x + 4, y: o.y + 3, w: o.w - 8, h: o.h - 4 };
            if (state === 'running' && hits(box, oBox)) {
                gameOver();
            }
        });
        obstacles = obstacles.filter(function (o) {
            return o.x + o.w > -20;
        });
    }

    function gameOver() {
        state = 'over';
        var meters = Math.floor(score);
        if (meters > hiScore) {
            hiScore = meters;
            try {
                window.localStorage.setItem(STORAGE_KEY, String(hiScore));
            } catch (e) {
                // Stockage indisponible : le record n'est pas conservé.
            }
        }
        setStatus('Perdu ! Distance parcourue : ' + meters + ' mètres. Appuie sur Espace ou touche le jeu pour rejouer.');
    }

    function pad(n) {
        return ('00000' + Math.floor(n)).slice(-5);
    }

    function drawText(text, x, y, size, align, color, family) {
        ctx.fillStyle = color || ink;
        ctx.font = '700 ' + size + 'px ' + (family || displayFont);
        ctx.textAlign = align;
        ctx.textBaseline = 'alphabetic';
        ctx.fillText(text, x, y);
    }

    function draw() {
        if (!player) {
            reset();
        }
        // Couleurs lues dans les variables CSS (thème clair/sombre), pas à chaque image.
        if (frameCount % 30 === 0) {
            readTheme();
        }
        ctx.clearRect(0, 0, W, H);

        drawMap(mapX);
        clouds.forEach(drawCloud);
        drawGround();
        obstacles.forEach(function (o) {
            if (o.type === 'cone') {
                drawCone(o);
            } else if (o.type === 'sign') {
                drawSign(o);
            } else {
                drawBird(o);
            }
        });
        drawPlayer();

        // Score : record puis distance courante.
        ctx.font = '700 15px ui-monospace, Menlo, Consolas, monospace';
        ctx.textAlign = 'right';
        ctx.fillStyle = withAlpha(ink, 0.55);
        ctx.fillText('REC ' + pad(hiScore), W - 100, 24);
        ctx.fillStyle = ink;
        ctx.fillText(pad(score) + ' m', W - 14, 24);

        if (state === 'idle') {
            drawText('Prêt à bouger ?', W / 2, 78, 30, 'center');
            drawText('Espace ou toucher pour courir', W / 2, 102, 15, 'center', withAlpha(ink, 0.7), bodyFont);
        } else if (state === 'over') {
            drawText('Terminé !', W / 2, 74, 34, 'center', COLORS.tomato);
            drawText(Math.floor(score) + ' m · Espace ou toucher pour rejouer', W / 2, 100, 15, 'center', withAlpha(ink, 0.75), bodyFont);
        }
    }

    function loop(now) {
        if (state !== 'running') {
            draw();

            return;
        }

        var dt = Math.max(0, Math.min(2.5, (now - last) / 16.667));
        last = now;
        frameCount++;
        update(dt);
        draw();
        raf = window.requestAnimationFrame(loop);
    }

    // ---------- Entrées ----------
    function isJumpKey(e) {
        return e.code === 'Space' || e.code === 'ArrowUp' || e.code === 'KeyW';
    }

    document.addEventListener('keydown', function (e) {
        if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) {
            return;
        }

        if (isJumpKey(e)) {
            e.preventDefault();
            startOrJump();
        } else if ((e.code === 'ArrowDown' || e.code === 'KeyS') && state === 'running') {
            e.preventDefault();
            player.ducking = true;
        }
    });

    document.addEventListener('keyup', function (e) {
        if ((e.code === 'ArrowDown' || e.code === 'KeyS') && player) {
            player.ducking = false;
        }
    });

    canvas.addEventListener('pointerdown', function (e) {
        e.preventDefault();
        startOrJump();
    });

    var restart = document.getElementById('bb-runner-restart');
    if (restart) {
        restart.addEventListener('click', startOrJump);
    }

    window.addEventListener('resize', resize);
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            tintedFor = '';
            draw();
        });
    }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden && state === 'running') {
            window.cancelAnimationFrame(raf);
            state = 'idle';
            draw();
        }
    });

    reset();
    resize();
    // Les polices d'affichage arrivent après le premier rendu.
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(draw);
    }
})();
