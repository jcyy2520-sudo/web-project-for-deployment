#!/bin/bash
# Real-Time Updates System Diagnostic Script
# Verifies all components are working correctly

echo "=== Real-Time Updates System Diagnostics ==="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check backend server
echo -n "Checking backend server (port 8000)... "
if curl -s http://localhost:8000 > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Running${NC}"
else
    echo -e "${RED}✗ Not running${NC}"
    exit 1
fi

# Check frontend server
echo -n "Checking frontend server (port 3000)... "
if curl -s http://localhost:3000 > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Running${NC}"
else
    echo -e "${RED}✗ Not running${NC}"
    exit 1
fi

# Check realtime API endpoints
echo -n "Testing /api/realtime/updates endpoint... "
RESPONSE=$(curl -s http://localhost:8000/api/realtime/updates)
if echo "$RESPONSE" | grep -q '"success":true'; then
    echo -e "${GREEN}✓ Working${NC}"
else
    echo -e "${RED}✗ Failed${NC}"
    echo "Response: $RESPONSE"
    exit 1
fi

# Check slot capacities endpoint
echo -n "Testing /api/realtime/slot-capacities endpoint... "
RESPONSE=$(curl -s "http://localhost:8000/api/realtime/slot-capacities?date=2025-12-15&startTime=08:00&endTime=09:00")
if echo "$RESPONSE" | grep -q '"success":true'; then
    echo -e "${GREEN}✓ Working${NC}"
else
    echo -e "${RED}✗ Failed${NC}"
    exit 1
fi

# Check appointment settings endpoint
echo -n "Testing /api/realtime/appointment-settings endpoint... "
RESPONSE=$(curl -s http://localhost:8000/api/realtime/appointment-settings)
if echo "$RESPONSE" | grep -q '"success":true'; then
    echo -e "${GREEN}✓ Working${NC}"
else
    echo -e "${RED}✗ Failed${NC}"
    exit 1
fi

# Check event classes exist
echo -n "Checking SlotCapacityChanged event class... "
if [ -f "web-backend/app/Events/SlotCapacityChanged.php" ]; then
    echo -e "${GREEN}✓ Exists${NC}"
else
    echo -e "${RED}✗ Not found${NC}"
    exit 1
fi

echo -n "Checking AppointmentSettingsChanged event class... "
if [ -f "web-backend/app/Events/AppointmentSettingsChanged.php" ]; then
    echo -e "${GREEN}✓ Exists${NC}"
else
    echo -e "${RED}✗ Not found${NC}"
    exit 1
fi

# Check RealtimeUpdateController exists
echo -n "Checking RealtimeUpdateController class... "
if [ -f "web-backend/app/Http/Controllers/RealtimeUpdateController.php" ]; then
    echo -e "${GREEN}✓ Exists${NC}"
else
    echo -e "${RED}✗ Not found${NC}"
    exit 1
fi

# Check frontend hook exists
echo -n "Checking useRealtimeUpdates hook... "
if [ -f "web-frontend/src/hooks/useRealtimeUpdates.js" ]; then
    echo -e "${GREEN}✓ Exists${NC}"
else
    echo -e "${RED}✗ Not found${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}=== All Diagnostics Passed! ==="
echo "Real-Time Updates System is ready for testing"
echo ""
echo "Next steps:"
echo "1. Open http://localhost:3000 in your browser"
echo "2. Log in as admin and regular user (different browsers)"
echo "3. Admin: Go to admin panel and change slot capacities"
echo "4. User: Watch Dashboard for automatic updates"
echo "5. Check browser DevTools Network tab for polling requests"
echo ""
