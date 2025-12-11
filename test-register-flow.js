// Test registration flow
const axios = require('axios');

const API_URL = 'http://localhost:8000/api';
const testEmail = 'testuser' + Date.now() + '@test.com';
const testUsername = 'testuser' + Date.now().toString().slice(-6);

(async () => {
  try {
    console.log('\n🔵 Step 1: Register Step 1');
    console.log(`Email: ${testEmail}`);
    console.log(`Username: ${testUsername}`);
    
    const step1Response = await axios.post(`${API_URL}/register-step1`, {
      username: testUsername,
      email: testEmail,
      password: 'TestPassword123',
      password_confirmation: 'TestPassword123'
    }, {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    });
    
    console.log('\n✅ Step 1 Response Status:', step1Response.status);
    console.log('✅ Step 1 Response Data:', JSON.stringify(step1Response.data, null, 2));
    
    if (step1Response.status >= 200 && step1Response.status < 300) {
      console.log('\n✅ Step 1 SUCCESS - Should advance to step 2');
    } else {
      console.log('\n❌ Step 1 FAILED - Status not in 200-299 range');
    }
    
  } catch (error) {
    if (error.response) {
      console.error('\n❌ Error:', error.response.status);
      console.error('Error data:', JSON.stringify(error.response.data, null, 2));
    } else {
      console.error('\n❌ Error:', error.message);
    }
  }
})();
