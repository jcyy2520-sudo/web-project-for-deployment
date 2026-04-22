import React, { useState } from 'react';

const ChatbotMessage = ({ message, isDarkMode = true, onFeedback, isLastMessage = false }) => {
  const isUser = message.role === 'user';
  const meta = message?.meta || {};
  const actionResult = meta?.action_result || {};
  const data = meta?.data || actionResult?.data || {};

  // Feedback state for assistant messages
  const [feedbackState, setFeedbackState] = useState(message.feedbackGiven ? message.feedbackType : null); // 'helpful' | 'unhelpful' | null
  const [showFeedbackForm, setShowFeedbackForm] = useState(false);
  const [feedbackComment, setFeedbackComment] = useState('');
  const [feedbackSubmitting, setFeedbackSubmitting] = useState(false);

  const handleFeedback = async (type) => {
    if (!onFeedback || feedbackState) return;
    setFeedbackState(type);
    if (type === 'unhelpful') {
      setShowFeedbackForm(true);
      return; // wait for additional feedback
    }
    // Submit positive feedback immediately
    try {
      setFeedbackSubmitting(true);
      await onFeedback(message.id, { is_helpful: true, rating: 5, category: 'helpful' });
    } catch (e) {
      console.error('Feedback failed:', e);
    } finally {
      setFeedbackSubmitting(false);
    }
  };

  const submitNegativeFeedback = async () => {
    if (!onFeedback) return;
    try {
      setFeedbackSubmitting(true);
      await onFeedback(message.id, {
        is_helpful: false,
        rating: 1,
        comments: feedbackComment,
        category: 'unhelpful',
      });
      setShowFeedbackForm(false);
    } catch (e) {
      console.error('Feedback failed:', e);
    } finally {
      setFeedbackSubmitting(false);
    }
  };

  // Check for priority/sentiment indicators
  const isPriority = message?.isPriority || meta?.is_priority;
  const sentiment = message?.sentiment || meta?.sentiment || 'neutral';
  const detectedLanguage = message?.detectedLanguage || meta?.detected_language || 'en';

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
      'completed': 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30',
      'cancelled': 'bg-red-500/20 text-red-300 border-red-500/30',
      'declined': 'bg-red-500/20 text-red-300 border-red-500/30',
      'paid': 'bg-green-500/20 text-green-300 border-green-500/30',
      'unpaid': 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
    };
    return colors[status?.toLowerCase()] || 'bg-gray-500/20 text-gray-300 border-gray-500/30';
  };

  // Get sentiment color
  const getSentimentIndicator = () => {
    if (isUser) return null;
    
    const sentimentConfig = {
      'positive': { color: 'text-green-400', icon: '😊' },
      'neutral': { color: 'text-gray-400', icon: null },
      'frustrated': { color: 'text-yellow-400', icon: '😤' },
      'angry': { color: 'text-red-400', icon: '😠' },
    };
    
    const config = sentimentConfig[sentiment] || sentimentConfig['neutral'];
    return config.icon ? (
      <span className={`text-xs ${config.color}`} title={`Sentiment: ${sentiment}`}>
        {config.icon}
      </span>
    ) : null;
  };

  // Render language indicator
  const renderLanguageIndicator = () => {
    if (isUser || detectedLanguage === 'en') return null;
    
    const langNames = {
      'tl': '🇵🇭 Filipino',
      'es': '🇪🇸 Spanish',
      'zh': '🇨🇳 Chinese',
      'ja': '🇯🇵 Japanese',
      'ko': '🇰🇷 Korean',
    };
    
    const langName = langNames[detectedLanguage];
    if (!langName) return null;
    
    return (
      <span className="text-[10px] text-gray-500 ml-2" title={`Detected language: ${langName}`}>
        {langName}
      </span>
    );
  };

  // Render role badge for assistant messages
  const renderRoleBadge = () => {
    const role = meta?.role;
    if (!role || isUser) return null;
    
    const roleConfig = {
      'admin': { color: 'bg-purple-500/20 text-purple-300 border-purple-500/30', lightColor: 'bg-purple-50 text-purple-700 border-purple-200', icon: '🛡️' },
      'cashier': { color: 'bg-green-500/20 text-green-300 border-green-500/30', lightColor: 'bg-green-50 text-green-700 border-green-200', icon: '💼' },
      'staff': { color: 'bg-blue-500/20 text-blue-300 border-blue-500/30', lightColor: 'bg-blue-50 text-blue-700 border-blue-200', icon: '👷' },
      'client': { color: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30', lightColor: 'bg-cyan-50 text-cyan-700 border-cyan-200', icon: '👤' },
      'guest': { color: 'bg-gray-500/20 text-gray-300 border-gray-500/30', lightColor: 'bg-gray-100 text-gray-600 border-gray-200', icon: '👋' },
    };

    const normalizedRole = role.toLowerCase();
    const config = roleConfig[normalizedRole] || { color: 'bg-gray-500/20 text-gray-300 border-gray-500/30', lightColor: 'bg-gray-100 text-gray-600 border-gray-200', icon: '⚙️' };
    const displayName = meta?.role_display || role;

    return (
      <span className={`text-[10px] px-2 py-0.5 rounded-full border ${isDarkMode ? config.color : config.lightColor} mb-2 inline-flex items-center gap-1`}>
        <span>{config.icon}</span>
        {displayName}
      </span>
    );
  };

  // Render quick actions bar from meta — only shown on the last assistant message
  const renderQuickActions = () => {
    if (!isLastMessage) return null;
    const quickActions = meta?.quick_actions;
    if (!quickActions || !Array.isArray(quickActions) || quickActions.length === 0 || isUser) return null;

    return (
      <div className={`mt-3 p-2 rounded-xl ${isDarkMode ? 'bg-gray-800/50 border border-gray-700/30' : 'bg-gray-50 border border-gray-100'}`}>
        <div className={`text-[10px] uppercase tracking-wide mb-1.5 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>⚡ Related Actions</div>
        <div className="flex flex-wrap gap-1.5">
          {quickActions.slice(0, 4).map((action, idx) => (
            <button
              key={`qa-${idx}`}
              className={`text-xs px-2.5 py-1.5 rounded-xl transition-all flex items-center gap-1 ${
                isDarkMode
                  ? 'bg-gray-900 border border-amber-500/20 text-amber-400 hover:bg-amber-500/10 hover:border-amber-500/40'
                  : 'bg-white border border-gray-200/80 text-gray-600 hover:border-amber-200 hover:text-amber-600 hover:shadow-sm'
              }`}
              onClick={() => {
                if (action.route) {
                  window.dispatchEvent(new CustomEvent('chatbot-navigate', { detail: { route: action.route } }));
                } else if (action.message) {
                  window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: action.message }));
                }
              }}
              title={action.label}
            >
              {action.icon && <span>{action.icon}</span>}
              <span className="truncate max-w-[120px]">{action.label}</span>
            </button>
          ))}
        </div>
      </div>
    );
  };

  // Render pending items alert from role context — only on last message
  const renderRolePendingItems = () => {
    if (!isLastMessage) return null;
    const pendingItems = meta?.pending_items;
    if (!pendingItems || !Array.isArray(pendingItems) || pendingItems.length === 0 || isUser) return null;

    // Only show items that have a count > 0
    const activeItems = pendingItems.filter(item => item.count > 0);
    if (activeItems.length === 0) return null;

    return (
      <div className={`mt-3 p-2.5 rounded-lg ${isDarkMode ? 'bg-amber-500/5 border border-amber-500/15' : 'bg-amber-50 border border-amber-100'}`}>
        <div className={`text-[10px] uppercase tracking-wide mb-1.5 ${isDarkMode ? 'text-amber-400/70' : 'text-amber-600'}`}>📋 Needs Your Attention</div>
        <div className="flex flex-wrap gap-2">
          {activeItems.map((item, idx) => (
            <button
              key={`pi-${idx}`}
              className={`text-xs px-2.5 py-1.5 rounded-lg transition-all flex items-center gap-1.5 ${
                isDarkMode
                  ? 'bg-gray-900/80 border border-amber-500/20 text-gray-300 hover:border-amber-500/40 hover:text-amber-300'
                  : 'bg-white border border-amber-200 text-gray-700 hover:bg-amber-50'
              }`}
              onClick={() => {
                if (item.route) {
                  window.dispatchEvent(new CustomEvent('chatbot-navigate', { detail: { route: item.route } }));
                } else {
                  window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: `Show me ${item.label?.toLowerCase()}` }));
                }
              }}
            >
              <span className={`font-bold ${
                item.count >= 5
                  ? (isDarkMode ? 'text-red-400' : 'text-red-600')
                  : (isDarkMode ? 'text-amber-400' : 'text-amber-600')
              }`}>{item.count}</span>
              <span className="truncate max-w-[100px]">{item.label}</span>
            </button>
          ))}
        </div>
      </div>
    );
  };

  // Render priority indicator
  const renderPriorityIndicator = () => {
    if (!isPriority || isUser) return null;
    
    return (
      <span className="text-[10px] px-2 py-0.5 rounded-full border bg-red-500/20 text-red-300 border-red-500/30 mb-2 ml-2 inline-block animate-pulse">
        ⚡ Priority Response
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
      <div className={`mt-3 p-3 rounded-lg ${isDarkMode ? 'bg-gray-800 border border-gray-700/30' : 'bg-white border border-gray-100'}`}>
        <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} uppercase tracking-wide mb-2`}>📊 Metrics</div>
        <div className="grid grid-cols-2 gap-2 text-sm">
          {simpleMetrics.map(([key, value]) => {
            const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            const isMonetary = key.includes('amount') || key.includes('sales') || key.includes('revenue') || key.includes('total') || key.includes('collected') || key.includes('refunded');
            const displayValue = isMonetary && typeof value === 'number'
              ? formatCurrency(value)
              : value;
            return (
              <div key={key} className="flex flex-col">
                <span className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs`}>{label}</span>
                <span className={`${isDarkMode ? 'text-gray-200' : 'text-gray-700'} font-medium`}>{displayValue}</span>
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
      <div className={`mt-3 p-3 rounded-lg ${isDarkMode ? 'bg-gray-800 border border-gray-700/30' : 'bg-white border border-gray-100'}`}>
        <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} uppercase tracking-wide mb-2`}>📅 Appointments</div>
        <div className="space-y-2">
            {appointments.slice(0, 5).map((apt, idx) => (
              <div key={idx} className={`flex justify-between items-center text-sm p-2 rounded ${isDarkMode ? 'bg-gray-900/50' : 'bg-white'}`}>
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <span className={`${isDarkMode ? 'text-gray-200' : 'text-gray-700'} font-medium`}>#{apt.id}</span>
                  <span className={`text-[10px] px-2 py-0.5 rounded-full border ${getStatusColor(apt.status)}`}>
                    {apt.status}
                  </span>
                </div>
                <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{apt.client || apt.service}</div>
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
      <div className={`mt-3 p-3 rounded-lg ${isDarkMode ? 'bg-gray-800 border border-gray-700/30' : 'bg-white border border-gray-100'}`}>
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
      <div className="mt-3 p-3 bg-gray-800 border border-gray-700/30 rounded-lg">
        <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} uppercase tracking-wide mb-2`}>💳 Payments</div>
        <div className="space-y-2">
            {payments.slice(0, 5).map((payment, idx) => (
              <div key={idx} className={`flex justify-between items-center text-sm p-2 rounded ${isDarkMode ? 'bg-gray-900/50' : 'bg-white'}`}>
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <span className={`${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>{payment.client}</span>
                  <span className={`text-[10px] px-2 py-0.5 rounded-full border ${getStatusColor(payment.status)}`}>
                    {payment.status}
                  </span>
                </div>
                <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{payment.service}</div>
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
      <div className={`mt-3 p-3 rounded-lg ${isDarkMode ? 'bg-gray-800 border border-red-500/10' : 'bg-white border border-red-100'}`}>
        <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} uppercase tracking-wide mb-2`}>💸 Refunds</div>
        <div className="space-y-2">
            {refunds.slice(0, 5).map((refund, idx) => (
              <div key={idx} className={`flex justify-between items-center text-sm p-2 rounded ${isDarkMode ? 'bg-gray-900/50' : 'bg-white'}`}>
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <span className={`${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>{refund.client}</span>
                  <span className={`text-[10px] px-2 py-0.5 rounded-full border ${getStatusColor(refund.status)}`}>
                    {refund.status}
                  </span>
                </div>
                <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{refund.reason?.substring(0, 40)}{refund.reason?.length > 40 ? '...' : ''}</div>
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
      <div className={`mt-3 p-3 rounded-lg ${isDarkMode ? 'bg-gray-800 border border-gray-700/30' : 'bg-white border border-gray-100'}`}>
        <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} uppercase tracking-wide mb-2`}>📋 Services</div>
        <div className="space-y-2">
            {services.slice(0, 6).map((service, idx) => (
            <div key={idx} className={`flex justify-between items-center text-sm p-2 rounded ${isDarkMode ? 'bg-gray-900/50' : 'bg-white'}`}>
              <div>
                <div className={`${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>{service.name}</div>
                <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{service.duration}</div>
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
      <div className={`mt-3 p-3 rounded-lg ${isDarkMode ? 'bg-gray-800 border border-gray-700/30' : 'bg-white border border-gray-100'}`}>
        <div className="flex items-center justify-between mb-2">
            <span className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} uppercase tracking-wide`}>🔧 System Health</span>
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
      <div className={`mt-3 p-3 rounded-lg ${isDarkMode ? 'bg-gray-800 border border-yellow-500/10' : 'bg-white border border-yellow-100'}`}>
        <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} uppercase tracking-wide mb-2`}>Items Needing Attention</div>
        <div className="space-y-2">
              {pendingItems.map((item, idx) => (
                <button
                  key={idx}
                  className={`w-full text-left text-sm p-2 rounded hover:bg-gray-900 transition-colors ${isDarkMode ? 'bg-gray-900/50' : 'bg-white'}`}
              onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: `Show appointment #${item.id}` }))}
            >
              <div className="flex justify-between items-center">
                <span className="text-amber-400">#{item.id}</span>
                <span className="text-gray-400 text-xs">{item.date}</span>
              </div>
                <div className={`${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{item.client}</div>
              <div className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-500'}`}>{item.service}</div>
            </button>
          ))}
        </div>
      </div>
    );
  };

  // Enhanced markdown formatter for chatbot messages
  const formatMessageContent = (text) => {
    if (!text || isUser) return text;

    const lines = text.split('\n');
    const elements = [];
    let inList = false;

    lines.forEach((line, lineIdx) => {
      // Process inline formatting: **bold**, *italic*, `code`
      const processInline = (str) => {
        const parts = [];
        let remaining = str;
        let key = 0;

        while (remaining.length > 0) {
          // Match **bold**, *italic*, `code`, or plain text
          const match = remaining.match(/(\*\*(.+?)\*\*|\*(.+?)\*|`(.+?)`)/);
          if (match) {
            const before = remaining.substring(0, match.index);
            if (before) parts.push(<span key={`t-${lineIdx}-${key++}`}>{before}</span>);

            if (match[2]) {
              // **bold**
              parts.push(<strong key={`b-${lineIdx}-${key++}`} className="font-semibold">{match[2]}</strong>);
            } else if (match[3]) {
              // *italic*
              parts.push(<em key={`i-${lineIdx}-${key++}`}>{match[3]}</em>);
            } else if (match[4]) {
              // `code`
              parts.push(<code key={`c-${lineIdx}-${key++}`} className={`px-1 py-0.5 rounded text-xs font-mono ${isDarkMode ? 'bg-amber-900/30 text-amber-300' : 'bg-amber-50 text-amber-700'}`}>{match[4]}</code>);
            }
            remaining = remaining.substring(match.index + match[0].length);
          } else {
            parts.push(<span key={`t-${lineIdx}-${key++}`}>{remaining}</span>);
            remaining = '';
          }
        }
        return parts;
      };

      const trimmed = line.trim();

      if (trimmed === '') {
        inList = false;
        elements.push(<br key={`br-${lineIdx}`} />);
      } else if (trimmed.match(/^---+$/)) {
        // Horizontal rule
        inList = false;
        elements.push(
          <hr key={`hr-${lineIdx}`} className={`my-2 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-100'}`} />
        );
      } else if (trimmed.match(/^>\s+/)) {
        // Blockquote
        inList = false;
        const text = trimmed.replace(/^>\s+/, '');
        elements.push(
          <span key={`bq-${lineIdx}`} className={`block pl-3 border-l-2 ${isDarkMode ? 'border-amber-500/40 text-gray-300' : 'border-amber-300 text-gray-600'} italic my-1`}>
            {processInline(text)}
          </span>
        );
      } else if (trimmed.match(/^#{1,3}\s+/)) {
        // Headings: # ## ###
        inList = false;
        const level = trimmed.match(/^(#{1,3})\s+/)[1].length;
        const text = trimmed.replace(/^#{1,3}\s+/, '');
        const className = level === 1 ? 'font-bold text-base mt-2 mb-1' : level === 2 ? 'font-semibold text-sm mt-1.5 mb-0.5' : 'font-medium text-sm mt-1';
        elements.push(
          <span key={`h-${lineIdx}`} className={`block ${className}`}>
            {processInline(text)}
          </span>
        );
      } else if (trimmed.match(/^[-*•]\s+/)) {
        // Bullet list items
        inList = true;
        const text = trimmed.replace(/^[-*•]\s+/, '');
        elements.push(
          <span key={`li-${lineIdx}`} className="block pl-4 relative before:content-['•'] before:absolute before:left-1 before:text-amber-400">
            {processInline(text)}
          </span>
        );
      } else if (trimmed.match(/^\d+\.\s+/)) {
        // Numbered list items
        inList = true;
        const num = trimmed.match(/^(\d+)\./)[1];
        const text = trimmed.replace(/^\d+\.\s+/, '');
        elements.push(
          <span key={`oli-${lineIdx}`} className="block pl-5 relative">
            <span className="absolute left-0 text-amber-400 font-medium">{num}.</span>
            {processInline(text)}
          </span>
        );
      } else {
        elements.push(
          <span key={`line-${lineIdx}`} className="block">
            {processInline(line)}
          </span>
        );
      }
    });

    return elements;
  };

  // Render confirmation buttons for destructive actions
  const renderConfirmationButtons = () => {
    if (!meta?.requires_confirmation || isUser || !isLastMessage) return null;

    return (
      <div className={`mt-3 p-3 rounded-lg ${isDarkMode ? 'bg-amber-500/5 border border-amber-500/20' : 'bg-amber-50 border border-amber-200'}`}>
        <div className={`text-[10px] uppercase tracking-wide mb-2 ${isDarkMode ? 'text-amber-400/70' : 'text-amber-600'}`}>⚠️ Confirmation Required</div>
        <div className="flex gap-2">
          <button
            className={`flex-1 px-3 py-2 rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1.5 ${
              isDarkMode
                ? 'bg-green-500/20 text-green-300 border border-green-500/30 hover:bg-green-500/30'
                : 'bg-green-50 text-green-700 border border-green-200 hover:bg-green-100'
            }`}
            onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: 'yes' }))}
          >
            <span>✓</span> Confirm
          </button>
          <button
            className={`flex-1 px-3 py-2 rounded-lg text-xs font-medium transition-all flex items-center justify-center gap-1.5 ${
              isDarkMode
                ? 'bg-red-500/20 text-red-300 border border-red-500/30 hover:bg-red-500/30'
                : 'bg-red-50 text-red-700 border border-red-200 hover:bg-red-100'
            }`}
            onClick={() => window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: 'no' }))}
          >
            <span>✗</span> Cancel
          </button>
        </div>
      </div>
    );
  };

  return (
    <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
      <div
        className={`max-w-[85%] rounded-2xl px-4 py-3 ${
          isUser
            ? (isDarkMode 
                ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/20' 
                : 'bg-[#FF9100] text-white shadow-md shadow-amber-200 border border-amber-500/10')
            : isPriority 
              ? (isDarkMode ? 'bg-gray-900 text-gray-100 border-2 border-red-500/40 ring-2 ring-red-500/20' : 'bg-red-50 text-red-700 border-2 border-red-200')
              : (isDarkMode ? 'bg-gray-900 text-gray-100 border border-gray-700/50' : 'bg-white text-gray-800 border border-slate-200 shadow-sm')
        }`}
      >
        <div>
          <div className="flex items-center flex-wrap gap-1">
            {renderRoleBadge()}
            {renderPriorityIndicator()}
            {renderActionBadge()}
            {getSentimentIndicator()}
          </div>
          <div className="text-sm leading-relaxed break-words">{formatMessageContent(message.message)}</div>

          {/* Render structured appointment card when present (legacy support) */}
          {message?.meta?.data?.next_appointment && !data?.appointment && (
            <div className={`mt-3 p-3 rounded-lg ${isDarkMode ? 'bg-gray-800 border border-gray-700/30' : 'bg-white border border-gray-100'}`}>
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

          {/* Render confirmation buttons for destructive actions */}
          {renderConfirmationButtons()}

          {/* Render role-based pending items alert */}
          {renderRolePendingItems()}

          {/* Render quick actions from role context */}
          {renderQuickActions()}

          {/* Render action buttons with navigation links */}
          {meta?.action_buttons && Array.isArray(meta.action_buttons) && meta.action_buttons.length > 0 && (
            <div className="mt-3 flex flex-wrap gap-2">
              {meta.action_buttons.map((action, idx) => (
                <button
                  key={idx}
                  className={`text-xs px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5 ${
                    action.type === 'primary'
                      ? (isDarkMode ? 'bg-amber-500 text-gray-900 hover:bg-amber-400' : 'bg-gradient-to-r from-orange-500 to-purple-500 text-white hover:shadow-md')
                      : action.type === 'danger'
                      ? (isDarkMode ? 'bg-red-500/20 text-red-300 border border-red-500/30 hover:bg-red-500/30' : 'bg-red-50 text-red-600 border border-red-100')
                      : (isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-amber-400 hover:bg-amber-500/10 hover:border-amber-500/40' : 'bg-white border border-gray-200/80 text-gray-600 hover:border-purple-200 hover:text-purple-600')
                  }`}
                  onClick={() => {
                    if (action.route) {
                      // Navigate to the route
                      window.dispatchEvent(new CustomEvent('chatbot-navigate', { detail: { route: action.route } }));
                    } else if (action.message) {
                      // Send a follow-up message
                      window.dispatchEvent(new CustomEvent('chatbot-suggestion', { detail: action.message }));
                    }
                  }}
                >
                  {action.icon && <span>{action.icon}</span>}
                  {action.label}
                  {action.route && (
                    <svg className="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                  )}
                </button>
              ))}
            </div>
          )}

          {/* Render suggestions as clickable buttons */}
          {meta?.suggestions && Array.isArray(meta.suggestions) && meta.suggestions.length > 0 && (
            <div className="mt-3 flex flex-wrap gap-2">
              {meta.suggestions.map((suggestion, idx) => (
                <button
                  key={idx}
                  className={`text-xs px-3 py-1.5 rounded-full transition-all ${isDarkMode ? 'bg-gray-800 border border-amber-500/20 text-amber-400 hover:bg-amber-500/10 hover:border-amber-500/40' : 'bg-white border border-gray-200/80 text-gray-600 hover:border-purple-200 hover:text-purple-600'}`}
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

          <div className="flex items-center justify-between mt-2">
            <span className={`text-xs ${isUser ? 'text-white/70' : (isDarkMode ? 'text-gray-400' : 'text-gray-400')}`}>
              {new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
              {renderLanguageIndicator()}
            </span>

            {/* Feedback thumbs for assistant messages */}
            {!isUser && onFeedback && (
              <div className="flex items-center gap-1 ml-2">
                {feedbackState === 'helpful' ? (
                  <span className="text-green-400 text-xs flex items-center gap-1" title="You found this helpful">
                    <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z" /></svg>
                    Thanks!
                  </span>
                ) : feedbackState === 'unhelpful' ? (
                  <span className="text-red-400 text-xs flex items-center gap-1" title="You reported this as unhelpful">
                    <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M18 9.5a1.5 1.5 0 11-3 0v-6a1.5 1.5 0 013 0v6zM14 9.667v-5.43a2 2 0 00-1.106-1.79l-.05-.025A4 4 0 0011.057 2H5.64a2 2 0 00-1.962 1.608l-1.2 6A2 2 0 004.44 12H8v4a2 2 0 002 2 1 1 0 001-1v-.667a4 4 0 01.8-2.4l1.4-1.866a4 4 0 00.8-2.4z" /></svg>
                    Noted
                  </span>
                ) : (
                  <>
                    <button
                      onClick={() => handleFeedback('helpful')}
                      disabled={feedbackSubmitting}
                      className={`p-1 rounded transition-all ${isDarkMode ? 'text-gray-500 hover:text-green-400 hover:bg-green-500/10' : 'text-gray-400 hover:text-green-600 hover:bg-green-50'}`}
                      title="Helpful"
                    >
                      <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 20 20"><path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z" /></svg>
                    </button>
                    <button
                      onClick={() => handleFeedback('unhelpful')}
                      disabled={feedbackSubmitting}
                      className={`p-1 rounded transition-all ${isDarkMode ? 'text-gray-500 hover:text-red-400 hover:bg-red-500/10' : 'text-gray-400 hover:text-red-600 hover:bg-red-50'}`}
                      title="Not helpful"
                    >
                      <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 20 20"><path d="M18 9.5a1.5 1.5 0 11-3 0v-6a1.5 1.5 0 013 0v6zM14 9.667v-5.43a2 2 0 00-1.106-1.79l-.05-.025A4 4 0 0011.057 2H5.64a2 2 0 00-1.962 1.608l-1.2 6A2 2 0 004.44 12H8v4a2 2 0 002 2 1 1 0 001-1v-.667a4 4 0 01.8-2.4l1.4-1.866a4 4 0 00.8-2.4z" /></svg>
                    </button>
                  </>
                )}
              </div>
            )}
          </div>

          {/* Expanded negative feedback form */}
          {showFeedbackForm && feedbackState === 'unhelpful' && (
            <div className={`mt-2 p-2 rounded-lg ${isDarkMode ? 'bg-gray-800 border border-red-500/20' : 'bg-red-50 border border-red-100'}`}>
              <textarea
                value={feedbackComment}
                onChange={(e) => setFeedbackComment(e.target.value)}
                placeholder="What was wrong? (optional)"
                rows={2}
                className={`w-full text-xs rounded p-2 resize-none ${isDarkMode ? 'bg-gray-900 border-gray-700 text-gray-300 placeholder-gray-500' : 'bg-white border-gray-200 text-gray-700 placeholder-gray-400'} border focus:outline-none focus:ring-1 focus:ring-purple-400/50`}
              />
              <div className="flex gap-2 mt-1.5">
                <button
                  onClick={submitNegativeFeedback}
                  disabled={feedbackSubmitting}
                  className={`text-xs px-3 py-1 rounded transition-all ${isDarkMode ? 'bg-amber-500 text-gray-900 hover:bg-amber-400' : 'bg-gradient-to-r from-orange-500 to-purple-500 text-white hover:shadow-md'} ${feedbackSubmitting ? 'opacity-50' : ''}`}
                >
                  {feedbackSubmitting ? 'Sending...' : 'Submit'}
                </button>
                <button
                  onClick={() => { setShowFeedbackForm(false); setFeedbackState(null); }}
                  className={`text-xs px-3 py-1 rounded transition-all ${isDarkMode ? 'text-gray-400 hover:text-gray-300' : 'text-gray-500 hover:text-gray-700'}`}
                >
                  Cancel
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default ChatbotMessage;
