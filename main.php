<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neon Highway Racer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://www.gstatic.com/firebasejs/11.6.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/11.6.1/firebase-auth.js"></script>
    <script src="https://www.gstatic.com/firebasejs/11.6.1/firebase-firestore.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap');
        
        body {
            background-color: #050505;
            color: #fff;
            font-family: 'Orbitron', sans-serif;
            margin: 0;
            overflow: hidden;
            touch-action: none;
        }

        #game-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            background: linear-gradient(to bottom, #000022 0%, #000000 100%);
        }

        #road-canvas {
            display: block;
            width: 100%;
            height: 100%;
        }

        .ui-overlay {
            position: absolute;
            top: 20px;
            left: 20px;
            pointer-events: none;
        }

        .dashboard {
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid #00f2ff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 242, 255, 0.3);
        }

        #history-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 250px;
            max-height: 80vh;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #ff00ea;
            border-radius: 8px;
            padding: 15px;
            overflow-y: auto;
            display: none;
        }

        .history-item {
            border-bottom: 1px solid #333;
            padding: 8px 0;
            font-size: 0.8rem;
        }

        #menu-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 100;
        }

        .neon-button {
            background: transparent;
            border: 2px solid #00f2ff;
            color: #00f2ff;
            padding: 15px 40px;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 20px;
            pointer-events: auto;
        }

        .neon-button:hover {
            background: #00f2ff;
            color: #000;
            box-shadow: 0 0 20px #00f2ff;
        }

        .controls-hint {
            margin-top: 30px;
            color: #aaa;
            text-align: center;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div id="game-container">
    <canvas id="road-canvas"></canvas>
    
    <div class="ui-overlay">
        <div class="dashboard">
            <div class="text-xs text-cyan-400">SPEED</div>
            <div id="speed-val" class="text-3xl font-bold">0 <span class="text-sm">KM/H</span></div>
            <div class="text-xs text-pink-400 mt-2">DISTANCE</div>
            <div id="distance-val" class="text-2xl">0 <span class="text-sm">M</span></div>
        </div>
    </div>

    <div id="history-panel">
        <h3 class="text-pink-500 font-bold mb-3 border-b border-pink-500 pb-1 text-center">RACE HISTORY</h3>
        <div id="history-list">
            <!-- Loaded from Firestore -->
            <p class="text-gray-500 text-xs italic">Loading history...</p>
        </div>
        <button onclick="toggleHistory()" class="mt-4 w-full text-xs text-gray-400 hover:text-white">Close</button>
    </div>

    <div id="menu-overlay">
        <h1 class="text-6xl font-black text-white italic mb-2 tracking-tighter">NEON<span class="text-cyan-400">HIGHWAY</span></h1>
        <p class="text-pink-500 animate-pulse">DON'T HIT THE TRAFFIC</p>
        
        <div id="auth-status" class="text-xs text-gray-500 mt-2 mb-4">Connecting to secure cloud...</div>
        
        <button id="start-btn" class="neon-button">Start Engine</button>
        <button id="view-history-btn" onclick="toggleHistory()" class="mt-4 text-cyan-400 hover:underline cursor-pointer pointer-events-auto">View Dashboard History</button>
        
        <div class="controls-hint">
            Use <span class="text-white font-bold">A / D</span> or <span class="text-white font-bold">ARROW KEYS</span> to steer<br>
            Touch sides of screen on mobile
        </div>
    </div>
</div>

<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-app.js";
    import { getAuth, onAuthStateChanged, signInAnonymously, signInWithCustomToken } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-auth.js";
    import { getFirestore, collection, addDoc, onSnapshot, query, doc, setDoc } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-firestore.js";

    // --- Firebase Setup ---
    const firebaseConfig = JSON.parse(__firebase_config);
    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);
    const db = getFirestore(app);
    const appId = typeof __app_id !== 'undefined' ? __app_id : 'neon-highway';
    let user = null;

    const initAuth = async () => {
        try {
            if (typeof __initial_auth_token !== 'undefined' && __initial_auth_token) {
                await signInWithCustomToken(auth, __initial_auth_token);
            } else {
                await signInAnonymously(auth);
            }
        } catch (err) {
            console.error("Auth error", err);
        }
    };
    initAuth();

    onAuthStateChanged(auth, (u) => {
        user = u;
        if (user) {
            document.getElementById('auth-status').innerText = `Logged in as Driver: ${user.uid.slice(0,8)}`;
            loadHistory();
        }
    });

    // --- Game Engine Variables ---
    const canvas = document.getElementById('road-canvas');
    const ctx = canvas.getContext('2d');
    const speedEl = document.getElementById('speed-val');
    const distEl = document.getElementById('distance-val');
    const startBtn = document.getElementById('start-btn');
    const menu = document.getElementById('menu-overlay');

    let gameActive = false;
    let speed = 0;
    let maxSpeed = 120;
    let distance = 0;
    let roadOffset = 0;
    let playerX = 0; // -1 to 1 (left to right)
    let keys = {};
    let obstacles = [];
    let particles = [];
    let animationFrameId;

    // Responsive Canvas
    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resize);
    resize();

    // Controls
    window.addEventListener('keydown', e => keys[e.code] = true);
    window.addEventListener('keyup', e => keys[e.code] = false);

    // Touch controls
    window.addEventListener('touchstart', e => {
        const x = e.touches[0].clientX;
        if (x < window.innerWidth / 2) keys['ArrowLeft'] = true;
        else keys['ArrowRight'] = true;
    });
    window.addEventListener('touchend', () => {
        keys['ArrowLeft'] = false;
        keys['ArrowRight'] = false;
    });

    function createObstacle() {
        const lane = Math.floor(Math.random() * 3) - 1; // -1, 0, 1
        obstacles.push({
            x: lane,
            z: 2000, // Distance away
            speed: 5 + Math.random() * 5,
            color: `hsl(${Math.random() * 360}, 70%, 50%)`
        });
    }

    function saveRace() {
        if (!user) return;
        const raceData = {
            distance: Math.floor(distance),
            topSpeed: Math.floor(speed),
            timestamp: Date.now()
        };
        
        const historyCol = collection(db, 'artifacts', appId, 'users', user.uid, 'race_history');
        addDoc(historyCol, raceData).catch(err => console.error(err));
    }

    function loadHistory() {
        if (!user) return;
        const historyCol = collection(db, 'artifacts', appId, 'users', user.uid, 'race_history');
        const q = query(historyCol);
        
        onSnapshot(q, (snapshot) => {
            const list = document.getElementById('history-list');
            const data = [];
            snapshot.forEach(doc => data.push(doc.data()));
            
            // Sort in memory
            data.sort((a, b) => b.timestamp - a.timestamp);
            
            list.innerHTML = data.slice(0, 10).map(item => `
                <div class="history-item">
                    <div class="flex justify-between">
                        <span class="text-white">${item.distance}m</span>
                        <span class="text-cyan-400">${item.topSpeed} km/h</span>
                    </div>
                    <div class="text-[10px] text-gray-500">${new Date(item.timestamp).toLocaleTimeString()}</div>
                </div>
            `).join('') || '<p class="text-gray-500 text-xs">No races yet.</p>';
        }, (err) => console.error(err));
    }

    window.toggleHistory = function() {
        const panel = document.getElementById('history-panel');
        panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
    }

    function gameLoop() {
        if (!gameActive) return;

        // Update Physics
        if (keys['ArrowLeft'] || keys['KeyA']) playerX -= 0.05;
        if (keys['ArrowRight'] || keys['KeyD']) playerX += 0.05;
        playerX = Math.max(-1.2, Math.min(1.2, playerX));

        speed = Math.min(maxSpeed, speed + 0.5);
        distance += speed / 50;
        roadOffset = (roadOffset + speed) % 1000;

        // Spawn obstacles
        if (Math.random() < 0.02) createObstacle();

        // Update obstacles
        for (let i = obstacles.length - 1; i >= 0; i--) {
            let o = obstacles[i];
            o.z -= speed + o.speed;

            // Collision Check
            if (o.z < 100 && o.z > 0) {
                const distToPlayer = Math.abs(o.x - playerX);
                if (distToPlayer < 0.4) {
                    gameOver();
                }
            }

            if (o.z < -100) obstacles.splice(i, 1);
        }

        draw();
        
        speedEl.innerHTML = `${Math.floor(speed)} <span class="text-sm">KM/H</span>`;
        distEl.innerHTML = `${Math.floor(distance)} <span class="text-sm">M</span>`;
        
        animationFrameId = requestAnimationFrame(gameLoop);
    }

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        const w = canvas.width;
        const h = canvas.height;
        const horizon = h * 0.4;

        // Sky / Background
        ctx.fillStyle = '#000011';
        ctx.fillRect(0, 0, w, h);

        // Draw Stars/Neon Grid for "Realism"
        ctx.strokeStyle = 'rgba(0, 242, 255, 0.1)';
        for(let i=0; i<w; i+=50) {
            ctx.beginPath();
            ctx.moveTo(i, 0);
            ctx.lineTo(i, h);
            ctx.stroke();
        }

        // Draw Road (Perspective)
        const roadW = w * 0.8;
        const bottomW = roadW;
        const topW = 50;

        // Road surface
        ctx.fillStyle = '#111';
        ctx.beginPath();
        ctx.moveTo(w/2 - topW, horizon);
        ctx.lineTo(w/2 + topW, horizon);
        ctx.lineTo(w/2 + bottomW/2, h);
        ctx.lineTo(w/2 - bottomW/2, h);
        ctx.fill();

        // Road markings
        ctx.strokeStyle = '#00f2ff';
        ctx.lineWidth = 2;
        ctx.setLineDash([20, 30]);
        ctx.lineDashOffset = -roadOffset;
        
        // Center Line
        ctx.beginPath();
        ctx.moveTo(w/2, horizon);
        ctx.lineTo(w/2, h);
        ctx.stroke();
        ctx.setLineDash([]);

        // Side Lines (Glow)
        ctx.shadowBlur = 15;
        ctx.shadowColor = '#00f2ff';
        ctx.beginPath();
        ctx.moveTo(w/2 - topW, horizon);
        ctx.lineTo(w/2 - bottomW/2, h);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(w/2 + topW, horizon);
        ctx.lineTo(w/2 + bottomW/2, h);
        ctx.stroke();
        ctx.shadowBlur = 0;

        // Draw Obstacles
        obstacles.forEach(o => {
            const perspective = 1 - (o.z / 2000);
            if (perspective < 0) return;
            
            const size = 20 + (perspective * 150);
            const oz = o.z / 2000;
            const ox = w/2 + (o.x * bottomW/2 * (1-oz));
            const oy = horizon + (h - horizon) * (1-oz);

            ctx.fillStyle = o.color;
            ctx.shadowBlur = 10;
            ctx.shadowColor = o.color;
            ctx.fillRect(ox - size/2, oy - size, size, size * 0.6);
            
            // Tail lights
            ctx.fillStyle = 'red';
            ctx.fillRect(ox - size/2 + 5, oy - size + 5, 10, 5);
            ctx.fillRect(ox + size/2 - 15, oy - size + 5, 10, 5);
            ctx.shadowBlur = 0;
        });

        // Draw Player Car
        const pSize = 180;
        const px = w/2 + (playerX * bottomW/2);
        const py = h - 50;

        // Car Body (Simple SVG-like shapes for efficiency)
        ctx.fillStyle = '#00f2ff';
        ctx.shadowBlur = 20;
        ctx.shadowColor = '#00f2ff';
        
        // Base
        ctx.fillRect(px - 60, py - 40, 120, 30);
        // Cabin
        ctx.fillStyle = '#000';
        ctx.fillRect(px - 40, py - 60, 80, 25);
        // Headlights
        ctx.fillStyle = '#fff';
        ctx.fillRect(px - 55, py - 35, 20, 10);
        ctx.fillRect(px + 35, py - 35, 20, 10);
        
        ctx.shadowBlur = 0;
    }

    function gameOver() {
        gameActive = false;
        cancelAnimationFrame(animationFrameId);
        saveRace();
        
        menu.style.display = 'flex';
        document.querySelector('h1').innerText = "CRASHED!";
        document.querySelector('h1').style.color = "#ff00ea";
        startBtn.innerText = "Try Again";
    }

    function startGame() {
        gameActive = true;
        speed = 0;
        distance = 0;
        playerX = 0;
        obstacles = [];
        menu.style.display = 'none';
        gameLoop();
    }

    startBtn.addEventListener('click', startGame);

</script>

</body>
</html>

