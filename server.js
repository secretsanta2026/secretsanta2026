const express = require('express');
const nodemailer = require('nodemailer');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;
const APP_URL = process.env.APP_URL || `http://localhost:${PORT}`;

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static('public'));

// Data storage
const DATA_FILE = path.join(__dirname, 'data.json');

// Initialize or load data
function loadData() {
  if (fs.existsSync(DATA_FILE)) {
    return JSON.parse(fs.readFileSync(DATA_FILE, 'utf8'));
  }
  return { participants: {}, assignments: {}, revealed: {} };
}

function saveData(data) {
  fs.writeFileSync(DATA_FILE, JSON.stringify(data, null, 2));
}

// Secret Santa drawing algorithm (ensures no one gets themselves)
function performDraw(participants) {
  const names = Object.keys(participants);
  if (names.length < 2) {
    throw new Error('Need at least 2 participants');
  }

  let assignments = {};
  let attempts = 0;
  const maxAttempts = 1000;

  while (attempts < maxAttempts) {
    assignments = {};
    const availableRecipients = [...names];
    let valid = true;

    for (let giver of names) {
      // Filter out the giver themselves
      const possibleRecipients = availableRecipients.filter(r => r !== giver);
      
      if (possibleRecipients.length === 0) {
        valid = false;
        break;
      }

      // Random selection
      const randomIndex = Math.floor(Math.random() * possibleRecipients.length);
      const recipient = possibleRecipients[randomIndex];
      
      assignments[giver] = recipient;
      availableRecipients.splice(availableRecipients.indexOf(recipient), 1);
    }

    if (valid) {
      return assignments;
    }
    attempts++;
  }

  throw new Error('Could not generate valid Secret Santa assignments');
}

// Email configuration
function createTransporter() {
  return nodemailer.createTransport({
    host: process.env.EMAIL_HOST,
    port: process.env.EMAIL_PORT,
    secure: false,
    auth: {
      user: process.env.EMAIL_USER,
      pass: process.env.EMAIL_PASS,
    },
  });
}

// Generate unique token for each participant
function generateToken() {
  return crypto.randomBytes(32).toString('hex');
}

// Admin endpoint to set up Secret Santa
app.post('/api/admin/setup', async (req, res) => {
  try {
    const { participants } = req.body; // Array of {name, email}
    
    if (!participants || participants.length < 2) {
      return res.status(400).json({ error: 'Need at least 2 participants' });
    }

    // Create participant map with tokens
    const participantMap = {};
    participants.forEach(p => {
      const token = generateToken();
      participantMap[p.name] = {
        email: p.email,
        token: token
      };
    });

    // Perform the draw
    const assignments = performDraw(participantMap);

    // Save data
    const data = {
      participants: participantMap,
      assignments: assignments,
      revealed: {}
    };
    saveData(data);

    // Send emails
    const transporter = createTransporter();
    const emailPromises = Object.keys(participantMap).map(name => {
      const participant = participantMap[name];
      const revealUrl = `${APP_URL}/reveal?token=${participant.token}`;
      
      const mailOptions = {
        from: process.env.EMAIL_USER,
        to: participant.email,
        subject: '🎅 Your Secret Santa Assignment',
        html: `
          <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;">
            <div style="background-color: #dc3545; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
              <h1 style="margin: 0; font-size: 32px;">🎅 Secret Santa 🎄</h1>
            </div>
            <div style="background-color: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
              <p style="font-size: 18px; color: #333;">Hi <strong>${name}</strong>!</p>
              <p style="font-size: 16px; color: #666; line-height: 1.6;">
                You're invited to participate in our Secret Santa gift exchange! 🎁
              </p>
              <p style="font-size: 16px; color: #666; line-height: 1.6;">
                Click the button below to reveal who you'll be buying a gift for. Remember, it's a secret! 🤫
              </p>
              <div style="text-align: center; margin: 30px 0;">
                <a href="${revealUrl}" style="display: inline-block; background-color: #28a745; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-size: 18px; font-weight: bold;">
                  Reveal Your Secret Santa 🎁
                </a>
              </div>
              <p style="font-size: 14px; color: #999; margin-top: 30px;">
                ⚠️ This link is unique to you and can only be used once. Keep it private!
              </p>
            </div>
          </div>
        `
      };
      
      return transporter.sendMail(mailOptions);
    });

    await Promise.all(emailPromises);

    res.json({ 
      success: true, 
      message: `Secret Santa set up successfully! ${participants.length} emails sent.`,
      count: participants.length
    });

  } catch (error) {
    console.error('Setup error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Reveal endpoint (used by participants)
app.get('/api/reveal', (req, res) => {
  try {
    const { token } = req.query;
    
    if (!token) {
      return res.status(400).json({ error: 'Token is required' });
    }

    const data = loadData();
    
    // Find participant by token
    let giverName = null;
    for (let name in data.participants) {
      if (data.participants[name].token === token) {
        giverName = name;
        break;
      }
    }

    if (!giverName) {
      return res.status(404).json({ error: 'Invalid token' });
    }

    // Check if already revealed
    if (data.revealed[giverName]) {
      return res.json({
        giver: giverName,
        recipient: data.assignments[giverName],
        alreadyRevealed: true
      });
    }

    // Mark as revealed
    data.revealed[giverName] = new Date().toISOString();
    saveData(data);

    res.json({
      giver: giverName,
      recipient: data.assignments[giverName],
      alreadyRevealed: false
    });

  } catch (error) {
    console.error('Reveal error:', error);
    res.status(500).json({ error: 'Server error' });
  }
});

// Status endpoint for admin
app.get('/api/admin/status', (req, res) => {
  try {
    const data = loadData();
    const total = Object.keys(data.participants).length;
    const revealed = Object.keys(data.revealed).length;
    
    const participants = Object.keys(data.participants).map(name => ({
      name,
      email: data.participants[name].email,
      hasRevealed: !!data.revealed[name],
      revealedAt: data.revealed[name] || null
    }));

    res.json({
      total,
      revealed,
      participants
    });
  } catch (error) {
    res.status(500).json({ error: 'Server error' });
  }
});

// Reset endpoint for admin (use with caution)
app.post('/api/admin/reset', (req, res) => {
  try {
    const data = { participants: {}, assignments: {}, revealed: {} };
    saveData(data);
    res.json({ success: true, message: 'Secret Santa reset successfully' });
  } catch (error) {
    res.status(500).json({ error: 'Server error' });
  }
});

// Serve reveal page when user visits /reveal (so links like /reveal?token=... work)
app.get('/reveal', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'reveal.html'));
});
app.listen(PORT, () => {
  console.log(`🎅 Secret Santa server running on ${APP_URL}`);
  console.log(`📧 Make sure to configure your email settings in .env file`);
});
