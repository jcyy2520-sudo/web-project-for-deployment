import React, { useState, useEffect } from 'react';
import axios from 'axios';

const CancellationRiskNotice = ({ appointmentDate, appointmentTime, onAlternativeSelected }) => {
  const [riskNotice, setRiskNotice] = useState(null);
  const [alternatives, setAlternatives] = useState([]);
  const [showAlternatives, setShowAlternatives] = useState(false);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (appointmentDate && appointmentTime) {
      checkCancellationRisk();
    }
  }, [appointmentDate, appointmentTime]);

  const checkCancellationRisk = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/analytics/cancellation-risk', {
        params: {
          appointment_date: appointmentDate,
          appointment_time: appointmentTime,
        },
      });

      if (response.data.success) {
        setRiskNotice(response.data.data);
        
        // If high risk, fetch alternatives
        if (response.data.data.show_notice) {
          fetchAlternatives();
        }
      }
    } catch (err) {
      console.error('Error checking cancellation risk:', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchAlternatives = async () => {
    try {
      const response = await axios.get('/api/analytics/alternative-slots', {
        params: {
          appointment_date: appointmentDate,
          appointment_time: appointmentTime,
        },
      });

      if (response.data.success) {
        setAlternatives(response.data.data);
      }
    } catch (err) {
      console.error('Error fetching alternatives:', err);
    }
  };

  if (!riskNotice || !riskNotice.show_notice) {
    return null;
  }

  const isHighRisk = riskNotice.risk_level === 'high';
  const isMediumRisk = riskNotice.risk_level === 'medium';

  return (
    <div className={`p-4 rounded-lg border-l-4 mb-6 ${
      isHighRisk ? 'bg-red-50 border-red-500' : 'bg-yellow-50 border-yellow-500'
    }`}>
      <div className="flex gap-4">
        <div className="flex-shrink-0">
          <span className="text-2xl">{isHighRisk ? '⚠️' : '⚡'}</span>
        </div>
        <div className="flex-1">
          <div className="flex items-center justify-between">
            <div>
              <h3 className={`font-semibold ${isHighRisk ? 'text-red-900' : 'text-yellow-900'}`}>
                {isHighRisk ? 'Very Busy Time Slot' : 'Moderately Busy Time Slot'}
              </h3>
              <p className={`mt-1 text-sm ${isHighRisk ? 'text-red-800' : 'text-yellow-800'}`}>
                {riskNotice.message}
              </p>
            </div>
          </div>

          {/* Utilization Bar */}
          <div className="mt-3">
            <div className="flex items-center gap-2">
              <div className="flex-1 bg-gray-200 rounded-full h-2">
                <div
                  className={`h-full rounded-full transition-all ${
                    isHighRisk ? 'bg-red-500' : 'bg-yellow-500'
                  }`}
                  style={{ width: `${riskNotice.utilization_rate}%` }}
                />
              </div>
              <span className="text-sm font-semibold whitespace-nowrap">
                {riskNotice.utilization_rate}% Full
              </span>
            </div>
            <p className="text-xs text-gray-600 mt-1">
              Currently {riskNotice.current_bookings} of 10 slots booked
            </p>
          </div>

          {/* Alternative Slots */}
          {alternatives.length > 0 && (
            <div className="mt-4">
              <button
                onClick={() => setShowAlternatives(!showAlternatives)}
                className={`text-sm font-semibold flex items-center gap-2 ${
                  isHighRisk ? 'text-red-700 hover:text-red-900' : 'text-yellow-700 hover:text-yellow-900'
                }`}
              >
                <svg className={`w-4 h-4 transition-transform ${showAlternatives ? 'rotate-180' : ''}`} 
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
                View Available Alternatives ({alternatives.length})
              </button>

              {showAlternatives && (
                <div className="mt-3 space-y-2">
                  {alternatives.map((alt, idx) => (
                    <div
                      key={idx}
                      className={`p-3 rounded border cursor-pointer transition hover:shadow ${
                        isHighRisk ? 'bg-red-100 hover:bg-red-200 border-red-300' : 'bg-yellow-100 hover:bg-yellow-200 border-yellow-300'
                      }`}
                      onClick={() => {
                        onAlternativeSelected(alt.date, alt.time);
                        setShowAlternatives(false);
                      }}
                    >
                      <div className="flex items-center justify-between">
                        <div>
                          <p className="font-semibold text-gray-900">{alt.time}</p>
                          <p className="text-xs text-gray-600">{alt.date} • {alt.description}</p>
                        </div>
                        <div className="text-right">
                          <p className="text-sm font-semibold text-green-600">
                            {alt.availability_rate}% Available
                          </p>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default CancellationRiskNotice;
