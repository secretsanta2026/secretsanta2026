import { loadData } from '../../../lib/storage';

export default async function handler(req, res) {
  try {
    const data = await loadData();
    const total = Object.keys(data.participants).length;
    const revealed = Object.keys(data.revealed || {}).length;
    const participants = Object.keys(data.participants).map(name => ({ name, email: data.participants[name].email, hasRevealed: !!data.revealed[name], revealedAt: data.revealed[name] || null }));
    res.json({ total, revealed, participants });
  } catch (err) {
    console.error('status error', err);
    res.status(500).json({ error: 'Server error' });
  }
}
