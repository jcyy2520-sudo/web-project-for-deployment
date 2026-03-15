import React, { useState, useEffect } from 'react';

const ConnectionTest = () => {
  const [tests, setTests] = useState([]);
  const [loading, setLoading] = useState(true);
  
  // In development, use the Vite proxy (relative path). In production, use env variable.
  const API_URL = import.meta.env.PROD
    ? (import.meta.env.VITE_API_URL || 'http://localhost:8000/api')
    : '/api';

  useEffect(() => {
    const runTests = async () => {
      const testEndpoints = [
        { name: 'Base API', path: '/' },
        { name: 'Basic Test', path: '/test' },
        { name: 'Database', path: '/test-db' },
        { name: 'Services', path: '/services' },
        { name: 'Health', path: '/health' }
      ];

      const results = [];

      for (const test of testEndpoints) {
        try {
          const startTime = Date.now();
          const response = await fetch(`${API_URL}${test.path}`, {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json'
            }
          });
          
          const responseTime = Date.now() - startTime;
          const data = await response.json();

          results.push({
            name: test.name,
            status: response.ok ? '✅ PASS' : '❌ FAIL',
            time: `${responseTime}ms`,
            statusCode: response.status,
            data: data,
            error: null
          });
        } catch (error) {
          results.push({
            name: test.name,
            status: '❌ FAIL',
            time: 'N/A',
            statusCode: 'Network Error',
            data: null,
            error: error.message
          });
        }
      }

      setTests(results);
      setLoading(false);
    };

    runTests();
  }, [API_URL]);

  if (loading) {
    return <div style={{ padding: '20px', textAlign: 'center' }}>Testing connections...</div>;
  }

  return (
    <div style={{ maxWidth: '800px', margin: '0 auto', padding: '20px', fontFamily: 'Arial' }}>
      <h1>🔌 Backend Connection Test</h1>
      
      <div style={{ 
        background: '#f5f5f5', 
        padding: '15px', 
        borderRadius: '8px', 
        marginBottom: '20px',
        fontSize: '14px'
      }}>
        <strong>API Base URL:</strong> {API_URL}<br/>
        <strong>Environment:</strong> {import.meta.env.MODE}
      </div>

      <div style={{ marginBottom: '20px' }}>
        {tests.map((test, index) => (
          <div 
            key={index}
            style={{
              padding: '15px',
              marginBottom: '10px',
              border: `2px solid ${test.status.includes('✅') ? '#4CAF50' : '#f44336'}`,
              borderRadius: '8px',
              background: test.status.includes('✅') ? '#f0f9f0' : '#fff0f0'
            }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <h3 style={{ margin: 0, fontSize: '16px' }}>
                {test.name} <span style={{ fontSize: '12px', color: '#666' }}>({test.statusCode})</span>
              </h3>
              <div>
                <span style={{
                  padding: '4px 8px',
                  borderRadius: '4px',
                  background: test.status.includes('✅') ? '#4CAF50' : '#f44336',
                  color: 'white',
                  fontWeight: 'bold',
                  fontSize: '12px'
                }}>
                  {test.status}
                </span>
                <span style={{ marginLeft: '10px', color: '#666', fontSize: '12px' }}>
                  {test.time}
                </span>
              </div>
            </div>

            {test.data && (
              <div style={{ marginTop: '10px' }}>
                <pre style={{
                  background: '#fff',
                  padding: '10px',
                  borderRadius: '4px',
                  maxHeight: '100px',
                  overflow: 'auto',
                  fontSize: '11px',
                  margin: 0
                }}>
                  {JSON.stringify(test.data, null, 2)}
                </pre>
              </div>
            )}

            {test.error && (
              <div style={{ marginTop: '10px', color: '#d32f2f', fontSize: '12px' }}>
                <strong>Error:</strong> {test.error}
              </div>
            )}
          </div>
        ))}
      </div>

      <div style={{
        padding: '15px',
        background: tests.every(t => t.status.includes('✅')) ? '#e8f5e9' : '#ffebee',
        borderRadius: '8px',
        border: `2px solid ${tests.every(t => t.status.includes('✅')) ? '#4CAF50' : '#f44336'}`
      }}>
        <h3 style={{ marginTop: 0 }}>Test Summary</h3>
        <p style={{ marginBottom: '10px' }}>
          <strong>Passed:</strong> {tests.filter(t => t.status.includes('✅')).length} / {tests.length}
        </p>
        {tests.every(t => t.status.includes('✅')) ? (
          <div style={{ color: '#2e7d32', fontWeight: 'bold' }}>
            🎉 SUCCESS! Your Vite frontend is connected to Laravel backend!
          </div>
        ) : (
          <div style={{ color: '#c62828', fontWeight: 'bold' }}>
            ⚠️ Some tests failed. Check your API endpoints.
          </div>
        )}
      </div>

      <div style={{ marginTop: '20px', fontSize: '12px', color: '#666' }}>
        <h4>Expected Results:</h4>
        <ul>
          <li><strong>Base API</strong>: Should return API info with endpoints list</li>
          <li><strong>Basic Test</strong>: Should return "API is working!"</li>
          <li><strong>Database</strong>: Should show "Database connected successfully!"</li>
          <li><strong>Services</strong>: Should return services data with "success": true</li>
          <li><strong>Health</strong>: Should return "healthy" status</li>
        </ul>
      </div>
    </div>
  );
};

export default ConnectionTest;
