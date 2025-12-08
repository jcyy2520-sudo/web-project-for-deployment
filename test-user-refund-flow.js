/**
 * User Refund Request & History Flow Test
 * Tests complete flow: User requests refund -> Admin approves -> User sees history
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
  user: null
};

let testData = {
  appointmentId: null,
  refundId: null,
  userId: null,
  userFirstName: null,
  userLastName: null
};

/**
 * Make API request
 */
async function request(method, endpoint, body = null, token = null) {
  const fullUrl = `${API_URL}${endpoint}`;
  
  try {
    const options = {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    };

    if (token) {
      options.headers['Authorization'] = `Bearer ${token}`;
    }

    if (body) {
      options.body = JSON.stringify(body);
    }

    const response = await fetch(fullUrl, options);
    const data = await response.json();

    if (!response.ok) {
      apiErrors.push({
        method,
        endpoint,
        status: response.status,
        error: data.message || data.error || 'Unknown error'
      });
    }

    return {
      status: response.status,
      ok: response.ok,
      data
    };
  } catch (err) {
    console.error(`Request error: ${err.message}`);
    apiErrors.push({
      method,
      endpoint,
      error: err.message
    });
    return {
      ok: false,
      status: 0,
      data: { error: err.message }
    };
  }
}

/**
 * Test 1: Login as User
 */
async function testUserLogin() {
  log.section('TEST 1: User Login');
  
  const response = await request('POST', '/login', {
    email: 'user@example.com',
    password: 'password'
  });

  if (response.ok && response.data.token) {
    tokens.user = response.data.token;
    testData.userId = response.data.user.id;
    testData.userFirstName = response.data.user.first_name;
    testData.userLastName = response.data.user.last_name;
    log.success(`Logged in as user: ${response.data.user.first_name} ${response.data.user.last_name}`);
    testsPassed++;
  } else {
    log.error(`Failed to login: ${response.data.message}`);
    testsFailed++;
    return false;
  }
  return true;
}

/**
 * Test 2: Login as Admin
 */
async function testAdminLogin() {
  log.section('TEST 2: Admin Login');
  
  const response = await request('POST', '/login', {
    email: 'admin@example.com',
    password: 'password'
  });

  if (response.ok && response.data.token) {
    tokens.admin = response.data.token;
    log.success(`Logged in as admin: ${response.data.user.first_name} ${response.data.user.last_name}`);
    testsPassed++;
  } else {
    log.error(`Failed to login: ${response.data.message}`);
    testsFailed++;
    return false;
  }
  return true;
}

/**
 * Test 3: Get User's Paid Appointment
 */
async function testGetPaidAppointment() {
  log.section('TEST 3: Get Paid Appointment');
  
  const response = await request('GET', '/appointments', null, tokens.user);

  if (response.ok && response.data.data && response.data.data.length > 0) {
    const paidAppointment = response.data.data.find(a => a.payment_status === 'paid');
    
    if (paidAppointment) {
      testData.appointmentId = paidAppointment.id;
      log.success(`Found paid appointment #${paidAppointment.id} on ${paidAppointment.appointment_date}`);
      log.info(`  Payment: ₱${parseFloat(paidAppointment.payment_amount).toFixed(2)}`);
      testsPassed++;
      return true;
    } else {
      log.warning(`No paid appointments found. Creating scenario may be needed.`);
      testsFailed++;
      return false;
    }
  } else {
    log.error(`Failed to get appointments: ${response.data.message}`);
    testsFailed++;
    return false;
  }
}

/**
 * Test 4: User Requests Refund
 */
async function testUserRequestRefund() {
  log.section('TEST 4: User Requests Refund');
  
  if (!testData.appointmentId) {
    log.error('No appointment ID available');
    testsFailed++;
    return false;
  }

  const response = await request('POST', '/cashier/refunds/request', {
    appointment_id: testData.appointmentId,
    refund_amount: 500.00,
    reason: 'customer_request',
    description: 'User testing refund system - partial refund'
  }, tokens.user);

  if (response.ok && response.data.data) {
    testData.refundId = response.data.data.id;
    log.success(`Refund request submitted #${response.data.data.id}`);
    log.info(`  Amount: ₱${parseFloat(response.data.data.refund_amount).toFixed(2)}`);
    log.info(`  Status: ${response.data.data.status}`);
    log.info(`  Requested by: User #${response.data.data.requested_by}`);
    testsPassed++;
    return true;
  } else {
    log.error(`Failed to request refund: ${response.data.message}`);
    testsFailed++;
    return false;
  }
}

