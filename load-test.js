/**
 * K6 Load Testing Script for Rotational Contribution App
 * 
 * This script tests the API endpoints under various load conditions
 * to ensure the system can handle expected traffic.
 * 
 * Installation:
 *   brew install k6  # macOS
 *   choco install k6  # Windows
 *   apt-get install k6  # Linux
 * 
 * Usage:
 *   k6 run load-test.js
 *   k6 run --vus 100 --duration 5m load-test.js
 */

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const loginDuration = new Trend('login_duration');
const groupListDuration = new Trend('group_list_duration');
const contributionDuration = new Trend('contribution_duration');

// Test configuration
export const options = {
  stages: [
    { duration: '2m', target: 50 },   // Ramp up to 50 users
    { duration: '5m', target: 50 },   // Stay at 50 users
    { duration: '2m', target: 100 },  // Ramp up to 100 users
    { duration: '5m', target: 100 },  // Stay at 100 users
    { duration: '2m', target: 200 },  // Ramp up to 200 users
    { duration: '5m', target: 200 },  // Stay at 200 users
    { duration: '2m', target: 0 },    // Ramp down to 0 users
  ],
  thresholds: {
    'http_req_duration': ['p(95)<500', 'p(99)<1000'], // 95% of requests under 500ms, 99% under 1s
    'http_req_failed': ['rate<0.05'],  // Error rate under 5%
    'errors': ['rate<0.05'],
    'login_duration': ['p(95)<1000'],
    'group_list_duration': ['p(95)<300'],
    'contribution_duration': ['p(95)<500'],
  },
};

// Configuration
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const API_BASE = `${BASE_URL}/api/v1`;

// Test data
const testUsers = [
  { email: 'test1@example.com', password: 'password123' },
  { email: 'test2@example.com', password: 'password123' },
  { email: 'test3@example.com', password: 'password123' },
  { email: 'test4@example.com', password: 'password123' },
  { email: 'test5@example.com', password: 'password123' },
];

/**
 * Setup function - runs once before test
 */
export function setup() {
  console.log('Starting load test...');
  console.log(`Base URL: ${BASE_URL}`);
  
  // Verify API is accessible
  const healthCheck = http.get(`${BASE_URL}/api/health`);
  if (healthCheck.status !== 200) {
    console.error('API health check failed!');
  }
  
  return { baseUrl: BASE_URL };
}

/**
 * Main test function - runs for each virtual user
 */
export default function(data) {
  // Select random user
  const user = testUsers[Math.floor(Math.random() * testUsers.length)];
  
  // Test authentication flow
  group('Authentication', function() {
    testLogin(user);
  });
  
  sleep(1);
  
  // Test group operations
  group('Group Operations', function() {
    testGroupListing();
    testGroupDetails();
  });
  
  sleep(1);
  
  // Test contribution operations
  group('Contribution Operations', function() {
    testContributionHistory();
  });
  
  sleep(1);
  
  // Test wallet operations
  group('Wallet Operations', function() {
    testWalletBalance();
    testTransactionHistory();
  });
  
  sleep(2);
}

/**
 * Test user login
 */
function testLogin(user) {
  const payload = JSON.stringify({
    email: user.email,
    password: user.password,
  });
  
  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
  };
  
  const startTime = new Date();
  const response = http.post(`${API_BASE}/auth/login`, payload, params);
  const duration = new Date() - startTime;
  
  loginDuration.add(duration);
  
  const success = check(response, {
    'login status is 200': (r) => r.status === 200,
    'login has token': (r) => r.json('data.token') !== undefined,
    'login response time < 1000ms': (r) => r.timings.duration < 1000,
  });
  
  errorRate.add(!success);
  
  if (success && response.json('data.token')) {
    return response.json('data.token');
  }
  
  return null;
}

/**
 * Test group listing
 */
function testGroupListing() {
  const token = getAuthToken();
  if (!token) return;
  
  const params = {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  };
  
  const startTime = new Date();
  const response = http.get(`${API_BASE}/groups`, params);
  const duration = new Date() - startTime;
  
  groupListDuration.add(duration);
  
  const success = check(response, {
    'groups status is 200': (r) => r.status === 200,
    'groups has data': (r) => r.json('data') !== undefined,
    'groups response time < 300ms': (r) => r.timings.duration < 300,
  });
  
  errorRate.add(!success);
}

/**
 * Test group details
 */
function testGroupDetails() {
  const token = getAuthToken();
  if (!token) return;
  
  const params = {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  };
  
  // Assuming group ID 1 exists
  const response = http.get(`${API_BASE}/groups/1`, params);
  
  const success = check(response, {
    'group details status is 200 or 404': (r) => r.status === 200 || r.status === 404,
    'group details response time < 300ms': (r) => r.timings.duration < 300,
  });
  
  errorRate.add(!success);
}

