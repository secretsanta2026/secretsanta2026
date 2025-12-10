<?php
session_start();

define('DATA_FILE', __DIR__ . '/data.json');

function loadData() {
    if (!file_exists(DATA_FILE)) {
        return null;
    }
    $content = file_get_contents(DATA_FILE);
    return json_decode($content, true);
}

function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

$error = null;
$assignment = null;
$giverName = null;
$alreadyRevealed = false;
$token = isset($_GET['token']) ? $_GET['token'] : null;
$shouldReveal = isset($_GET['reveal']) ? true : false;

if ($token) {
    $data = loadData();
    
    if (!$data) {
        $error = "No Secret Santa has been set up yet.";
    } else {
        // Find participant by token
        $found = false;
        foreach ($data['participants'] as $name => $info) {
            if ($info['token'] === $token) {
                $found = true;
                $giverName = $name;
                
                // Check if already revealed
                if (isset($data['assignments'][$name])) {
                    // Already drew a name
                    $alreadyRevealed = true;
                    $assignment = $data['assignments'][$name];
                } elseif ($shouldReveal) {
                    // Draw a random name NOW from available pool
                    $available = isset($data['availableNames']) ? $data['availableNames'] : [];
                    
                    // Remove own name from options
                    $available = array_values(array_filter($available, function($n) use ($name) {
                        return $n !== $name;
                    }));
                    
                    if (empty($available)) {
                        $error = "No names left to draw! Please contact the admin.";
                    } else {
                        // Pick random name
                        $randomIndex = array_rand($available);
                        $assignment = $available[$randomIndex];
                        
                        // Save assignment and remove from available pool
                        $data['assignments'][$name] = $assignment;
                        $data['revealed'][$name] = date('Y-m-d H:i:s');
                        
                        // Remove drawn name from pool
                        $data['availableNames'] = array_values(array_diff($data['availableNames'], [$assignment]));
                        
                        saveData($data);
                    }
                }
                // If not revealed and not requesting reveal, keep assignment null (show button)
                break;
            }
        }
        
        if (!$found) {
            $error = "Invalid link. Please check your email for the correct link.";
        }
    }
} else {
    $error = "No token provided. Please use the link from your email.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Secret Santa Assignment</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        .logo {
            max-width: 200px;
            height: auto;
            margin: 0 auto 20px;
            display: block;
        }
        .emoji {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
        }
        .greeting {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
        }
        .reveal-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
            box-shadow: 0 10px 30px rgba(102,126,234,0.3);
        }
        .reveal-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .reveal-name {
            font-size: 36px;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        .note {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #f44336;
        }
        .badge {
            display: inline-block;
            background: #4caf50;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 10px;
        }
        .reveal-button {
            background: linear-gradient(135deg, #d32f2f 0%, #f44336 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(211,47,47,0.4);
            transition: all 0.3s;
            margin: 20px 0;
        }
        .reveal-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(211,47,47,0.6);
        }
        .gift-box {
            width: 150px;
            height: 150px;
            margin: 30px auto;
            position: relative;
        }
        .box {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #d32f2f 0%, #f44336 100%);
            position: absolute;
            bottom: 0;
            left: 15px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .box::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 120px;
            background: #ffd700;
            left: 50%;
            transform: translateX(-50%);
            top: 0;
        }
        .box::after {
            content: '';
            position: absolute;
            width: 120px;
            height: 20px;
            background: #ffd700;
            top: 50%;
            transform: translateY(-50%);
            left: 0;
        }
        .lid {
            width: 140px;
            height: 30px;
            background: linear-gradient(135deg, #b71c1c 0%, #d32f2f 100%);
            position: absolute;
            top: 0;
            left: 5px;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transition: all 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .ribbon {
            width: 30px;
            height: 20px;
            background: #ffd700;
            position: absolute;
            top: -10px;
            left: 55px;
            border-radius: 5px;
        }
        .ribbon::before,
        .ribbon::after {
            content: '';
            position: absolute;
            width: 15px;
            height: 15px;
            background: #ffd700;
            top: 10px;
            border-radius: 50%;
        }
        .ribbon::before {
            left: -10px;
        }
        .ribbon::after {
            right: -10px;
        }
        .opening .lid {
            transform: translateY(-100px) rotateZ(-15deg);
            opacity: 0;
        }
        .sparkles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            opacity: 0;
            transition: opacity 0.5s;
        }
        .opening .sparkles {
            opacity: 1;
        }
        .sparkle {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #ffd700;
            border-radius: 50%;
            animation: sparkle 1s ease-out forwards;
        }
        @keyframes sparkle {
            0% {
                transform: translate(0, 0) scale(0);
                opacity: 1;
            }
            100% {
                transform: translate(var(--tx), var(--ty)) scale(1);
                opacity: 0;
            }
        }
        .hidden {
            display: none;
        }
        .fade-in {
            animation: fadeIn 0.8s ease-in forwards;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="logo.png" alt="Logo" class="logo">
        
        <?php if ($error): ?>
            <div class="emoji">😕</div>
            <h1>Oops!</h1>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php elseif (!$assignment): ?>
            <div class="emoji">🎁</div>
            <h1>Your Secret Santa Assignment</h1>
            <p class="greeting">Hi <strong><?= htmlspecialchars($giverName) ?></strong>! 🎄</p>
            
            <div class="gift-box" id="giftBox">
                <div class="sparkles" id="sparkles"></div>
                <div class="lid">
                    <div class="ribbon"></div>
                </div>
                <div class="box"></div>
            </div>
            
            <button class="reveal-button" id="revealBtn" onclick="revealAssignment()">
                🎅 Open Gift & Reveal
            </button>
            
            <div class="note">
                Click the button above to reveal your Secret Santa assignment!
            </div>
        <?php else: ?>
            <div class="emoji">🎁</div>
            <h1>Your Secret Santa Assignment</h1>
            <p class="greeting">Hi <strong><?= htmlspecialchars($giverName) ?></strong>! 🎄</p>
            
            <div class="reveal-box">
                <div class="reveal-label">You are the Secret Santa for</div>
                <div class="reveal-name"><?= htmlspecialchars($assignment) ?></div>
            </div>
            
            <?php if ($alreadyRevealed): ?>
                <div class="badge">Previously Revealed</div>
            <?php endif; ?>
            
            <div class="note">
                🎅 <strong>Remember:</strong> Keep this a secret!<br>
                🎁 Get a thoughtful gift for <?= htmlspecialchars($assignment) ?><br>
                ✨ Have fun and spread the holiday cheer!<br>
                🔗 You can return to this link anytime if you forget who you have
            </div>
        <?php endif; ?>
    </div>

    <script>
        function revealAssignment() {
            const btn = document.getElementById('revealBtn');
            const giftBox = document.getElementById('giftBox');
            const sparkles = document.getElementById('sparkles');
            
            btn.disabled = true;
            btn.textContent = '🎁 Opening...';
            
            // Add opening animation
            giftBox.classList.add('opening');
            
            // Create sparkle effects
            for (let i = 0; i < 20; i++) {
                const sparkle = document.createElement('div');
                sparkle.className = 'sparkle';
                const angle = (Math.PI * 2 * i) / 20;
                const distance = 50 + Math.random() * 50;
                sparkle.style.setProperty('--tx', Math.cos(angle) * distance + 'px');
                sparkle.style.setProperty('--ty', Math.sin(angle) * distance + 'px');
                sparkle.style.animationDelay = Math.random() * 0.3 + 's';
                sparkles.appendChild(sparkle);
            }
            
            // Wait for animation then redirect to mark as revealed
            setTimeout(() => {
                window.location.href = window.location.href + '&reveal=1';
            }, 1500);
        }
    </script>
    </div>
</body>
</html>