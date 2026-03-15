import React, { useMemo } from 'react';
import {
  CalendarDaysIcon,
  ClockIcon,
  LightBulbIcon,
  SparklesIcon,
} from '@heroicons/react/24/outline';

/**
 * UserInsights - Personal appointment summary widget for the user dashboard.
 * Shows user-relevant info: upcoming schedule, favorite service, and helpful tips.
 * (Not admin-style analytics — that data is in StatsCards above.)
 *
 * Props:
 *  - appointments: array of user's appointments
 *  - isDarkMode: boolean
 */
const UserInsights = ({ appointments = [], isDarkMode = false }) => {
  const insights = useMemo(() => {
    if (!appointments || appointments.length === 0) {
      return null;
    }

    const now = new Date();
    const pending = appointments.filter(a => a.status === 'pending').length;
    const approved = appointments.filter(a => a.status === 'approved').length;
    const completed = appointments.filter(a => a.status === 'completed').length;

    // Upcoming appointments (approved or pending, future date)
    const upcoming = appointments.filter(a => {
      const apptDate = new Date(a.appointment_date);
      return (a.status === 'pending' || a.status === 'approved') && apptDate >= new Date(now.toDateString());
    }).sort((a, b) => new Date(a.appointment_date) - new Date(b.appointment_date));

    // Next appointment
    const nextAppointment = upcoming.length > 0 ? upcoming[0] : null;

    // Most used service
    const serviceCount = {};
    appointments.forEach(a => {
      const name = a.service?.name || a.appointment_type?.name || a.service_name || 'Unknown';
      serviceCount[name] = (serviceCount[name] || 0) + 1;
    });
    const mostUsedService = Object.entries(serviceCount).sort((a, b) => b[1] - a[1])[0];

    // Smart tips — personal & helpful
    const tips = [];
    if (pending > 0) {
      tips.push(`You have ${pending} pending appointment${pending > 1 ? 's' : ''} awaiting confirmation.`);
    }
    if (approved > 0) {
      tips.push(`${approved} confirmed appointment${approved > 1 ? 's' : ''} coming up — don't forget to prepare your documents.`);
    }
    if (upcoming.length === 0 && completed > 0) {
      tips.push('No upcoming appointments — ready to book your next one?');
    }
    if (mostUsedService && mostUsedService[1] >= 2) {
      tips.push(`Your most-used service is "${mostUsedService[0]}" (${mostUsedService[1]} bookings).`);
    }

    return {
      upcoming,
      nextAppointment,
      mostUsedService,
      tips,
    };
  }, [appointments]);

  if (!insights) {
    return null; // No appointments yet — don't show the widget
  }

  const formatDate = (dateStr) => {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
  };

  const formatTimeAmPm = (timeStr) => {
    if (!timeStr) return '';
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
    return `${displayHour}:${minutes} ${ampm}`;
  };

  // Color helpers
  const cardBg = isDarkMode ? 'bg-gray-900 border-gray-700/50' : 'bg-white border-gray-200';
  const textPrimary = isDarkMode ? 'text-amber-50' : 'text-gray-900';
  const textSecondary = isDarkMode ? 'text-gray-400' : 'text-gray-500';
  const textAccent = isDarkMode ? 'text-amber-400' : 'text-amber-600';
  const sectionBorder = isDarkMode ? 'border-gray-700/40' : 'border-gray-100';

  return (
    <div className={`${isDarkMode ? 'bg-gray-900/80 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 sm:p-5 hover:border-amber-500/40 transition-all duration-300`}>
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <h3 className={`text-sm font-semibold ${textPrimary} flex items-center gap-2`}>
          <SparklesIcon className={`h-4 w-4 ${textAccent}`} />
          Your Summary
        </h3>
      </div>

      {/* Next Appointment + Upcoming + Favorite Service */}
      <div className={`grid grid-cols-1 sm:grid-cols-2 gap-3 ${insights.tips.length > 0 ? 'mb-4' : ''}`}>
        {/* Next Appointment */}
        <div className={`p-3 rounded-lg border ${cardBg}`}>
          <div className={`text-[10px] uppercase tracking-wider font-medium ${textSecondary} mb-1.5 flex items-center gap-1`}>
            <CalendarDaysIcon className="h-3 w-3" />
            Next Appointment
          </div>
          {insights.nextAppointment ? (
            <div>
              <div className={`text-sm font-semibold ${textPrimary}`}>
                {formatDate(insights.nextAppointment.appointment_date)}
              </div>
              <div className={`text-xs ${textSecondary} mt-0.5`}>
                {formatTimeAmPm(insights.nextAppointment.appointment_time)}
                {' — '}
                {insights.nextAppointment.service?.name || insights.nextAppointment.appointment_type?.name || insights.nextAppointment.service_name || 'Service'}
              </div>
              <div className={`mt-1.5 inline-block text-[10px] px-2 py-0.5 rounded-full font-medium ${
                insights.nextAppointment.status === 'approved' 
                  ? (isDarkMode ? 'bg-emerald-500/15 text-emerald-300' : 'bg-emerald-50 text-emerald-700')
                  : (isDarkMode ? 'bg-yellow-500/15 text-yellow-300' : 'bg-yellow-50 text-yellow-700')
              }`}>
                {insights.nextAppointment.status === 'approved' ? 'Confirmed' : 'Awaiting Confirmation'}
              </div>
            </div>
          ) : (
            <div className={`text-xs ${textSecondary}`}>No upcoming appointments</div>
          )}
        </div>

        {/* Upcoming + Favorite Service */}
        <div className={`p-3 rounded-lg border ${cardBg}`}>
          <div className={`text-[10px] uppercase tracking-wider font-medium ${textSecondary} mb-1.5 flex items-center gap-1`}>
            <ClockIcon className="h-3 w-3" />
            At a Glance
          </div>
          <div className={`text-sm font-semibold ${textPrimary}`}>
            {insights.upcoming.length} upcoming
            <span className={`text-xs font-normal ${textSecondary} ml-1`}>appointment{insights.upcoming.length !== 1 ? 's' : ''}</span>
          </div>
          {insights.mostUsedService && (
            <div className={`mt-2 text-[11px] ${textSecondary}`}>
              Favorite service: <span className={textAccent}>{insights.mostUsedService[0]}</span>
            </div>
          )}
        </div>
      </div>

      {/* Smart Tips */}
      {insights.tips.length > 0 && (
        <div className={`border-t pt-3 ${sectionBorder}`}>
          <div className={`flex items-center gap-1.5 text-[10px] uppercase tracking-wider font-medium ${textSecondary} mb-2`}>
            <LightBulbIcon className={`h-3 w-3 ${textAccent}`} />
            Reminders
          </div>
          <div className="space-y-1.5">
            {insights.tips.slice(0, 3).map((tip, i) => (
              <div key={i} className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} flex items-start gap-2`}>
                <span className={`mt-0.5 w-1 h-1 rounded-full flex-shrink-0 ${isDarkMode ? 'bg-amber-400/60' : 'bg-amber-500'}`} />
                {tip}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default UserInsights;
