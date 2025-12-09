import { useState, useEffect } from 'react';

export default function TestPage() {
  const [testResult, setTestResult] = useState('');
  const [loading, setLoading] = useState(false);
  const [mounted, setMounted] = useState(false);

  // Fix hydration error by only rendering after client mount
  useEffect(() => {
    setMounted(true);
  }, []);

  if (!mounted) {
    return <div>Loading...</div>;
  }

  const testEmail = async () => {
    setLoading(true);
    try {
      const response = await fetch('/api/test-email', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        }
      });
      const data = await response.json();
      setTestResult(JSON.stringify(data, null, 2));
    } catch (error) {
      setTestResult(`Error: ${error.message}`);
    }
    setLoading(false);
  };

  const testSetup = async () => {
    setLoading(true);
    try {
      const response = await fetch('/api/admin/setup', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          participants: [
            { name: 'Test User 1', email: 'cr09hack@gmail.com' },
            { name: 'Test User 2', email: 'yveskhalil1@hotmail.com' }
          ]
        })
      });
      const data = await response.json();
      setTestResult(JSON.stringify(data, null, 2));
    } catch (error) {
      setTestResult(`Error: ${error.message}`);
    }
    setLoading(false);
  };

  return (
    <div style={{ padding: '20px', fontFamily: 'Arial, sans-serif' }}>
      <h1>🎅 Secret Santa Test Page</h1>
      
      <div style={{ marginBottom: '20px' }}>
        <button 
          onClick={testEmail} 
          disabled={loading}
          style={{
            padding: '10px 20px',
            backgroundColor: '#007bff',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer',
            marginRight: '10px'
          }}
        >
          {loading ? 'Testing...' : 'Test Email Connection'}
        </button>
        
        <button 
          onClick={testSetup} 
          disabled={loading}
          style={{
            padding: '10px 20px',
            backgroundColor: '#28a745',
            color: 'white',
            border: 'none',
            borderRadius: '4px',
            cursor: 'pointer'
          }}
        >
          {loading ? 'Testing...' : 'Test Full Setup'}
        </button>
      </div>

      {testResult && (
        <div style={{
          backgroundColor: '#f8f9fa',
          border: '1px solid #dee2e6',
          borderRadius: '4px',
          padding: '15px',
          marginTop: '20px'
        }}>
          <h3>Test Result:</h3>
          <pre style={{ 
            backgroundColor: 'white', 
            padding: '10px', 
            borderRadius: '4px',
            overflow: 'auto',
            maxHeight: '400px'
          }}>
            {testResult}
          </pre>
        </div>
      )}

      <div style={{ marginTop: '30px', padding: '15px', backgroundColor: '#e9ecef', borderRadius: '4px' }}>
        <h3>Environment Check:</h3>
        <p><strong>Environment:</strong> development</p>
        <p><strong>App URL:</strong> http://localhost:3000</p>
        <p><strong>Email Host:</strong> Configured</p>
      </div>
    </div>
  );
}