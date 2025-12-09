import fs from 'fs';
import path from 'path';

const DATA_FILE = path.join(process.cwd(), 'data.json');

function loadData() {
  if (fs.existsSync(DATA_FILE)) return JSON.parse(fs.readFileSync(DATA_FILE, 'utf8'));
  return { participants: {}, assignments: {}, revealed: {} };
}

export default function handler(req, res) {
  try {
    const data = loadData();
    const total = Object.keys(data.participants).length;
    const revealed = Object.keys(data.revealed).length;
    const participants = Object.keys(data.participants).map(name => ({ name, email: data.participants[name].email, hasRevealed: !!data.revealed[name], revealedAt: data.revealed[name] || null }));
    res.json({ total, revealed, participants });
  } catch (err) {
    res.status(500).json({ error: 'Server error' });
  }
}
