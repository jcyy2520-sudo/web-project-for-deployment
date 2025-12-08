#!/usr/bin/env node

const http = require('http');

/**
 * Test Shift Report API endpoint
 */
async function testShiftReport() {
  const today = new Date().toISOString().split('T')[0];
  
  const options = {
    hostname: 'localhost',
    port: 8000,
    path: `/api/cashier/shift-reports?from=${today}&to=${today}`,
    method: 'GET',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
      // Note: In production, would need Authorization header with token
    }
  };

  return new Promise((resolve, reject) => {
    const req = http.request(options, (res) => {
      let data = '';

      res.on('data', (chunk) => {
        data += chunk;
      });

      res.on('end', () => {
        console.log(`Status: ${res.statusCode}`);
        console.log('Response:');
        try {
          console.log(JSON.stringify(JSON.parse(data), null, 2));
          resolve();
        } catch (e) {
          console.log(data);
          resolve();
        }
      });
    });

    req.on('error', (error) => {
      console.error('Request error:', error);
      reject(error);
    });

    req.end();
  });
}

console.log('Testing Shift Report API Endpoint...\n');
testShiftReport().catch(console.error);
