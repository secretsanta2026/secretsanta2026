import { loadData, saveData } from '../../lib/storage';

export default async function handler(req, res) {
  const { token } = req.query;
  if (!token) return res.status(400).json({ error: 'Token is required' });

  try {
    const data = await loadData();
    let giver = null;
    for (const name in data.participants) {
      if (data.participants[name].token === token) { giver = name; break; }
    }
    if (!giver) return res.status(404).json({ error: 'Invalid token' });
    if (data.revealed[giver]) {
      return res.json({ giver, recipient: data.assignments[giver], alreadyRevealed: true });
    }
    data.revealed[giver] = new Date().toISOString();
    await saveData(data);
    return res.json({ giver, recipient: data.assignments[giver], alreadyRevealed: false });
  } catch (err) {
    console.error('reveal error', err);
    return res.status(500).json({ error: 'Server error' });
  }
}
