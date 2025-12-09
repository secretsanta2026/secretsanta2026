import fs from 'fs';
import path from 'path';

const DATA_FILE = path.join(process.cwd(), 'data.json');

function saveData(data) {
  fs.writeFileSync(DATA_FILE, JSON.stringify(data, null, 2));
}

export default function handler(req, res) {
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method not allowed' });
  try {
    const data = { participants: {}, assignments: {}, revealed: {} };
    saveData(data);
    res.json({ success: true, message: 'Secret Santa reset successfully' });
  } catch (err) {
    res.status(500).json({ error: 'Server error' });
  }
}