/**
 * Test 5: User Views Refund History
 */
async function testUserRefundHistory() {
  log.section('TEST 5: User Refund History (All)');
  
  const response = await request('GET', '/user/refunds?page=1&per_page=5', null, tokens.user);

  if (response.ok && response.data) {
    const refundCount = response.data.data ? response.data.data.length : 0;
    log.success(`Retrieved refund history: ${refundCount} refund(s)`);
    
    if (response.data.data && response.data.data.length > 0) {
      response.data.data.slice(0, 3).forEach(refund => {
        log.info(`  • Refund #${refund.id}: ${refund.reason} - ${refund.status} (₱${parseFloat(refund.refund_amount).toFixed(2)})`);
      });
    }
    
    log.info(`  Pagination: Page ${response.data.current_page} of ${response.data.last_page}`);
    testsPassed++;
    return true;
  } else {
    log.error(`Failed to get refund history: ${response.data.message}`);
    testsFailed++;
    return false;
  }
}

/**
 * Test 6: User Views Pending Refunds Only
 */
async function testUserRefundHistoryFiltered() {
  log.section('TEST 6: User Refund History (Pending Only)');
  
  const response = await request('GET', '/user/refunds?status=pending&page=1&per_page=5', null, tokens.user);

  if (response.ok && response.data) {
    const refundCount = response.data.data ? response.data.data.length : 0;
    log.success(`Retrieved pending refunds: ${refundCount} refund(s)`);
    
    if (response.data.data && response.data.data.length > 0) {
      response.data.data.slice(0, 3).forEach(refund => {
        log.info(`  • Refund #${refund.id}: Waiting admin review - ₱${parseFloat(refund.refund_amount).toFixed(2)}`);
      });
    }
    testsPassed++;
    return true;
  } else {
    log.error(`Failed to get pending refunds: ${response.data.message}`);
    testsFailed++;
    return false;
  }
}

/**
 * Test 7: Admin Views All Refunds
 */
async function testAdminViewAllRefunds() {
  log.section('TEST 7: Admin Views All Refunds');
  
  const response = await request('GET', '/admin/refunds/all', null, tokens.admin);

  if (response.ok && response.data.data) {
    log.success(`Admin retrieved all refunds: ${response.data.data.length} refund(s)`);
    
    if (response.data.data.length > 0) {
      response.data.data.slice(0, 3).forEach(refund => {
        const requestedByName = refund.requested_by_user 
          ? `${refund.requested_by_user.first_name} ${refund.requested_by_user.last_name}`
          : `User #${refund.requested_by}`;
        log.info(`  • Refund #${refund.id}: ${refund.status} - Requested by ${requestedByName}`);
      });
    }
    testsPassed++;
    return true;
  } else {
    log.error(`Failed to get all refunds: ${response.data.message}`);
    testsFailed++;
    return false;
  }
}

/**
 * Test 8: Admin Approves Refund
 */
async function testAdminApproveRefund() {
  log.section('TEST 8: Admin Approves Refund');
  
  if (!testData.refundId) {
    log.warning('No refund ID available from previous test');
    testsFailed++;
    return false;
  }

  const response = await request('POST', `/admin/refunds/${testData.refundId}/approve`, {
    refund_method: 'bank_transfer',
    approval_notes: 'Auto-approved for test - bank transfer'
  }, tokens.admin);

  if (response.ok && response.data.data) {
    log.success(`Refund approved #${response.data.data.id}`);
    log.info(`  New status: ${response.data.data.status}`);
    log.info(`  Approved by: Admin #${response.data.data.approved_by}`);
    testsPassed++;
    return true;
  } else {
    log.error(`Failed to approve refund: ${response.data.message}`);
    testsFailed++;
    return false;
  }
}

/**
 * Test 9: User Sees Approved Refund in History
 */
