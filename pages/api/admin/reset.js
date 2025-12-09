import { saveData } from '../../../lib/storage';

export default async function handler(req, res) {
  if (req.method !== 'POST') return res.status(405).json({ error: 'Method not allowed' });
  try {
    const data = { participants: {}, assignments: {}, revealed: {} };
    await saveData(data);
    res.json({ success: true, message: 'Secret Santa reset successfully' });
  } catch (err) {
    console.error('reset error', err);
    res.status(500).json({ error: 'Server error' });
  }
}
