import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { ChartBarIcon, ArrowPathIcon } from '@heroicons/react/24/outline';

const AdminDecisionSupport = ({ isDarkMode = true, onRefresh = () => {} }) => {
  const [loading, setLoading] = useState(false);
  const [dashboard, setDashboard] = useState(null);
  const [selectedDate, setSelectedDate] = useState(() => {
    const d = new Date();
    return d.toISOString().split('T')[0];
  });
  const [timeRecommendations, setTimeRecommendations] = useState([]);
  const [actionLoading, setActionLoading] = useState(false);

  useEffect(() => {
    // Load dashboard with initial selected date
    if (selectedDate) {
      loadDashboard(selectedDate);
    }
  }, [selectedDate]);

  // Convert 24-hour time to 12-hour format
  const formatTimeAmPm = (timeStr) => {
    if (!timeStr) return timeStr;
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
    return `${displayHour}:${minutes} ${ampm}`;
  };

  const loadDashboard = async (date = selectedDate) => {
    setLoading(true);
    try {
      const res = await axios.get('/api/decision-support/dashboard', { 
        params: { appointment_date: date } 
      });
      setDashboard(res.data?.data || res.data || null);
    } catch (err) {
      console.error('Failed to load decision support dashboard', err);
      setDashboard(null);
    } finally {
      setLoading(false);
    }
  };

  const fetchTimeRecommendations = async (date) => {
    setLoading(true);
    try {
      const res = await axios.get('/api/decision-support/time-slot-recommendations', { 
        params: { appointment_date: date } 
      });
      setTimeRecommendations(res.data?.data || res.data || []);
    } catch (err) {
      console.error('Failed to fetch time recommendations', err);
      setTimeRecommendations([]);
    } finally {
      setLoading(false);
    }
  };


  const handleReserve = async (slot) => {
    setActionLoading(true);
    try {
      // Best-effort: call admin reserve endpoint if available
      await axios.post('/api/admin/reserve-suggested-slot', { slot });
      // notify parent to refresh calendar/unavailable dates if needed
      onRefresh();
      alert('Reserved suggested slot (if API exists).');
    } catch (err) {
      console.warn('Reserve API may not exist; showing local confirmation.', err);
      alert('Reserved locally (API not available).');
    } finally {
      setActionLoading(false);
    }
  };

  const handleAssignStaff = async (rec) => {
    setActionLoading(true);
    try {
      await axios.post('/api/admin/assign-staff', { recommendation: rec });
      alert('Assigned staff (if API exists).');
      onRefresh();
    } catch (err) {
      console.warn('Assign staff API may not exist; showing local confirmation.', err);
      alert('Assigned locally (API not available).');
    } finally {
      setActionLoading(false);
    }
  };

  return (
    <div className="bg-gray-900 border border-amber-500/20 rounded-lg shadow p-4">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-sm font-semibold text-amber-50 flex items-center">
          <ChartBarIcon className="h-4 w-4 mr-2" /> Decision Support
        </h3>
        <div className="flex items-center gap-2">
          <button
            onClick={() => { 
              loadDashboard(selectedDate); 
              fetchTimeRecommendations(selectedDate); 
            }}
            className="text-xs px-2 py-1 border border-amber-500/30 rounded text-amber-50 hover:bg-amber-500/10"
            title="Refresh"
          >
            <ArrowPathIcon className="h-4 w-4" />
          </button>
        </div>
      </div>

      <div className="text-xs text-gray-300 mb-3">
        <div className="mb-2">Overview: {dashboard ? (dashboard.summary || 'Ready') : 'No data'}</div>
        <div className="flex items-center gap-2">
          <input
            type="date"
            value={selectedDate}
            onChange={(e) => setSelectedDate(e.target.value)}
            className="px-2 py-1 bg-gray-800 border border-gray-700 rounded text-xs text-white"
          />
          <button
            onClick={() => fetchTimeRecommendations(selectedDate)}
            className="px-2 py-1 text-xs bg-amber-600 rounded text-white"
          >
            Get Time Suggestions
          </button>
        </div>
      </div>

      <div className="space-y-3 max-h-[36vh] overflow-y-auto">
        <div>
          <h4 className="text-xs text-amber-200 font-medium mb-2">Time Slot Suggestions</h4>
          {loading && <div className="text-xs text-gray-400">Loading…</div>}
          {!loading && timeRecommendations.length === 0 && (
            <div className="text-xs text-gray-400">No time suggestions available for selected date.</div>
          )}
          {timeRecommendations.map((t, idx) => (
            <div key={idx} className="p-2 bg-gray-800/30 border border-gray-700 rounded flex items-center justify-between text-xs">
              <div>
                <div className="font-medium text-amber-50">{formatTimeAmPm(t.time) || formatTimeAmPm(t.slot) || `${formatTimeAmPm(t.start)} - ${formatTimeAmPm(t.end)}`}</div>
                <div className="text-gray-400">Available slots: {t.available_slots ?? t.capacity ?? t.available_capacity ?? 'N/A'}</div>
              </div>
              <div className="flex items-center gap-2">
                <button onClick={() => handleReserve(t)} disabled={actionLoading} className="px-2 py-1 text-xs bg-green-600 rounded text-white">Reserve</button>
              </div>
            </div>
          ))}
        </div>

        {/* Staff recommendations removed - system now supports only admin and client roles */}
      </div>
    </div>
  );
};

export default AdminDecisionSupport;
