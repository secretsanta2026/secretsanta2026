import fs from 'fs';
import path from 'path';

const DATA_FILE = path.join(process.cwd(), 'data.json');

function loadData() {
  if (fs.existsSync(DATA_FILE)) return JSON.parse(fs.readFileSync(DATA_FILE, 'utf8'));
  return { participants: {}, assignments: {}, revealed: {} };
}

function saveData(data) {
  fs.writeFileSync(DATA_FILE, JSON.stringify(data, null, 2));
}

export default function handler(req, res) {
  const { token } = req.query;
  if (!token) return res.status(400).json({ error: 'Token is required' });

  try {
    const data = loadData();
    let giver = null;
    for (const name in data.participants) {
      if (data.participants[name].token === token) { giver = name; break; }
    }
    if (!giver) return res.status(404).json({ error: 'Invalid token' });
    if (data.revealed[giver]) {
      return res.json({ giver, recipient: data.assignments[giver], alreadyRevealed: true });
    }
    data.revealed[giver] = new Date().toISOString();
    saveData(data);
    return res.json({ giver, recipient: data.assignments[giver], alreadyRevealed: false });
  } catch (err) {
    console.error('reveal error', err);
    return res.status(500).json({ error: 'Server error' });
  }
}
