import crypto from 'crypto';
import { loadData, saveData } from '../../../lib/storage';
const nodemailer = require('nodemailer');

// Load environment variables for local development
if (process.env.NODE_ENV !== 'production') {
  require('dotenv').config();
}

function generateToken() {
  return crypto.randomBytes(32).toString('hex');
}

function performDraw(participants) {
  const names = Object.keys(participants);
  if (names.length < 2) throw new Error('Need at least 2 participants');

  let attempts = 0;
  const maxAttempts = 1000;
  while (attempts < maxAttempts) {
    const assignments = {};
    const available = [...names];
    let valid = true;
    for (const giver of names) {
      const possible = available.filter(r => r !== giver);
      if (possible.length === 0) { valid = false; break; }
      const recipient = possible[Math.floor(Math.random() * possible.length)];
      assignments[giver] = recipient;
      available.splice(available.indexOf(recipient), 1);
    }
    if (valid) return assignments;
    attempts++;
  }
  throw new Error('Could not generate valid assignments');
}

function createTransporter() {
  // Debug environment variables
  console.log('Email config:', {
    host: process.env.EMAIL_HOST || 'smtp.gmail.com',
    port: process.env.EMAIL_PORT || '587',
    user: process.env.EMAIL_USER ? '***@' + process.env.EMAIL_USER.split('@')[1] : 'undefined',
    pass: process.env.EMAIL_PASS ? 'configured' : 'missing'
  });

  const config = {
    host: process.env.EMAIL_HOST || 'smtp.gmail.com',
    port: parseInt(process.env.EMAIL_PORT) || 587,
    secure: false, // Use STARTTLS for port 587
    auth: {
      user: process.env.EMAIL_USER,
      pass: process.env.EMAIL_PASS,
    },
    tls: {
      rejectUnauthorized: false
    },
    connectionTimeout: 60000,
    greetingTimeout: 30000,
    socketTimeout: 60000
  };

  return nodemailer.createTransporter(config);
}

export default async function handler(req, res) {
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method not allowed' });
  const { participants } = req.body;
  if (!participants || participants.length < 2) return res.status(400).json({ error: 'Need at least 2 participants' });

  try {
    const participantMap = {};
    participants.forEach(p => {
      const token = generateToken();
      participantMap[p.name] = { email: p.email, token };
    });

    const assignments = performDraw(participantMap);

    const data = { participants: participantMap, assignments, revealed: {} };
    await saveData(data);

    // send emails
    const transporter = createTransporter();
    const appUrl = process.env.APP_URL || '';
    const promises = Object.keys(participantMap).map(name => {
      const p = participantMap[name];
      const revealUrl = `${appUrl}/reveal?token=${p.token}`;
      const mail = {
        from: process.env.EMAIL_USER,
        to: p.email,
        subject: '🎅 Your Secret Santa Assignment',
        html: `<p>Hi <strong>${name}</strong>,</p><p>Click <a href="${revealUrl}">this link</a> to reveal your Secret Santa.</p>`
      };
      return transporter.sendMail(mail);
    });

    await Promise.all(promises);
    return res.json({ success: true, message: `Secret Santa set up and emails sent (${participants.length})` });
  } catch (err) {
    console.error('setup error', err);
    return res.status(500).json({ error: err.message });
  }
}
