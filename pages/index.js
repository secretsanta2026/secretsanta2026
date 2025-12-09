import { useState, useEffect } from 'react';

export default function Admin() {
  const [rows, setRows] = useState([{ name: '', email: '' }]);
  const [message, setMessage] = useState(null);
  const [status, setStatus] = useState(null);

  useEffect(() => {
    loadStatus();
  }, []);

  function addRow() {
    setRows(prev => [...prev, { name: '', email: '' }]);
  }

  function removeRow(i) {
    setRows(prev => prev.filter((_, idx) => idx !== i));
  }

  function updateRow(i, field, value) {
    setRows(prev => prev.map((r, idx) => idx === i ? { ...r, [field]: value } : r));
  }

  async function setup() {
    const participants = rows.map(r => ({ name: r.name.trim(), email: r.email.trim() })).filter(r => r.name && r.email);
    if (participants.length < 2) {
      setMessage({ type: 'error', text: 'Add at least 2 participants with emails.' });
      return;
    }

    try {
      const res = await fetch('/api/admin/setup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ participants })
      });
      const json = await res.json();
      if (res.ok) {
        setMessage({ type: 'success', text: json.message });
        loadStatus();
      } else {
        setMessage({ type: 'error', text: json.error || 'Failed' });
      }
    } catch (err) {
      setMessage({ type: 'error', text: 'Network error' });
      console.error(err);
    }
  }

  async function loadStatus() {
    try {
      const res = await fetch('/api/admin/status');
      const json = await res.json();
      setStatus(json);
    } catch (e) {
      setStatus(null);
    }
  }

  async function resetAll() {
    if (!confirm('Reset everything? This will delete participants and assignments.')) return;
    const res = await fetch('/api/admin/reset', { method: 'POST' });
    const j = await res.json();
    if (res.ok) {
      setMessage({ type: 'success', text: j.message });
      setRows([{ name: '', email: '' }]);
      loadStatus();
    } else {
      setMessage({ type: 'error', text: j.error || 'Failed' });
    }
  }

  return (
    <div style={{ fontFamily: 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif', padding: 24 }}>
      <h1>🎅 Secret Santa Admin</h1>
      {message && (
        <div style={{ padding: 12, background: message.type === 'error' ? '#f8d7da' : '#d4edda', marginBottom: 12 }}>{message.text}</div>
      )}

      <div style={{ marginBottom: 16 }}>
        <h3>Add participants</h3>
        {rows.map((r, i) => (
          <div key={i} style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
            <input placeholder="Name" value={r.name} onChange={e => updateRow(i, 'name', e.target.value)} />
            <input placeholder="Email" value={r.email} onChange={e => updateRow(i, 'email', e.target.value)} />
            <button onClick={() => removeRow(i)}>Remove</button>
          </div>
        ))}
        <button onClick={addRow}>+ Add</button>
        <div style={{ marginTop: 12 }}>
          <button onClick={setup} style={{ marginRight: 8 }}>🎁 Start Secret Santa & Send Emails</button>
          <button onClick={loadStatus} style={{ marginRight: 8 }}>🔄 Refresh Status</button>
          <button onClick={resetAll}>⚠️ Reset</button>
        </div>
      </div>

      <div>
        <h3>Status</h3>
        {!status && <div>No data yet</div>}
        {status && (
          <div>
            <div>Total: {status.total} — Revealed: {status.revealed}</div>
            <ul>
              {status.participants.map(p => (
                <li key={p.name}>{p.name} — {p.email} — {p.hasRevealed ? 'Revealed' : 'Pending'}</li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </div>
  );
}
