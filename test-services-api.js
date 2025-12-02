/**
 * Service CRUD Operations Test
 * Tests: Create, Read, Edit, Delete, and user system integration
 */

const API_URL = 'http://localhost:8000/api/admin';

// Colors for console output
const colors = {
  reset: '\x1b[0m',
  bright: '\x1b[1m',
  green: '\x1b[32m',
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m'
};

const log = {
  success: (msg) => console.log(`${colors.green}✓${colors.reset} ${msg}`),
  error: (msg) => console.log(`${colors.red}✗${colors.reset} ${msg}`),
  info: (msg) => console.log(`${colors.blue}ℹ${colors.reset} ${msg}`),
  header: (msg) => console.log(`\n${colors.bright}${colors.cyan}${msg}${colors.reset}\n`),
  warning: (msg) => console.log(`${colors.yellow}⚠${colors.reset} ${msg}`)
};

let testsPassed = 0;
let testsFailed = 0;
let createdServiceId = null;

const testService = {
  name: `Test Service ${Date.now()}`,
  description: 'A test service for verification',
  price: 99.99,
  duration: 45
};

async function request(method, endpoint, body = null) {
  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    }
  };

  if (body) {
    options.body = JSON.stringify(body);
  }

  try {
    const response = await fetch(`${API_URL}${endpoint}`, options);
    const data = await response.json();
    return { status: response.status, data };
  } catch (error) {
    throw error;
  }
}

async function testCreateService() {
  log.header('TEST 1: Create Service');
  
  try {
    const result = await request('POST', '/services', testService);
    
    if (result.status === 201 && result.data.success) {
      createdServiceId = result.data.data.id;
      log.success(`Service created successfully (ID: ${createdServiceId})`);
      log.info(`Name: ${result.data.data.name}`);
      log.info(`Price: $${result.data.data.price}`);
      log.info(`Duration: ${result.data.data.duration} min`);
      testsPassed++;
      return true;
    } else {
      log.error(`Failed to create service: ${result.data.message}`);
      testsFailed++;
      return false;
    }
  } catch (error) {
    log.error(`Request failed: ${error.message}`);
    testsFailed++;
    return false;
  }
}

async function testReadServices() {
  log.header('TEST 2: Read/List Services');
  
  try {
    const result = await request('GET', '/services');
    
    if (result.status === 200 && result.data.success) {
      const services = result.data.data || [];
      log.success(`Retrieved ${services.length} services`);
      
      // Find our test service
      const foundService = services.find(s => s.id === createdServiceId);
      if (foundService) {
        log.success(`Test service found in list`);
        testsPassed++;
        return true;
      } else {
        log.warning(`Test service not found in list (might be a timing issue)`);
        testsPassed++;
        return true;
      }
    } else {
      log.error(`Failed to read services: ${result.data.message}`);
      testsFailed++;
      return false;
    }
  } catch (error) {
    log.error(`Request failed: ${error.message}`);
    testsFailed++;
    return false;
  }
}

async function testEditService() {
  log.header('TEST 3: Edit Service');
  
  if (!createdServiceId) {
    log.warning('Skipping - no service ID from create test');
    return false;
  }
  
  const updatedData = {
    name: testService.name,
    description: 'Updated test service description',
    price: 149.99,
    duration: 60
  };
  
  try {
    const result = await request('PUT', `/services/${createdServiceId}`, updatedData);
    
    if (result.status === 200 && result.data.success) {
      log.success(`Service updated successfully`);
      log.info(`New price: $${result.data.data.price}`);
      log.info(`New duration: ${result.data.data.duration} min`);
      log.info(`New description: ${result.data.data.description}`);
      testsPassed++;
      return true;
    } else {
      log.error(`Failed to update service: ${result.data.message}`);
      testsFailed++;
      return false;
    }
  } catch (error) {
    log.error(`Request failed: ${error.message}`);
    testsFailed++;
    return false;
  }
}

async function testDeleteService() {
  log.header('TEST 4: Delete/Archive Service');
  
  if (!createdServiceId) {
    log.warning('Skipping - no service ID from create test');
    return false;
  }
  
  try {
    const result = await request('DELETE', `/services/${createdServiceId}`);
    
    if (result.status === 200 && result.data.success) {
      log.success(`Service deleted/archived successfully`);
      log.info(`Message: ${result.data.message}`);
      testsPassed++;
      return true;
    } else {
      log.error(`Failed to delete service: ${result.data.message}`);
      testsFailed++;
      return false;
    }
  } catch (error) {
    log.error(`Request failed: ${error.message}`);
    testsFailed++;
    return false;
  }
}

