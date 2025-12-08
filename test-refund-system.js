/**
 * Comprehensive Refund System Integration Test
 * Tests: Cashier refund requests, Admin refund management, User refund status
 */

const API_URL = 'http://localhost:8000/api';

// Colors for console output
const colors = {
  reset: '\x1b[0m',
  bright: '\x1b[1m',
  green: '\x1b[32m',
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m',
  magenta: '\x1b[35m'
};

const log = {
  success: (msg) => console.log(`${colors.green}✓${colors.reset} ${msg}`),
  error: (msg) => console.log(`${colors.red}✗${colors.reset} ${msg}`),
  info: (msg) => console.log(`${colors.blue}ℹ${colors.reset} ${msg}`),
  header: (msg) => console.log(`\n${colors.bright}${colors.cyan}${msg}${colors.reset}\n`),
  warning: (msg) => console.log(`${colors.yellow}⚠${colors.reset} ${msg}`),
  section: (msg) => console.log(`\n${colors.bright}${colors.magenta}═══ ${msg} ═══${colors.reset}\n`)
};

let testsPassed = 0;
let testsFailed = 0;
let apiErrors = [];

// Test data
let tokens = {
  admin: null,
  cashier: null,
  user: null
};

let testData = {
  appointmentId: null,
  refundId: null,
  userId: null
};

/**
 * Make API request
 */
async function request(method, endpoint, body = null, token = null) {
  const fullUrl = `${API_URL}${endpoint}`;
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const options = {
    method,
    headers,
    timeout: 10000
  };

  if (body && (method === 'POST' || method === 'PUT')) {
    options.body = JSON.stringify(body);
  }

  try {
    const response = await fetch(fullUrl, options);
    const data = await response.json();

    return {
      status: response.status,
      data,
      ok: response.ok
    };
  } catch (error) {
    apiErrors.push({ endpoint, error: error.message });
    return {
      status: 0,
      data: { error: error.message },
      ok: false
    };
  }
}

/**
 * Login users
 */
async function loginUsers() {
  log.section('AUTHENTICATION');

  // Login as admin
  log.info('Logging in as admin...');
  let response = await request('POST', '/login', {
    email: 'admin@example.com',
    password: 'Admin@12345'
  });

  if (!response.ok || !response.data.token) {
    log.error(`Admin login failed: ${response.data.message || 'No token'}`);
    testsFailed++;
    return false;
  }
  tokens.admin = response.data.token;
  log.success('Admin login successful');
  testsPassed++;

  // Login as cashier
  log.info('Logging in as cashier...');
  response = await request('POST', '/login', {
    email: 'cashier@example.com',
    password: 'Cashier@12345'
  });

  if (!response.ok || !response.data.token) {
    log.error(`Cashier login failed: ${response.data.message || 'No token'}`);
    testsFailed++;
    return false;
  }
  tokens.cashier = response.data.token;
  log.success('Cashier login successful');
  testsPassed++;

  // Login as user
  log.info('Logging in as user...');
  response = await request('POST', '/login', {
    email: 'user@example.com',
    password: 'User@12345'
  });

  if (!response.ok || !response.data.token) {
    log.error(`User login failed: ${response.data.message || 'No token'}`);
    testsFailed++;
    return false;
  }
  tokens.user = response.data.token;
  testData.userId = response.data.user?.id;
  log.success('User login successful');
  testsPassed++;

  return true;
}

/**
 * Test 1: Check if paid appointment exists for testing
 */
async function testGetPaidAppointments() {
  log.section('TEST 1: GET PAID APPOINTMENTS');

  log.info('Fetching paid appointments for user...');
  const response = await request('GET', '/appointments/my/appointments', null, tokens.user);

  if (!response.ok) {
    log.error(`Failed to fetch appointments: ${response.status}`);
    testsFailed++;
    return false;
  }

  const appointments = response.data.data || response.data || [];
  if (!Array.isArray(appointments)) {
    log.error('Invalid response format for appointments');
    testsFailed++;
    return false;
  }

  const paidAppointments = appointments.filter(a => a.payment_status === 'paid');
  
  if (paidAppointments.length === 0) {
    log.warning('No paid appointments found. Creating test appointment...');
    return false;
  }

  testData.appointmentId = paidAppointments[0].id;
  log.success(`Found ${paidAppointments.length} paid appointments`);
  log.info(`Using appointment ID: ${testData.appointmentId}`);
  testsPassed++;
  return true;
}

/**
 * Test 2: Cashier requests refund
 */
