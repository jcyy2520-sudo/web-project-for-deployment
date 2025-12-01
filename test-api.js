#!/usr/bin/env node

// Test script to verify API response for unavailable dates
const http = require('http');

const options = {
  hostname: '127.0.0.1',
  port: 8000,
  path: '/api/unavailable-dates',
  method: 'GET',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  }
};

const req = http.request(options, (res) => {
  let data = '';
  
  res.on('data', (chunk) => {
    data += chunk;
  });
  
  res.on('end', () => {
    console.log('Status:', res.statusCode);
    console.log('Headers:', res.headers);
    
    try {
      const json = JSON.parse(data);
      console.log('\n✅ API Response:');
      console.log(JSON.stringify(json, null, 2));
      
      if (json.data && Array.isArray(json.data)) {
        console.log('\n✅ Data array found with', json.data.length, 'items:');
        json.data.forEach((item, i) => {
          console.log(`  [${i}] ${item.date} - ${item.reason}`);
        });
      }
    } catch (e) {
      console.log('Response body:', data);
    }
  });
});

req.on('error', (e) => {
  console.error('Error:', e);
  process.exit(1);
});

req.end();