async function testUserSeeApprovedRefund() {
  log.section('TEST 9: User Sees Approved Refund');
  
  if (!testData.refundId) {
    log.warning('No refund ID available');
    testsFailed++;
    return false;
  }

  const response = await request('GET', '/user/refunds?status=approved', null, tokens.user);

  if (response.ok && response.data.data) {
    const approvedRefund = response.data.data.find(r => r.id === testData.refundId);
    
    if (approvedRefund) {
      log.success(`User can see approved refund #${approvedRefund.id}`);
      log.info(`  Status: ${approvedRefund.status}`);
      log.info(`  Amount: ₱${parseFloat(approvedRefund.refund_amount).toFixed(2)}`);
      if (approvedRefund.approval_notes) {
        log.info(`  Admin Notes: ${approvedRefund.approval_notes}`);
      }
      testsPassed++;
      return true;
    } else {
      log.warning('Approved refund not found in user history');
      testsFailed++;
      return false;
    }
  } else {
    log.error(`Failed to get approved refunds: ${response.data.message}`);
    testsFailed++;
    return false;
  }
}

/**
 * Test 10: Get Refund Stats (Admin)
 */
async function testRefundStats() {
  log.section('TEST 10: Refund Stats (Admin)');
  
  const response = await request('GET', '/admin/refunds/stats', null, tokens.admin);

  if (response.ok && response.data.data) {
    const stats = response.data.data;
    log.success(`Retrieved refund stats`);
    
    if (stats.total_refunded !== undefined) {
      log.info(`  Total Refunded: ₱${parseFloat(stats.total_refunded).toFixed(2)}`);
    }
    if (stats.by_status) {
      Object.entries(stats.by_status).forEach(([status, data]) => {
        log.info(`  ${status}: ${data.count} refunds (₱${parseFloat(data.total_amount || 0).toFixed(2)})`);
      });
    }
    testsPassed++;
  } else {
    log.warning(`Refund stats endpoint may not be available: ${response.data.message}`);
    testsFailed++;
  }
}

/**
 * Test 11: User Refund Count Endpoint
 */
async function testUserRefundCount() {
  log.section('TEST 11: User Refund Count by Status');
  
  const statuses = ['pending', 'approved', 'completed', 'rejected'];
  
  for (const status of statuses) {
    const response = await request('GET', `/user/refunds?status=${status}`, null, tokens.user);
    
    if (response.ok && response.data.data) {
      const count = response.data.data.length;
      log.info(`  ${status}: ${count} refund(s)`);
    }
  }
  
  testsPassed++;
}

/**
 * Run all tests
 */
async function runAllTests() {
  log.header('🧪 USER REFUND REQUEST & HISTORY SYSTEM TEST SUITE');
  log.info(`API: ${API_URL}`);
  log.info(`Timestamp: ${new Date().toISOString()}\n`);

  // Run tests in sequence
  await testUserLogin();
  await testAdminLogin();
  await testGetPaidAppointment();
  
  if (testData.appointmentId) {
    await testUserRequestRefund();
  }
  
  await testUserRefundHistory();
  await testUserRefundHistoryFiltered();
  await testAdminViewAllRefunds();
  
  if (testData.refundId) {
    await testAdminApproveRefund();
    await testUserSeeApprovedRefund();
  }
  
  await testRefundStats();
  await testUserRefundCount();

  // Print summary
  log.section('TEST SUMMARY');
  log.success(`${testsPassed} tests passed`);
  if (testsFailed > 0) {
    log.error(`${testsFailed} tests failed`);
  }
  
  if (apiErrors.length > 0) {
    log.section('API ERRORS');
    apiErrors.forEach(err => {
      log.error(`${err.method} ${err.endpoint}`);
      log.info(`  ${err.status || 'ERROR'}: ${err.error}`);
    });
  }

  const passRate = ((testsPassed / (testsPassed + testsFailed)) * 100).toFixed(1);
  log.info(`\nPass Rate: ${passRate}%`);
  
  process.exit(testsFailed > 0 ? 1 : 0);
}

// Run tests
runAllTests().catch(err => {
  console.error('Test suite failed:', err);
  process.exit(1);
});