/**
 * Test contribution history
 */
function testContributionHistory() {
  const token = getAuthToken();
  if (!token) return;
  
  const params = {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  };
  
  const startTime = new Date();
  const response = http.get(`${API_BASE}/contributions/history`, params);
  const duration = new Date() - startTime;
  
  contributionDuration.add(duration);
  
  const success = check(response, {
    'contribution history status is 200': (r) => r.status === 200,
    'contribution history has data': (r) => r.json('data') !== undefined,
    'contribution history response time < 500ms': (r) => r.timings.duration < 500,
  });
  
  errorRate.add(!success);
}

/**
 * Test wallet balance
 */
function testWalletBalance() {
  const token = getAuthToken();
  if (!token) return;
  
  const params = {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  };
  
  const response = http.get(`${API_BASE}/wallet/balance`, params);
  
  const success = check(response, {
    'wallet balance status is 200': (r) => r.status === 200,
    'wallet balance has data': (r) => r.json('data.balance') !== undefined,
    'wallet balance response time < 200ms': (r) => r.timings.duration < 200,
  });
  
  errorRate.add(!success);
}

/**
 * Test transaction history
 */
function testTransactionHistory() {
  const token = getAuthToken();
  if (!token) return;
  
  const params = {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  };
  
  const response = http.get(`${API_BASE}/wallet/transactions`, params);
  
  const success = check(response, {
    'transaction history status is 200': (r) => r.status === 200,
    'transaction history has data': (r) => r.json('data') !== undefined,
    'transaction history response time < 400ms': (r) => r.timings.duration < 400,
  });
  
  errorRate.add(!success);
}

/**
 * Helper function to get auth token
 */
function getAuthToken() {
  const user = testUsers[0];
  const payload = JSON.stringify({
    email: user.email,
    password: user.password,
  });
  
  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
  };
  
  const response = http.post(`${API_BASE}/auth/login`, payload, params);
  
  if (response.status === 200 && response.json('data.token')) {
    return response.json('data.token');
  }
  
  return null;
}

/**
 * Teardown function - runs once after test
 */
export function teardown(data) {
  console.log('Load test completed!');
}

/**
 * Handle summary - custom summary output
 */
export function handleSummary(data) {
  return {
    'stdout': textSummary(data, { indent: ' ', enableColors: true }),
    'load-test-results.json': JSON.stringify(data),
  };
}

function textSummary(data, options) {
  const indent = options.indent || '';
  const enableColors = options.enableColors || false;
  
  let summary = '\n';
  summary += `${indent}Load Test Summary\n`;
  summary += `${indent}================\n\n`;
  
  // Test duration
  summary += `${indent}Duration: ${data.state.testRunDurationMs / 1000}s\n`;
  
  // HTTP metrics
  if (data.metrics.http_reqs) {
    summary += `${indent}Total Requests: ${data.metrics.http_reqs.values.count}\n`;
  }
  
  if (data.metrics.http_req_duration) {
    summary += `${indent}Avg Response Time: ${data.metrics.http_req_duration.values.avg.toFixed(2)}ms\n`;
    summary += `${indent}P95 Response Time: ${data.metrics.http_req_duration.values['p(95)'].toFixed(2)}ms\n`;
    summary += `${indent}P99 Response Time: ${data.metrics.http_req_duration.values['p(99)'].toFixed(2)}ms\n`;
  }
  
  if (data.metrics.http_req_failed) {
    const failRate = (data.metrics.http_req_failed.values.rate * 100).toFixed(2);
    summary += `${indent}Error Rate: ${failRate}%\n`;
  }
  
  // Custom metrics
  if (data.metrics.login_duration) {
    summary += `${indent}\nLogin Performance:\n`;
    summary += `${indent}  Avg: ${data.metrics.login_duration.values.avg.toFixed(2)}ms\n`;
    summary += `${indent}  P95: ${data.metrics.login_duration.values['p(95)'].toFixed(2)}ms\n`;
  }
  
  if (data.metrics.group_list_duration) {
    summary += `${indent}\nGroup List Performance:\n`;
    summary += `${indent}  Avg: ${data.metrics.group_list_duration.values.avg.toFixed(2)}ms\n`;
    summary += `${indent}  P95: ${data.metrics.group_list_duration.values['p(95)'].toFixed(2)}ms\n`;
  }
  
  if (data.metrics.contribution_duration) {
    summary += `${indent}\nContribution Performance:\n`;
    summary += `${indent}  Avg: ${data.metrics.contribution_duration.values.avg.toFixed(2)}ms\n`;
    summary += `${indent}  P95: ${data.metrics.contribution_duration.values['p(95)'].toFixed(2)}ms\n`;
  }
  
  summary += '\n';
  
  return summary;
}