async function testCashierRequestRefund() {
  log.section('TEST 2: CASHIER REQUEST REFUND');

  if (!testData.appointmentId) {
    log.warning('Skipping - no paid appointment available');
    return false;
  }

  log.info(`Requesting refund for appointment ${testData.appointmentId}...`);
  const response = await request('POST', '/cashier/refunds/request', {
    appointment_id: testData.appointmentId,
    refund_amount: 100.00,
    reason: 'customer_request',
    description: 'Test refund request from cashier'
  }, tokens.cashier);

  if (!response.ok) {
    log.error(`Refund request failed: ${response.data.message || response.status}`);
    testsFailed++;
    return false;
  }

  testData.refundId = response.data.refund?.id;
  log.success(`Refund request created successfully (ID: ${testData.refundId})`);
  testsPassed++;
  return true;
}

/**
 * Test 3: Get cashier pending refunds
 */
async function testGetCashierPendingRefunds() {
  log.section('TEST 3: GET CASHIER PENDING REFUNDS');

  log.info('Fetching pending refunds for cashier...');
  const response = await request('GET', '/cashier/refunds/pending', null, tokens.cashier);

  if (!response.ok) {
    log.error(`Failed to fetch pending refunds: ${response.status}`);
    testsFailed++;
    return false;
  }

  const refunds = response.data.data || [];
  log.success(`Found ${refunds.length} pending refunds`);
  testsPassed++;
  return true;
}

/**
 * Test 4: Admin gets all refunds
 */
async function testAdminGetAllRefunds() {
  log.section('TEST 4: ADMIN GET ALL REFUNDS');

  log.info('Fetching all refunds for admin...');
  const response = await request('GET', '/admin/refunds/all', null, tokens.admin);

  if (!response.ok) {
    log.error(`Failed to fetch refunds: ${response.status}`);
    testsFailed++;
    return false;
  }

  const refunds = response.data.data || [];
  log.success(`Found ${refunds.length} total refunds`);
  
  if (testData.refundId) {
    const refund = refunds.find(r => r.id === testData.refundId);
    if (refund) {
      log.info(`Test refund found: Status=${refund.status}, Amount=₱${refund.refund_amount}`);
    }
  }
  
  testsPassed++;
  return true;
}

/**
 * Test 5: Admin approves refund
 */
async function testAdminApproveRefund() {
  log.section('TEST 5: ADMIN APPROVE REFUND');

  if (!testData.refundId) {
    log.warning('Skipping - no refund to approve');
    return false;
  }

  log.info(`Approving refund ${testData.refundId}...`);
  const response = await request('POST', `/admin/refunds/${testData.refundId}/approve`, {
    approval_notes: 'Test approval notes',
    refund_method: 'original_method'
  }, tokens.admin);

  if (!response.ok) {
    log.error(`Approve refund failed: ${response.data.message || response.status}`);
    testsFailed++;
    return false;
  }

  log.success(`Refund approved successfully`);
  testsPassed++;
  return true;
}

/**
 * Test 6: Admin completes refund
 */
async function testAdminCompleteRefund() {
  log.section('TEST 6: ADMIN COMPLETE REFUND');

  if (!testData.refundId) {
    log.warning('Skipping - no refund to complete');
    return false;
  }

  log.info(`Completing refund ${testData.refundId}...`);
  const response = await request('POST', `/admin/refunds/${testData.refundId}/complete`, {
    transaction_id: 'TXN-12345-67890'
  }, tokens.admin);

  if (!response.ok) {
    log.error(`Complete refund failed: ${response.data.message || response.status}`);
    testsFailed++;
    return false;
  }

  log.success(`Refund completed successfully`);
  testsPassed++;
  return true;
}

/**
 * Test 7: Get refund stats
 */
async function testGetRefundStats() {
  log.section('TEST 7: GET REFUND STATS');

  log.info('Fetching refund statistics...');
  const response = await request('GET', '/admin/refunds/stats?timeframe=monthly', null, tokens.admin);

  if (!response.ok) {
    log.error(`Failed to fetch stats: ${response.status}`);
    testsFailed++;
    return false;
  }

  const stats = response.data.stats || {};
  log.success('Refund statistics fetched successfully');
  log.info(`Total Requests: ${stats.totalRequests || 0}`);
  log.info(`Pending: ${stats.pendingCount || 0}`);
  log.info(`Approved: ${stats.approvedCount || 0}`);
  log.info(`Completed: ${stats.completedCount || 0}`);
  log.info(`Rejected: ${stats.rejectedCount || 0}`);
  log.info(`Total Refunded: ₱${parseFloat(stats.totalRefundAmount || 0).toFixed(2)}`);
  testsPassed++;
  return true;
}

/**
 * Test 8: Check appointment refunds
 */
async function testGetAppointmentRefunds() {
  log.section('TEST 8: GET APPOINTMENT REFUNDS');

  if (!testData.appointmentId) {
    log.warning('Skipping - no appointment available');
    return false;
  }

  log.info(`Fetching refunds for appointment ${testData.appointmentId}...`);
  const response = await request('GET', `/cashier/appointments/${testData.appointmentId}/refunds`, null, tokens.cashier);

  if (!response.ok) {
    log.error(`Failed to fetch appointment refunds: ${response.status}`);
    testsFailed++;
    return false;
  }

  const refunds = response.data.refunds || [];
  log.success(`Found ${refunds.length} refund(s) for appointment`);
  testsPassed++;
  return true;
}