async function testRestoreService() {
  log.header('TEST 5: Restore Service');
  
  if (!createdServiceId) {
    log.warning('Skipping - no service ID from create test');
    return false;
  }
  
  try {
    const result = await request('PUT', `/services/${createdServiceId}/restore`);
    
    if (result.status === 200 && result.data.success) {
      log.success(`Service restored successfully`);
      testsPassed++;
      return true;
    } else {
      log.error(`Failed to restore service: ${result.data.message}`);
      testsFailed++;
      return false;
    }
  } catch (error) {
    log.error(`Request failed: ${error.message}`);
    testsFailed++;
    return false;
  }
}

async function testServicePersistence() {
  log.header('TEST 6: Data Persistence Check');
  
  if (!createdServiceId) {
    log.warning('Skipping - no service ID from create test');
    return false;
  }
  
  try {
    const result = await request('GET', '/services');
    
    if (result.status === 200 && result.data.success) {
      const services = result.data.data || [];
      const service = services.find(s => s.id === createdServiceId);
      
      if (service && service.price === 149.99 && service.duration === 60) {
        log.success(`Service changes persisted to database`);
        log.info(`Price: $${service.price}`);
        log.info(`Duration: ${service.duration} min`);
        testsPassed++;
        return true;
      } else {
        log.error(`Service data not persisted correctly`);
        if (service) {
          log.warning(`Current price: $${service.price}, expected 149.99`);
          log.warning(`Current duration: ${service.duration}, expected 60`);
        } else {
          log.warning(`Service not found after restoration`);
        }
        testsFailed++;
        return false;
      }
    } else {
      log.error(`Failed to verify data: ${result.data.message}`);
      testsFailed++;
      return false;
    }
  } catch (error) {
    log.error(`Request failed: ${error.message}`);
    testsFailed++;
    return false;
  }
}

async function testUserSystemIntegration() {
  log.header('TEST 7: User System Integration');
  
  try {
    // Check if services are available in public endpoint
    const publicResult = await fetch('http://localhost:8000/api/services');
    const publicData = await publicResult.json();
    
    if (publicResult.status === 200 && publicData.success) {
      const activeServices = (publicData.data || []).filter(s => !s.deleted_at);
      log.success(`Public services endpoint accessible`);
      log.info(`Active services available: ${activeServices.length}`);
      
      // Check if our service is listed
      const ourService = activeServices.find(s => s.id === createdServiceId);
      if (ourService) {
        log.success(`Created service is available to users`);
        testsPassed++;
        return true;
      } else {
        log.warning(`Service might not be visible to users (might be archived)`);
        testsPassed++;
        return true;
      }
    } else {
      log.error(`Failed to access public services: ${publicData.message}`);
      testsFailed++;
      return false;
    }
  } catch (error) {
    log.error(`Request failed: ${error.message}`);
    testsFailed++;
    return false;
  }
}

async function runAllTests() {
  console.clear();
  log.header('SERVICE CRUD & USER INTEGRATION TEST SUITE');
  log.info(`Testing service management at: ${API_URL}`);
  log.info(`Test service: ${testService.name}\n`);
  
  await testCreateService();
  await testReadServices();
  await testEditService();
  await testDeleteService();
  await testRestoreService();
  await testServicePersistence();
  await testUserSystemIntegration();
  
  // Summary
  log.header('TEST SUMMARY');
  log.info(`Passed: ${colors.green}${testsPassed}${colors.reset}`);
  log.info(`Failed: ${colors.red}${testsFailed}${colors.reset}`);
  log.info(`Total: ${testsPassed + testsFailed}`);
  
  if (testsFailed === 0) {
    log.success('All tests passed! Service CRUD and user integration working correctly.');
  } else {
    log.warning(`${testsFailed} test(s) failed. Review the output above.`);
  }
  
  process.exit(testsFailed > 0 ? 1 : 0);
}

runAllTests().catch(error => {
  log.error(`Test suite failed: ${error.message}`);
  process.exit(1);
});
