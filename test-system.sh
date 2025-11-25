#!/bin/bash
# System Testing Script
# Tests both backend and frontend connectivity

echo "================================"
echo "  SYSTEM DEPLOYMENT TEST SUITE"
echo "================================"
echo ""

# Configuration
RENDER_BACKEND_URL="${1:-http://localhost:8000}"
VERCEL_FRONTEND_URL="${2:-http://localhost:3000}"

echo "📋 Testing Configuration:"
echo "  Backend URL: $RENDER_BACKEND_URL"
echo "  Frontend URL: $VERCEL_FRONTEND_URL"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Test function
test_endpoint() {
  local name=$1
  local method=$2
  local url=$3
  local expected_status=$4

  echo -n "Testing $name... "
  
  if [ "$method" = "GET" ]; then
    response=$(curl -s -w "\n%{http_code}" -X GET "$url" 2>/dev/null)
  elif [ "$method" = "POST" ]; then
    response=$(curl -s -w "\n%{http_code}" -X POST "$url" -H "Content-Type: application/json" 2>/dev/null)
  fi

  status_code=$(echo "$response" | tail -n1)
  body=$(echo "$response" | head -n-1)

  if [[ "$status_code" == $expected_status* ]]; then
    echo -e "${GREEN}✅ PASS${NC} (Status: $status_code)"
    TESTS_PASSED=$((TESTS_PASSED + 1))
  else
    echo -e "${RED}❌ FAIL${NC} (Status: $status_code, Expected: $expected_status)"
    TESTS_FAILED=$((TESTS_FAILED + 1))
  fi
}

echo "🔧 BACKEND TESTS"
echo "================"
echo ""

# Test health endpoint
test_endpoint "Backend Health" "GET" "$RENDER_BACKEND_URL/api/health" "200"

# Test CSRF token endpoint
test_endpoint "CSRF Token" "POST" "$RENDER_BACKEND_URL/sanctum/csrf-cookie" "200"

# Test user endpoint (should fail without auth)
test_endpoint "User Endpoint (No Auth)" "GET" "$RENDER_BACKEND_URL/api/user" "401"

echo ""
echo "💻 FRONTEND TESTS"
echo "================"
echo ""

# Test frontend is serving
test_endpoint "Frontend Home" "GET" "$VERCEL_FRONTEND_URL/" "200"

echo ""
echo "================================"
echo "  TEST SUMMARY"
echo "================================"
echo -e "Passed: ${GREEN}$TESTS_PASSED${NC}"
echo -e "Failed: ${RED}$TESTS_FAILED${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
  echo -e "${GREEN}✅ All tests passed!${NC}"
  exit 0
else
  echo -e "${RED}❌ Some tests failed${NC}"
  exit 1
fi