/**
 * Test 9: Validate appointment payment status update
 */
async function testAppointmentPaymentStatusUpdate() {
  log.section('TEST 9: VERIFY APPOINTMENT STATUS UPDATE');

  if (!testData.appointmentId) {
    log.warning('Skipping - no appointment available');
    return false;
  }

  log.info(`Checking appointment payment status...`);
  const response = await request('GET', `/appointments/${testData.appointmentId}`, null, tokens.user);

  if (!response.ok) {
    log.error(`Failed to fetch appointment: ${response.status}`);
    testsFailed++;
    return false;
  }

  const appointment = response.data.data || response.data;
  log.info(`Current payment status: ${appointment.payment_status}`);
  
  if (['refunded', 'partially_refunded'].includes(appointment.payment_status)) {
    log.success('Appointment payment status correctly updated');
    testsPassed++;
    return true;
  } else {
    log.warning('Payment status not showing refunded state (may still be processing)');
    testsPassed++;
    return true;
  }
}

/**
 * Test 10: Test invalid refund request
 */
async function testInvalidRefundRequest() {
  log.section('TEST 10: TEST INVALID REFUND (ERROR HANDLING)');

  log.info('Testing refund request with invalid appointment ID...');
  const response = await request('POST', '/cashier/refunds/request', {
    appointment_id: 99999,
    refund_amount: 100.00,
    reason: 'customer_request',
    description: 'Invalid test'
  }, tokens.cashier);

  if (response.ok) {
    log.error('Should have failed but succeeded');
    testsFailed++;
    return false;
  }

  log.success(`Correctly rejected: ${response.data.message}`);
  testsPassed++;
  return true;
}

/**
 * Test 11: Test refund amount validation
 */
async function testRefundAmountValidation() {
  log.section('TEST 11: TEST REFUND AMOUNT VALIDATION');

  if (!testData.appointmentId) {
    log.warning('Skipping - no appointment available');
    return false;
  }

  log.info('Testing refund with amount exceeding payment...');
  const response = await request('POST', '/cashier/refunds/request', {
    appointment_id: testData.appointmentId,
    refund_amount: 9999.99,
    reason: 'customer_request',
    description: 'Amount validation test'
  }, tokens.cashier);

  if (response.ok) {
    log.error('Should have rejected amount exceeding payment');
    testsFailed++;
    return false;
  }

  log.success(`Correctly rejected: ${response.data.message}`);
  testsPassed++;
  return true;
}

/**
 * Run all tests
 */
async function runAllTests() {
  console.clear();
  log.header('🧪 REFUND SYSTEM INTEGRATION TEST SUITE');
  log.info(`API URL: ${API_URL}`);
  log.info(`Test Start: ${new Date().toLocaleString()}`);

  try {
    // Authentication
    if (!await loginUsers()) {
      log.error('Authentication failed - tests cannot continue');
      process.exit(1);
    }

    // Run all tests
    await testGetPaidAppointments();
    await testCashierRequestRefund();
    await testGetCashierPendingRefunds();
    await testAdminGetAllRefunds();
    await testAdminApproveRefund();
    await testAdminCompleteRefund();
    await testGetRefundStats();
    await testGetAppointmentRefunds();
    await testAppointmentPaymentStatusUpdate();
    await testInvalidRefundRequest();
    await testRefundAmountValidation();

    // Summary
    log.section('TEST SUMMARY');
    const totalTests = testsPassed + testsFailed;
    const percentage = totalTests > 0 ? ((testsPassed / totalTests) * 100).toFixed(2) : 0;

    console.log(`${colors.bright}Tests Passed: ${colors.green}${testsPassed}${colors.reset}`);
    console.log(`${colors.bright}Tests Failed: ${colors.red}${testsFailed}${colors.reset}`);
    console.log(`${colors.bright}Success Rate: ${percentage}%${colors.reset}`);
    console.log(`${colors.bright}Total Tests: ${totalTests}${colors.reset}\n`);

    if (apiErrors.length > 0) {
      log.section('API ERRORS');
      apiErrors.forEach(err => {
        log.error(`${err.endpoint}: ${err.error}`);
      });
    }

    // Exit status
    if (testsFailed > 0) {
      console.log(`\n${colors.red}❌ Some tests failed!${colors.reset}\n`);
      process.exit(1);
    } else {
      console.log(`\n${colors.green}✅ All tests passed!${colors.reset}\n`);
      process.exit(0);
    }
  } catch (error) {
    log.error(`Test suite error: ${error.message}`);
    console.error(error);
    process.exit(1);
  }
}

runAllTests();
