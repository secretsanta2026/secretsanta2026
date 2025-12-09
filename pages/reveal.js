import { useEffect, useState } from 'react';
import { useRouter } from 'next/router';

export default function Reveal() {
  const router = useRouter();
  const { token } = router.query;
  const [state, setState] = useState({ loading: true });

  useEffect(() => {
    if (!token) return;
    (async () => {
      try {
        const res = await fetch(`/api/reveal?token=${encodeURIComponent(token)}`);
        const json = await res.json();
        if (!res.ok) {
          setState({ error: json.error || 'Invalid token' });
        } else {
          setState({ result: json });
        }
      } catch (e) {
        setState({ error: 'Network error' });
      }
    })();
  }, [token]);

  if (!token) return <div style={{ padding: 24 }}>Missing token in URL.</div>;
  if (state.loading && !state.result && !state.error) return <div style={{ padding: 24 }}>Loading...</div>;
  if (state.error) return <div style={{ padding: 24, color: 'red' }}>{state.error}</div>;

  const { recipient, alreadyRevealed } = state.result;
  return (
    <div style={{ fontFamily: 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif', padding: 24 }}>
      <h1>Secret Santa</h1>
      <div style={{ marginTop: 24, padding: 20, borderRadius: 8, background: alreadyRevealed ? '#667eea' : '#f093fb' }}>
        <div style={{ color: '#fff', fontSize: 18 }}>{alreadyRevealed ? 'You already revealed' : "You're Secret Santa for:"}</div>
        <div style={{ color: '#fff', fontSize: 36, fontWeight: 'bold' }}>{recipient}</div>
      </div>
    </div>
  );
}
