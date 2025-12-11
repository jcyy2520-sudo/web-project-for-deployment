import React from 'react';

const ChatbotMessage = ({ message }) => {
  const isUser = message.role === 'user';
  const meta = message?.meta || {};
  const actionResult = meta?.action_result || {};
  const data = meta?.data || actionResult?.data || {};

  // Helper to format currency
  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP'
    }).format(amount || 0);
  };

  // Helper to get status badge color
  const getStatusColor = (status) => {
    const colors = {
      'pending': 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
      'approved': 'bg-green-500/20 text-green-300 border-green-500/30',
      'confirmed': 'bg-green-500/20 text-green-300 border-green-500/30',
      'completed': 'bg-blue-500/20 text-blue-300 border-blue-500/30',
      'cancelled': 'bg-red-500/20 text-red-300 border-red-500/30',
      'declined': 'bg-red-500/20 text-red-300 border-red-500/30',
      'paid': 'bg-green-500/20 text-green-300 border-green-500/30',
      'unpaid': 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
    };
    return colors[status?.toLowerCase()] || 'bg-gray-500/20 text-gray-300 border-gray-500/30';
  };

  // Render role badge for assistant messages
  const renderRoleBadge = () => {
    const role = meta?.role;
    if (!role || isUser) return null;
    
    const roleColors = {
      'ADMIN': 'bg-purple-500/20 text-purple-300 border-purple-500/30',
      'CASHIER': 'bg-green-500/20 text-green-300 border-green-500/30',
      'CLIENT': 'bg-blue-500/20 text-blue-300 border-blue-500/30',
      'SYSTEM': 'bg-gray-500/20 text-gray-300 border-gray-500/30',
    };

    return (
      <span className={`text-[10px] px-2 py-0.5 rounded-full border ${roleColors[role] || roleColors['SYSTEM']} mb-2 inline-block`}>
        {role}
      </span>
    );
  };

  // Render action result badge
  const renderActionBadge = () => {
    const actionIntent = meta?.action_intent;
    if (!actionIntent || isUser) return null;

    const isSuccess = meta?.success || actionResult?.success;
    const badgeColor = isSuccess 
      ? 'bg-green-500/20 text-green-300 border-green-500/30'
      : 'bg-red-500/20 text-red-300 border-red-500/30';
    
    const actionLabels = {
      'approve_appointment': '✓ Approved',
      'decline_appointment': '✗ Declined',
      'cancel_appointment': '✗ Cancelled',
      'complete_appointment': '✓ Completed',
      'process_payment': '💰 Payment Processed',
      'approve_refund': '✓ Refund Approved',
      'process_refund': '💸 Refund Processed',
      'request_refund': '📋 Refund Requested',
      'view_pending_appointments': '📋 Pending List',
      'view_pending_payments': '💳 Payments',
      'view_pending_refunds': '💸 Refunds',
      'shift_report': '📊 Shift Report',
      'system_health': '🔧 System Status',
    };

    return (
      <span className={`text-[10px] px-2 py-0.5 rounded-full border ${badgeColor} mb-2 ml-2 inline-block`}>
        {actionLabels[actionIntent] || actionIntent}
      </span>
    );
  };

  // Render metrics card for admin/cashier data
  const renderMetrics = () => {
    const metrics = meta?.metrics || actionResult?.metrics;
    if (!metrics || isUser) return null;

    // Filter out complex objects for simple display
    const simpleMetrics = Object.entries(metrics).filter(([key, value]) => 
      typeof value !== 'object' || value === null
    );

    if (simpleMetrics.length === 0) return null;

    return (
      <div className="mt-3 p-3 bg-gray-800 border border-amber-500/10 rounded-lg">
        <div className="text-xs text-gray-400 uppercase tracking-wide mb-2">📊 Metrics</div>
        <div className="grid grid-cols-2 gap-2 text-sm">
          {simpleMetrics.map(([key, value]) => {
            const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            const isMonetary = key.includes('amount') || key.includes('sales') || key.includes('revenue') || key.includes('total') || key.includes('collected') || key.includes('refunded');
            const displayValue = isMonetary && typeof value === 'number'
              ? formatCurrency(value)
              : value;
            return (
              <div key={key} className="flex flex-col">
                <span className="text-gray-400 text-xs">{label}</span>
                <span className="text-gray-200 font-medium">{displayValue}</span>
              </div>
            );
          })}
        </div>
      </div>
    );
  };

  // Render appointments list
  const renderAppointmentsList = () => {
    const appointments = data?.appointments || meta?.metrics?.pending_appointments;
    if (!appointments || !Array.isArray(appointments) || appointments.length === 0) return null;

    return (
      <div className="mt-3 p-3 bg-gray-800 border border-amber-500/10 rounded-lg">
        <div className="text-xs text-gray-400 uppercase tracking-wide mb-2">📅 Appointments</div>
        <div className="space-y-2">
          {appointments.slice(0, 5).map((apt, idx) => (
            <div key={idx} className="flex justify-between items-center text-sm p-2 bg-gray-900/50 rounded">
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <span className="text-gray-200 font-medium">#{apt.id}</span>
                  <span className={`text-[10px] px-2 py-0.5 rounded-full border ${getStatusColor(apt.status)}`}>
                    {apt.status}
                  </span>
                </div>
                <div className="text-xs text-gray-400">{apt.client || apt.service}</div>
                <div className="text-xs text-amber-400">{apt.date} {apt.time && `at ${apt.time}`}</div>
              </div>
              <button
                className="px-2 py-1 text-xs bg-amber-500/20 text-amber-400 rounded hover:bg-amber-500/30 transition-colors"
                onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: `Show details for appointment #${apt.id}` }))}
              >
                Details
              </button>
            </div>
          ))}
          {appointments.length > 5 && (
            <div className="text-xs text-gray-400 text-center">+{appointments.length - 5} more</div>
          )}
        </div>
      </div>
    );
  };

  // Render single appointment details
  const renderAppointmentDetails = () => {
    const apt = data?.appointment;
    if (!apt || isUser) return null;

    return (
      <div className="mt-3 p-3 bg-gray-800 border border-amber-500/10 rounded-lg">
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm font-semibold text-gray-200">Appointment #{apt.id}</span>
          <span className={`text-[10px] px-2 py-0.5 rounded-full border ${getStatusColor(apt.status)}`}>
            {apt.status}
          </span>
        </div>
        <div className="space-y-1 text-sm">
          {apt.client && (
            <div className="flex justify-between">
              <span className="text-gray-400">Client:</span>
              <span className="text-gray-200">{apt.client.name || apt.client}</span>
            </div>
          )}
          {apt.service && (
            <div className="flex justify-between">
              <span className="text-gray-400">Service:</span>
              <span className="text-gray-200">{apt.service.name || apt.service}</span>
            </div>
          )}
          <div className="flex justify-between">
            <span className="text-gray-400">Date:</span>
            <span className="text-amber-400">{apt.date} {apt.time && `at ${apt.time}`}</span>
          </div>
          {apt.payment_status && (
            <div className="flex justify-between">
              <span className="text-gray-400">Payment:</span>
              <span className={apt.payment_status === 'paid' ? 'text-green-400' : 'text-yellow-400'}>
                {apt.payment_status}
              </span>
            </div>
          )}
        </div>
        {apt.status === 'pending' && (
          <div className="mt-3 flex gap-2">
            <button 
              className="flex-1 px-3 py-1.5 bg-green-500/20 text-green-300 rounded text-xs hover:bg-green-500/30 transition-colors"
              onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: `Approve appointment #${apt.id}` }))}
            >
              ✓ Approve
            </button>
            <button 
              className="flex-1 px-3 py-1.5 bg-red-500/20 text-red-300 rounded text-xs hover:bg-red-500/30 transition-colors"
              onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: `Decline appointment #${apt.id}` }))}
            >
              ✗ Decline
            </button>
          </div>
        )}
      </div>
    );
  };

  // Render pending payments list
  const renderPaymentsList = () => {
    const payments = data?.payments || meta?.metrics?.pending_payments;
    if (!payments || !Array.isArray(payments) || payments.length === 0) return null;

    return (
      <div className="mt-3 p-3 bg-gray-800 border border-amber-500/10 rounded-lg">
        <div className="text-xs text-gray-400 uppercase tracking-wide mb-2">💳 Payments</div>
        <div className="space-y-2">
          {payments.slice(0, 5).map((payment, idx) => (
            <div key={idx} className="flex justify-between items-center text-sm p-2 bg-gray-900/50 rounded">
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <span className="text-gray-200">{payment.client}</span>
                  <span className={`text-[10px] px-2 py-0.5 rounded-full border ${getStatusColor(payment.status)}`}>
                    {payment.status}
                  </span>
                </div>
                <div className="text-xs text-gray-400">{payment.service}</div>
              </div>
              <div className="text-right">
                <div className="text-amber-400 font-medium">{formatCurrency(payment.amount)}</div>
                {payment.status !== 'paid' && (
                  <button
                    className="text-[10px] text-green-400 hover:underline"
                    onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: `Process payment #${payment.id}` }))}
                  >
                    Process
                  </button>
                )}
              </div>
            </div>
          ))}
          {payments.length > 5 && (
            <div className="text-xs text-gray-400 text-center">+{payments.length - 5} more</div>
          )}
        </div>
      </div>
    );
  };

  // Render refunds list
  const renderRefundsList = () => {
    const refunds = data?.refunds || meta?.metrics?.pending_refunds;
    if (!refunds || !Array.isArray(refunds) || refunds.length === 0) return null;

    return (
      <div className="mt-3 p-3 bg-gray-800 border border-red-500/10 rounded-lg">
        <div className="text-xs text-gray-400 uppercase tracking-wide mb-2">💸 Refunds</div>
        <div className="space-y-2">
          {refunds.slice(0, 5).map((refund, idx) => (
            <div key={idx} className="flex justify-between items-center text-sm p-2 bg-gray-900/50 rounded">
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <span className="text-gray-200">{refund.client}</span>
                  <span className={`text-[10px] px-2 py-0.5 rounded-full border ${getStatusColor(refund.status)}`}>
                    {refund.status}
                  </span>
                </div>
                <div className="text-xs text-gray-400">{refund.reason?.substring(0, 40)}{refund.reason?.length > 40 ? '...' : ''}</div>
              </div>
              <div className="text-right">
                <div className="text-red-400 font-medium">{formatCurrency(refund.amount)}</div>
                {refund.status === 'pending' && (
                  <button
                    className="text-[10px] text-green-400 hover:underline"
                    onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: `Approve refund #${refund.id}` }))}
                  >
                    Approve
                  </button>
                )}
              </div>
            </div>
          ))}
          {refunds.length > 5 && (
            <div className="text-xs text-gray-400 text-center">+{refunds.length - 5} more</div>
          )}
        </div>
      </div>
    );
  };

  // Render services list
  const renderServicesList = () => {
    const services = data?.services;
    if (!services || !Array.isArray(services) || services.length === 0) return null;

    return (
      <div className="mt-3 p-3 bg-gray-800 border border-amber-500/10 rounded-lg">
        <div className="text-xs text-gray-400 uppercase tracking-wide mb-2">📋 Services</div>
        <div className="space-y-2">
          {services.slice(0, 6).map((service, idx) => (
            <div key={idx} className="flex justify-between items-center text-sm p-2 bg-gray-900/50 rounded">
              <div>
                <div className="text-gray-200">{service.name}</div>
                <div className="text-xs text-gray-400">{service.duration}</div>
              </div>
              <div className="text-amber-400 font-medium">{service.price}</div>
            </div>
          ))}
        </div>
      </div>
    );
  };

  // Render system health status
  const renderSystemHealth = () => {
    const health = data?.health;
    if (!health || isUser) return null;

    const statusColors = {
      'healthy': 'text-green-400',
      'warning': 'text-yellow-400',
      'needs_attention': 'text-red-400',
      'unknown': 'text-gray-400',
    };

    return (
      <div className="mt-3 p-3 bg-gray-800 border border-amber-500/10 rounded-lg">
        <div className="flex items-center justify-between mb-2">
          <span className="text-xs text-gray-400 uppercase tracking-wide">🔧 System Health</span>
          <span className={`text-sm font-medium ${statusColors[health.status] || statusColors.unknown}`}>
            {health.status?.toUpperCase()}
          </span>
        </div>
        {health.issues && health.issues.length > 0 && (
          <div className="space-y-1 mt-2">
            {health.issues.map((issue, idx) => (
              <div key={idx} className="text-xs text-red-300 flex items-center gap-1">
                <span>⚠️</span> {issue}
              </div>
            ))}
          </div>
        )}
        {health.warnings && health.warnings.length > 0 && (
          <div className="space-y-1 mt-2">
            {health.warnings.map((warning, idx) => (
              <div key={idx} className="text-xs text-yellow-300 flex items-center gap-1">
                <span>⚡</span> {warning}
              </div>
            ))}
          </div>
        )}
        {(!health.issues || health.issues.length === 0) && (!health.warnings || health.warnings.length === 0) && (
          <div className="text-xs text-green-300 flex items-center gap-1">
            <span>✓</span> All systems operational
          </div>
        )}
      </div>
    );
  };

  // Render pending items for action required
  const renderPendingItems = () => {
    const pendingItems = data?.pending_items;
    if (!pendingItems || !Array.isArray(pendingItems) || pendingItems.length === 0) return null;

    return (
      <div className="mt-3 p-3 bg-gray-800 border border-yellow-500/10 rounded-lg">
        <div className="text-xs text-gray-400 uppercase tracking-wide mb-2">📋 Items Needing Attention</div>
        <div className="space-y-2">
          {pendingItems.map((item, idx) => (
            <button
              key={idx}
              className="w-full text-left text-sm p-2 bg-gray-900/50 rounded hover:bg-gray-900 transition-colors"
              onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: `Show appointment #${item.id}` }))}
            >
              <div className="flex justify-between items-center">
                <span className="text-amber-400">#{item.id}</span>
                <span className="text-gray-400 text-xs">{item.date}</span>
              </div>
              <div className="text-gray-300">{item.client}</div>
              <div className="text-xs text-gray-500">{item.service}</div>
            </button>
          ))}
        </div>
      </div>
    );
  };

  return (
    <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
      <div
        className={`max-w-[85%] rounded-xl px-4 py-3 ${
          isUser
            ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/20'
            : 'bg-gray-900 text-gray-100 border border-amber-500/20'
        }`}
      >
        <div>
          <div className="flex items-center flex-wrap">
            {renderRoleBadge()}
            {renderActionBadge()}
          </div>
          <p className="text-sm leading-relaxed break-words whitespace-pre-wrap">{message.message}</p>

          {/* Render structured appointment card when present (legacy support) */}
          {message?.meta?.data?.next_appointment && !data?.appointment && (
            <div className="mt-3 p-3 bg-gray-800 border border-amber-500/10 rounded-lg">
              <div className="text-sm text-gray-300 font-semibold">Next appointment</div>
              <div className="mt-2 text-sm text-gray-200">
                <div><strong>Date:</strong> {message.meta.data.next_appointment.date}</div>
                <div><strong>Time:</strong> {message.meta.data.next_appointment.time}</div>
                <div><strong>Service:</strong> {message.meta.data.next_appointment.service}</div>
                <div><strong>Status:</strong> {message.meta.data.next_appointment.status}</div>
              </div>
              <div className="mt-3 flex gap-2">
                <button 
                  className="px-3 py-1 bg-amber-500 text-black rounded text-sm hover:bg-amber-400 transition-colors"
                  onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: 'Reschedule my appointment' }))}
                >
                  Reschedule
                </button>
                <button 
                  className="px-3 py-1 border border-amber-500 text-amber-300 rounded text-sm hover:bg-amber-500/10 transition-colors"
                  onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: 'Cancel my appointment' }))}
                >
                  Cancel
                </button>
              </div>
            </div>
          )}

          {/* Render appointment details (from action) */}
          {renderAppointmentDetails()}

          {/* Render appointments list */}
          {renderAppointmentsList()}

          {/* Render metrics for admin/cashier */}
          {renderMetrics()}

          {/* Render payments list */}
          {renderPaymentsList()}

          {/* Render refunds list */}
          {renderRefundsList()}

          {/* Render services list */}
          {renderServicesList()}

          {/* Render system health */}
          {renderSystemHealth()}

          {/* Render pending items for action required */}
          {renderPendingItems()}

          {/* Render suggestions as clickable buttons */}
          {meta?.suggestions && Array.isArray(meta.suggestions) && meta.suggestions.length > 0 && (
            <div className="mt-3 flex flex-wrap gap-2">
              {meta.suggestions.map((suggestion, idx) => (
                <button
                  key={idx}
                  className="text-xs px-3 py-1.5 bg-gray-800 border border-amber-500/20 text-amber-400 rounded-full hover:bg-amber-500/10 hover:border-amber-500/40 transition-all"
                  onClick={() => {
                    // Dispatch custom event to send suggestion as message
                    window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: suggestion }));
                  }}
                >
                  {suggestion}
                </button>
              ))}
            </div>
          )}

          <span className={`text-xs mt-2 block ${isUser ? 'text-amber-100/70' : 'text-gray-400'}`}>
            {new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
          </span>
        </div>
      </div>
    </div>
  );
};

export default ChatbotMessage;
