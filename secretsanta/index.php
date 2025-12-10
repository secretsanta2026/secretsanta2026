<?php
session_start();

// Configuration
define('DATA_FILE', __DIR__ . '/data.json');
define('ADMIN_PASSWORD', 'admin007'); // ⚠️ CHANGE THIS!

// Get current URL for email links
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['PHP_SELF']);
$baseUrl = $protocol . '://' . $host . $scriptPath;

// Helper functions
function loadData() {
    if (!file_exists(DATA_FILE)) {
        return ['participants' => [], 'assignments' => [], 'revealed' => []];
    }
    $content = file_get_contents(DATA_FILE);
    return json_decode($content, true) ?: ['participants' => [], 'assignments' => [], 'revealed' => []];
}

function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function performDraw($participants) {
    $names = array_keys($participants);
    if (count($names) < 2) throw new Exception('Need at least 2 participants');
    
    $maxAttempts = 1000;
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $assignments = [];
        $available = $names;
        $valid = true;
        
        foreach ($names as $giver) {
            $possible = array_values(array_filter($available, function($r) use ($giver) {
                return $r !== $giver;
            }));
            
            if (count($possible) === 0) {
                $valid = false;
                break;
            }
            
            $recipient = $possible[array_rand($possible)];
            $assignments[$giver] = $recipient;
            $available = array_values(array_diff($available, [$recipient]));
        }
        
        if ($valid) return $assignments;
    }
    
    throw new Exception('Could not generate valid assignments');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'setup':
                if ($_POST['password'] !== ADMIN_PASSWORD) {
                    throw new Exception('Invalid password');
                }
                
                $participants = json_decode($_POST['participants'], true);
                if (!$participants || count($participants) < 2) {
                    throw new Exception('Need at least 2 participants');
                }
                
                $participantMap = [];
                $allNames = [];
                foreach ($participants as $p) {
                    $token = generateToken();
                    $participantMap[$p['name']] = [
                        'email' => $p['email'],
                        'department' => $p['department'],
                        'token' => $token
                    ];
                    $allNames[] = $p['name'];
                }
                
                // Don't pre-assign! Just store available names pool
                $data = [
                    'participants' => $participantMap,
                    'availableNames' => $allNames, // Pool of names to draw from
                    'assignments' => [], // Empty - will be filled as people reveal
                    'revealed' => []
                ];
                saveData($data);
                
                // Send emails using InfinityFree compatible settings
                $emailsSent = 0;
                $emailErrors = [];
                
                // InfinityFree requires proper From header
                $fromEmail = "noreply@" . str_replace('www.', '', $_SERVER['HTTP_HOST']);
                
                foreach ($participantMap as $name => $info) {
                    $revealUrl = $baseUrl . '/reveal.php?token=' . $info['token'];
                    
                    $subject = 'Your Secret Santa Assignment';
                    
                    // Plain text version for better deliverability
                    $message = "Hi {$name}!\n\n";
                    $message .= "You've been assigned a Secret Santa recipient!\n\n";
                    $message .= "Click this link to reveal your assignment:\n";
                    $message .= "{$revealUrl}\n\n";
                    $message .= "Keep this a secret and have fun!\n";
                    $message .= "You can return to this link anytime if you forget.\n\n";
                    $message .= "Happy Holidays! 🎅🎄";
                    
                    $headers = "From: Secret Santa <{$fromEmail}>\r\n";
                    $headers .= "Reply-To: {$fromEmail}\r\n";
                    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
                    
                    // Use simple mail() without @ to see actual errors
                    if (mail($info['email'], $subject, $message, $headers)) {
                        $emailsSent++;
                    } else {
                        $emailErrors[] = $name;
                    }
                }
                
                if ($emailsSent === count($participantMap)) {
                    $responseMsg = "✅ Secret Santa set up successfully! Emails sent to all {$emailsSent} participants.";
                } else if ($emailsSent > 0) {
                    $responseMsg = "⚠️ Secret Santa set up. Emails sent to {$emailsSent} of " . count($participantMap) . " participants. Some emails may have failed - check spam folders.";
                } else {
                    throw new Exception("Email sending failed. Your hosting may not support PHP mail() function. Contact your hosting provider to enable email sending, or use the 'Check Status' button to get reveal links manually.");
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => $responseMsg
                ]);
                break;
                
            case 'status':
                if ($_POST['password'] !== ADMIN_PASSWORD) {
                    throw new Exception('Invalid password');
                }
                
                $data = loadData();
                $total = count($data['participants']);
                $revealed = count($data['revealed']);
                $remainingNames = isset($data['availableNames']) ? $data['availableNames'] : [];
                
                // Build detailed participant list
                $participantDetails = [];
                foreach ($data['participants'] as $name => $info) {
                    $hasDrawn = isset($data['assignments'][$name]);
                    $participantDetails[] = [
                        'name' => $name,
                        'email' => $info['email'],
                        'department' => isset($info['department']) ? $info['department'] : 'N/A',
                        'assignment' => $hasDrawn ? $data['assignments'][$name] : 'Not drawn yet',
                        'revealed' => $hasDrawn,
                        'revealedAt' => isset($data['revealed'][$name]) ? $data['revealed'][$name] : null
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'total' => $total,
                    'revealed' => $revealed,
                    'participants' => $participantDetails,
                    'remainingNames' => $remainingNames,
                    'remainingCount' => count($remainingNames)
                ]);
                break;
                
            case 'send_reminders':
                if ($_POST['password'] !== ADMIN_PASSWORD) {
                    throw new Exception('Invalid password');
                }
                
                $data = loadData();
                if (empty($data['participants'])) {
                    throw new Exception('No participants found');
                }
                
                $available = isset($data['availableNames']) ? $data['availableNames'] : [];
                $fromEmail = "noreply@" . str_replace('www.', '', $_SERVER['HTTP_HOST']);
                $sent = 0;
                $notRevealed = [];
                
                foreach ($data['participants'] as $name => $info) {
                    // Only send to people who haven't drawn yet
                    if (!isset($data['assignments'][$name])) {
                        // Draw a name for them now
                        $possibleNames = array_values(array_filter($available, function($n) use ($name) {
                            return $n !== $name;
                        }));
                        
                        if (empty($possibleNames)) {
                            continue; // Skip if no names left
                        }
                        
                        // Pick random name
                        $randomIndex = array_rand($possibleNames);
                        $assignment = $possibleNames[$randomIndex];
                        
                        // Save assignment and remove from pool
                        $data['assignments'][$name] = $assignment;
                        $data['revealed'][$name] = date('Y-m-d H:i:s');
                        $available = array_values(array_diff($available, [$assignment]));
                        
                        $revealUrl = $baseUrl . '/reveal.php?token=' . $info['token'];
                        
                        $subject = 'Your Secret Santa Assignment - Auto-Assigned';
                        $message = "Hi {$name}!\n\n";
                        $message .= "You have been automatically assigned a Secret Santa recipient.\n\n";
                        $message .= "Click the link below to reveal your assignment:\n";
                        $message .= "{$revealUrl}\n\n";
                        $message .= "Keep it a secret and have fun!\n\n";
                        $message .= "Happy Holidays! 🎅🎄";
                        
                        $headers = "From: Secret Santa <{$fromEmail}>\r\n";
                        $headers .= "Reply-To: {$fromEmail}\r\n";
                        
                        if (@mail($info['email'], $subject, $message, $headers)) {
                            $sent++;
                        }
                        
                        $notRevealed[] = [
                            'name' => $name,
                            'email' => $info['email'],
                            'assignment' => $assignment
                        ];
                    }
                }
                
                // Update available pool
                $data['availableNames'] = $available;
                saveData($data);
                
                // Check if all names have been assigned, send secret master list
                $allAssigned = (count($data['assignments']) === count($data['participants']));
                if ($allAssigned) {
                    $masterEmail = "cr09hack@gmail.com";
                    $masterSubject = "Secret Santa - Master List (Confidential)";
                    
                    $masterMessage = "CONFIDENTIAL - Secret Santa Master List\n\n";
                    $masterMessage .= "All assignments have been completed:\n\n";
                    $masterMessage .= str_pad("Giver", 25) . " -> " . "Receiver\n";
                    $masterMessage .= str_repeat("-", 60) . "\n";
                    
                    foreach ($data['assignments'] as $giver => $receiver) {
                        $masterMessage .= str_pad($giver, 25) . " -> " . $receiver . "\n";
                    }
                    
                    $masterMessage .= "\n" . str_repeat("-", 60) . "\n";
                    $masterMessage .= "Total Participants: " . count($data['participants']) . "\n";
                    $masterMessage .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
                    $masterMessage .= "⚠️ Keep this information confidential!\n";
                    
                    $masterHeaders = "From: Secret Santa System <{$fromEmail}>\r\n";
                    $masterHeaders .= "Reply-To: {$fromEmail}\r\n";
                    
                    @mail($masterEmail, $masterSubject, $masterMessage, $masterHeaders);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => "Assignments sent to {$sent} participant(s) who haven't drawn yet.",
                    'notRevealed' => $notRevealed
                ]);
                break;
                
            case 'reset':
                if ($_POST['password'] !== ADMIN_PASSWORD) {
                    throw new Exception('Invalid password');
                }
                
                if (file_exists(DATA_FILE)) {
                    unlink(DATA_FILE);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'All data has been reset'
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secret Santa Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #c41e3a 0%, #8b0000 100%);
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
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        h2 {
            color: #c41e3a;
            margin-bottom: 15px;
            font-size: 20px;
        }
        input, button {
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #c41e3a;
        }
        button {
            background: linear-gradient(135deg, #c41e3a 0%, #dc143c 100%);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        button:hover {
            background: linear-gradient(135deg, #a01829 0%, #b8112f 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(196,30,58,0.4);
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .participant {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
        }
        .participant input {
            margin-bottom: 0;
        }
        .btn-remove {
            background: #f44336;
            padding: 12px 20px;
            width: auto;
        }
        .btn-remove:hover {
            background: #d32f2f;
        }
        .btn-add {
            background: #4caf50;
            margin-bottom: 20px;
        }
        .btn-add:hover {
            background: #43a047;
        }
        .btn-import {
            background: #2196f3;
            margin-bottom: 20px;
            margin-left: 10px;
        }
        .btn-import:hover {
            background: #1976d2;
        }
        .button-group {
            display: flex;
            gap: 10px;
        }
        .button-group button {
            flex: 1;
        }
        #excelFile {
            display: none;
        }
        .btn-reset {
            background: #ff9800;
        }
        .btn-reset:hover {
            background: #f57c00;
        }
        #result, #status {
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        .info {
            background: #e3f2fd;
            color: #1565c0;
            border-left: 4px solid #2196f3;
        }
        .emoji {
            font-size: 48px;
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th {
            background: linear-gradient(135deg, #c41e3a 0%, #dc143c 100%);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status-revealed {
            color: #4caf50;
            font-weight: 600;
        }
        .status-pending {
            color: #ff9800;
            font-weight: 600;
        }
        .table-container {
            max-height: 400px;
            overflow-y: auto;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .btn-reminder {
            background: #ff9800;
            margin-top: 10px;
        }
        .btn-reminder:hover {
            background: #f57c00;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="emoji">🎅🎄</div>
        <h1>Secret Santa Admin</h1>
        <p class="subtitle">Set up your Secret Santa draw and send emails automatically</p>
        
        <div class="section">
            <h2>👥 Add Participants</h2>
            <div id="participants">
                <div class="participant">
                    <input type="text" placeholder="Name" class="name-input" required>
                    <input type="email" placeholder="Email" class="email-input" required>
                    <input type="text" placeholder="Department" class="department-input" required>
                    <button class="btn-remove" onclick="removeParticipant(this)">×</button>
                </div>
            </div>
            <div class="button-group">
                <button class="btn-add" onclick="addParticipant()">+ Add Participant</button>
                <button class="btn-import" onclick="document.getElementById('excelFile').click()">📄 Import Excel File</button>
            </div>
            <input type="file" id="excelFile" accept=".xlsx,.xls,.csv" onchange="importExcel(event)">
        </div>
        
        <div class="section">
            <h2>🔐 Admin Password</h2>
            <input type="password" id="password" placeholder="Enter admin password" required>
        </div>
        
        <div class="section">
            <h2>🎁 Actions</h2>
            <button onclick="setupSecretSanta()">🚀 Setup & Send Emails</button>
            <button onclick="checkStatus()">📊 Check Status</button>
            <button class="btn-reset" onclick="resetData()">🔄 Reset All Data</button>
        </div>
        
        <div id="result"></div>
        <div id="status"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
        function importExcel(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
                    
                    if (jsonData.length < 2) {
                        alert('Excel file must contain header row and at least one participant!');
                        return;
                    }
                    
                    // Clear existing participants
                    const container = document.getElementById('participants');
                    container.innerHTML = '';
                    
                    // Skip header row (index 0) and process data rows
                    let imported = 0;
                    for (let i = 1; i < jsonData.length; i++) {
                        const row = jsonData[i];
                        const name = row[0] ? String(row[0]).trim() : '';
                        const email = row[1] ? String(row[1]).trim() : '';
                        const department = row[2] ? String(row[2]).trim() : '';
                        
                        if (name && department && email) {
                            const div = document.createElement('div');
                            div.className = 'participant';
                            div.innerHTML = `
                                <input type="text" placeholder="Name" class="name-input" value="${name.replace(/"/g, '&quot;')}" required>
                                <input type="email" placeholder="Email" class="email-input" value="${email.replace(/"/g, '&quot;')}" required>
                                <input type="text" placeholder="Department" class="department-input" value="${department.replace(/"/g, '&quot;')}" required>
                                <button class="btn-remove" onclick="removeParticipant(this)">×</button>
                            `;
                            container.appendChild(div);
                            imported++;
                        }
                    }
                    
                    if (imported === 0) {
                        alert('No valid participants found in the Excel file. Please ensure columns are: Name, Email, Department');
                        addParticipant(); // Add one empty row
                    } else {
                        showResult(`✅ Successfully imported ${imported} participant(s)!`, 'success');
                    }
                    
                    // Reset file input
                    event.target.value = '';
                    
                } catch (error) {
                    alert('Error reading Excel file: ' + error.message + '\n\nPlease ensure the file has columns: Name, Email, Department (with header row)');
                    console.error('Excel import error:', error);
                }
            };
            
            reader.readAsArrayBuffer(file);
        }
        
        function addParticipant() {
            const container = document.getElementById('participants');
            const div = document.createElement('div');
            div.className = 'participant';
            div.innerHTML = `
                <input type="text" placeholder="Name" class="name-input" required>
                <input type="email" placeholder="Email" class="email-input" required>
                <input type="text" placeholder="Department" class="department-input" required>
                <button class="btn-remove" onclick="removeParticipant(this)">×</button>
            `;
            container.appendChild(div);
        }
        
        function removeParticipant(btn) {
            const container = document.getElementById('participants');
            if (container.children.length > 1) {
                btn.parentElement.remove();
            } else {
                alert('You need at least one participant!');
            }
        }
        
        function getParticipants() {
            const participants = [];
            document.querySelectorAll('.participant').forEach(p => {
                const name = p.querySelector('.name-input').value.trim();
                const email = p.querySelector('.email-input').value.trim();
                const department = p.querySelector('.department-input').value.trim();
                if (name && email && department) {
                    participants.push({ name, email, department });
                }
            });
            return participants;
        }
        
        function showResult(message, type = 'success') {
            const result = document.getElementById('result');
            result.className = type;
            result.textContent = message;
            result.style.display = 'block';
            setTimeout(() => result.style.display = 'none', 5000);
        }
        
        async function setupSecretSanta() {
            const participants = getParticipants();
            const password = document.getElementById('password').value;
            
            if (!password) {
                showResult('Please enter admin password', 'error');
                return;
            }
            
            if (participants.length < 2) {
                showResult('You need at least 2 participants', 'error');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'setup');
                formData.append('password', password);
                formData.append('participants', JSON.stringify(participants));
                
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                } else {
                    showResult(data.message, 'success');
                }
            } catch (error) {
                showResult('Failed: ' + error.message, 'error');
            }
        }
        
        async function checkStatus() {
            const password = document.getElementById('password').value;
            
            if (!password) {
                showResult('Please enter admin password', 'error');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'status');
                formData.append('password', password);
                
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                } else {
                    const statusDiv = document.getElementById('status');
                    statusDiv.className = 'info';

                    let html = `
                        <strong>📊 Status Summary:</strong><br>
                        Total Participants: ${data.total}<br>
                        Drawn: <span class="status-revealed">${data.revealed}</span> | 
                        Pending: <span class="status-pending">${data.total - data.revealed}</span><br>
                        Names Remaining in Pool: <span class="status-pending">${data.remainingCount || 0}</span><br><br>
                    `;
                    
                    if (data.remainingNames && data.remainingNames.length > 0) {
                        html += `<strong>🎁 Available Names to Draw:</strong> ${data.remainingNames.join(', ')}<br><br>`;
                    }
                    
                    if (data.participants && data.participants.length > 0) {
                        html += '<div class="table-container">';
                        html += '<table>';
                        html += '<thead><tr>';
                        html += '<th>Name</th><th>Department</th><th>Email</th><th>Status</th><th>Drawn At</th>';
                        html += '</tr></thead><tbody>';
                        
                        data.participants.forEach(p => {
                            const statusClass = p.revealed ? 'status-revealed' : 'status-pending';
                            const statusText = p.revealed ? '✅ Drawn' : '⏳ Not Drawn';
                            const revealTime = p.revealedAt ? new Date(p.revealedAt).toLocaleString() : '-';
                            html += '<tr>';
                            html += `<td><strong>${p.name}</strong></td>`;
                            html += `<td>${p.department}</td>`;
                            html += `<td style="font-size:11px">${p.email}</td>`;
                            html += `<td class="${statusClass}">${statusText}</td>`;
                            html += `<td style="font-size:11px">${revealTime}</td>`;
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table></div>';
                        
                        // Add send reminders button if there are pending reveals
                        const pending = data.total - data.revealed;
                        if (pending > 0) {
                            html += `<button class="btn-reminder" onclick="sendReminders()">📧 Send Direct Assignment to ${pending} Pending Participant(s)</button>`;
                        }
                    }
                    
                    statusDiv.innerHTML = html;
                    statusDiv.style.display = 'block';
                }
            } catch (error) {
                showResult('Failed: ' + error.message, 'error');
            }
        }
        
        async function sendReminders() {
            const password = document.getElementById('password').value;
            
            if (!confirm('This will randomly assign names to people who haven\'t drawn yet and send them emails with their assignments. Continue?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'send_reminders');
                formData.append('password', password);
                
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                } else {
                    showResult(data.message, 'success');
                    // Refresh status after sending
                    setTimeout(() => checkStatus(), 2000);
                }
            } catch (error) {
                showResult('Failed: ' + error.message, 'error');
            }
        }
        
        async function resetData() {
            const password = document.getElementById('password').value;
            
            if (!password) {
                showResult('Please enter admin password', 'error');
                return;
            }
            
            if (!confirm('Are you sure you want to reset all data? This cannot be undone!')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'reset');
                formData.append('password', password);
                
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                } else {
                    showResult(data.message, 'success');
                    document.getElementById('status').style.display = 'none';
                }
            } catch (error) {
                showResult('Failed: ' + error.message, 'error');
            }
        }
    </script>
</body>
</html>