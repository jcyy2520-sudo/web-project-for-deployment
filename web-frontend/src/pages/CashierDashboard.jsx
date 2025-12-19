import { useState, useEffect, useMemo, useCallback } from 'react';
import { useAuth } from '../context/AuthContext';
import { useApi } from '../hooks/useApi';
import axios from 'axios';
import { 
  CalendarIcon, 
  DocumentTextIcon,
  ChartBarIcon,
  HomeIcon,
  ClockIcon,
  EyeIcon,
  XMarkIcon,
  CheckCircleIcon,
  XCircleIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  ArrowPathIcon,
  ChartPieIcon,
  BuildingLibraryIcon,
  CurrencyDollarIcon,
  UserCircleIcon,
  Bars3Icon,
  PrinterIcon,
  DocumentArrowDownIcon,
  ReceiptPercentIcon,
  AcademicCapIcon,
  UserIcon,
  InformationCircleIcon,
  ShieldCheckIcon,
  LockClosedIcon
} from '@heroicons/react/24/outline';
import LoadingSpinner from '../components/LoadingSpinner';
import LineChart from '../components/charts/LineChart';
import InteractiveCalendar from '../components/calendar/InteractiveCalendar';
import CalendarDetailPanel from '../components/calendar/CalendarDetailPanel';
import AdminMessages from '../components/admin/AdminMessages';
import { formatServiceName, formatPrice } from '../utils/format';

// Chart Components (copied from admin)
const BarChart = ({ data, title, color = 'amber', height = 160, isDarkMode = true }) => {
  const safeData = useMemo(() => 
    data.map(item => ({ ...item, value: Number(item.value) || 0 })), 
    [data]
  );
  const maxValue = Math.max(...safeData.map(item => item.value), 1);
  
  return (
    <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 hover:border-amber-500/40 transition-all duration-300 overflow-auto max-h-[280px]`}>
      <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3 flex items-center`}>
        <ChartBarIcon className="h-4 w-4 mr-2" />
        {title}
      </h3>
      <div className="space-y-2" style={{ height: `${height}px` }}>
        {safeData.map((item, index) => (
          <div key={index} className="flex items-center justify-between group" title={`${item.label}: ${item.value}`}>
            <span className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} w-16 truncate group-hover:text-amber-500 transition-colors`}>
              {item.label}
            </span>
            <div className="flex-1 mx-2">
              <div 
                className="h-4 rounded-md bg-gradient-to-r from-amber-500 to-amber-600 transition-all duration-500 group-hover:from-amber-400 group-hover:to-amber-500 shadow group-hover:shadow-amber-500/25 relative overflow-hidden"
                style={{ 
                  width: `${(item.value / maxValue) * 100}%`,
                  maxWidth: '100%'
                }}
              >
                <div className="absolute inset-0 bg-white/10"></div>
              </div>
            </div>
            <span className={`text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} w-6 text-right group-hover:scale-110 transition-transform`}>
              {item.value}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
};

const PieChart = ({ data, title, isDarkMode = true }) => {
  const safeData = useMemo(() => 
    data.map(item => ({ ...item, value: Number(item.value) || 0 })), 
    [data]
  );
  const total = Math.max(safeData.reduce((sum, item) => sum + item.value, 0), 1);
  let currentAngle = 0;
  
  return (
    <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4 hover:border-amber-500/40 transition-all duration-300`}>
      <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3 flex items-center`}>
        <ChartPieIcon className="h-4 w-4 mr-2" />
        {title}
      </h3>
      <div className="flex items-center justify-center">
        <div className="relative w-32 h-32 group">
          <svg viewBox="0 0 100 100" className="w-full h-full transform -rotate-90 transition-transform duration-300 group-hover:scale-105">
            {safeData.map((item, index) => {
              const percentage = (item.value / total) * 100;
              const angle = (percentage / 100) * 360;
              const largeArcFlag = percentage > 50 ? 1 : 0;
              
              const x1 = 50 + 50 * Math.cos(currentAngle * Math.PI / 180);
              const y1 = 50 + 50 * Math.sin(currentAngle * Math.PI / 180);
              currentAngle += angle;
              const x2 = 50 + 50 * Math.cos(currentAngle * Math.PI / 180);
              const y2 = 50 + 50 * Math.sin(currentAngle * Math.PI / 180);
              
              const pathData = [
                `M 50 50`,
                `L ${x1} ${y1}`,
                `A 50 50 0 ${largeArcFlag} 1 ${x2} ${y2}`,
                `Z`
              ].join(' ');
              
              return (
                <path
                  key={index}
                  d={pathData}
                  fill={item.color}
                  stroke={isDarkMode ? "#1f2937" : "#e5e7eb"}
                  strokeWidth="2"
                  title={`${item.label}: ${item.value}`}
                  className="transition-all duration-300 hover:opacity-80 cursor-pointer"
                />
              );
            })}
          </svg>
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="text-center">
              <span className={`${isDarkMode ? 'text-amber-50' : 'text-amber-900'} font-bold text-sm block`}>{total}</span>
              <span className="text-amber-500 text-xs">Total</span>
            </div>
          </div>
        </div>
      </div>
      <div className="mt-3">
        <div className="space-y-1 max-h-40 overflow-auto pr-2">
          {safeData.map((item, index) => (
            <div key={index} title={`${item.label}: ${item.value}`} className={`flex items-center text-xs group cursor-pointer ${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-100'} p-1 rounded transition-colors`}>
              <div 
                className="w-2 h-2 rounded-full mr-2 transition-transform group-hover:scale-125"
                style={{ backgroundColor: item.color }}
              ></div>
              <span className={`${isDarkMode ? 'text-gray-300 group-hover:text-amber-50' : 'text-gray-600 group-hover:text-amber-900'} flex-1 truncate`}>{item.label}</span>
              <span className={`${isDarkMode ? 'text-amber-50' : 'text-amber-900'} font-medium text-xs`}>
                {((item.value / total) * 100).toFixed(1)}%
              </span>
              <span className={`${isDarkMode ? 'text-gray-500' : 'text-gray-400'} text-xs ml-1`}>({item.value})</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

// Small sparkline for KPI cards — supports multiple mini chart types
const Sparkline = ({ data = [], width = 120, height = 28, stroke = '#f59e0b', type = 'line' }) => {
  const safe = data.map(d => Number(d.value) || 0);
  const max = Math.max(...safe, 1);
  if (safe.length === 0) return <div className="h-7" />;

  // generate points array normalized to svg width/height
  const pointsArr = safe.map((v, i) => {
    const x = (safe.length === 1) ? width / 2 : (i / (safe.length - 1)) * width;
    const y = height - (v / max) * height;
    return { x, y, v };
  });

  // helper to build polyline points string
  const pointsStr = pointsArr.map(p => `${p.x},${p.y}`).join(' ');

  // render different mini-chart types
  if (type === 'bars') {
    const barW = Math.max(2, Math.floor(width / Math.max(pointsArr.length * 1.6, 1)));
    return (
      <svg width={width} height={height} className="block">
        {pointsArr.map((p, i) => (
          <rect
            key={i}
            x={Math.max(0, p.x - barW / 2)}
            y={p.y}
            width={barW}
            height={Math.max(1, height - p.y)}
            rx="2"
            fill={stroke}
            opacity="0.9"
          />
        ))}
      </svg>
    );
  }

  if (type === 'area') {
    const areaPath = `${pointsStr} L ${width} ${height} L 0 ${height} Z`;
    return (
      <svg width={width} height={height} className="block">
        <path d={areaPath} fill={stroke} opacity="0.12" />
        <polyline points={pointsStr} fill="none" stroke={stroke} strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    );
  }

  if (type === 'dots') {
    return (
      <svg width={width} height={height} className="block">
        <polyline points={pointsStr} fill="none" stroke={stroke} strokeWidth="1.2" />
        {pointsArr.map((p, i) => (
          <circle key={i} cx={p.x} cy={p.y} r="2" fill={stroke} />
        ))}
      </svg>
    );
  }

  // default: simple line
  return (
    <svg width={width} height={height} className="block">
      <polyline
        points={pointsStr}
        fill="none"
        stroke={stroke}
        strokeWidth="2"
        className="transition-all duration-500 ease-out"
      />
    </svg>
  );
};

// Using shared LineChart component from components/charts/LineChart

// Logout Modal Component
const LogoutModal = ({ isOpen, onClose, onConfirm, loading }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-md transform animate-scaleIn">
        <div className="p-4">
          <h3 className="text-sm font-semibold text-amber-50 mb-3">Confirm Logout</h3>
          <p className="text-gray-300 text-sm mb-4">Are you sure you want to logout?</p>
          <div className="flex justify-end space-x-2">
            <button
              onClick={onClose}
              disabled={loading}
              className="px-3 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              disabled={loading}
              className="px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50"
            >
              {loading ? (
                <div className="flex items-center">
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  Logging out...
                </div>
              ) : (
                'Logout'
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

// Completion Confirmation Modal with 5-second countdown
const CompletionConfirmationModal = ({ isOpen, onClose, appointment, countdown, onConfirm, loading, paymentAmount, paymentType, selectedDiscounts, calculateDiscount, inKindDescription }) => {
  if (!isOpen || !appointment) return null;

  const isCountdownActive = countdown > 0 && !loading;
  
  // Calculate totals
  const rawSubtotal = (paymentAmount && !Number.isNaN(parseFloat(paymentAmount))) ? parseFloat(paymentAmount) : (Number(appointment.service?.price) || 0);
  const discountObj = calculateDiscount(rawSubtotal) || { discount: 0, discountType: '' };
  const discountVal = Number(discountObj.discount) || 0;
  const totalVal = rawSubtotal - discountVal;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-2xl w-full max-w-md transform animate-scaleIn">
        {/* Header */}
        <div className="px-6 py-4 border-b border-amber-500/20 bg-gradient-to-r from-gray-800 to-gray-900">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-bold text-amber-400">Complete Appointment</h3>
            <div className="flex items-center justify-center w-12 h-12 rounded-full bg-gray-800 border border-amber-500/30">
              <span className="text-sm font-bold text-amber-400">{loading ? '✓' : countdown}</span>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
          {/* Client Info */}
          <div>
            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Client</p>
            <p className="text-sm font-medium text-amber-50">{appointment.user?.first_name} {appointment.user?.last_name}</p>
            <p className="text-xs text-gray-400">{appointment.user?.email}</p>
          </div>

          {/* Appointment Details */}
          <div className="grid grid-cols-2 gap-4 pt-2 border-t border-gray-700">
            <div>
              <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Service</p>
              <p className="text-sm text-amber-50">{appointment.service?.name || 'N/A'}</p>
            </div>
            <div>
              <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Date</p>
              <p className="text-sm text-amber-50">{new Date(appointment.appointment_date).toLocaleDateString()}</p>
            </div>
          </div>

          <div>
            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Time</p>
            <p className="text-sm text-amber-50">{appointment.start_time} - {appointment.end_time}</p>
          </div>

          {/* Payment Summary */}
          <div className="pt-2 border-t border-gray-700">
            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Payment Details</p>
            <div className="bg-gray-800 rounded p-3 space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-400">Payment Type:</span>
                <span className="text-amber-50 font-medium capitalize">{paymentType === 'in-kind' ? 'In-kind' : paymentType}</span>
              </div>
              {paymentType !== 'in-kind' && (
                <>
                  <div className="flex justify-between">
                    <span className="text-gray-400">Amount:</span>
                    <span className="text-amber-50 font-medium">₱{rawSubtotal.toFixed(2)}</span>
                  </div>
                  {discountVal > 0 && (
                    <div className="flex justify-between text-green-400">
                      <span>Discount:</span>
                      <span className="font-medium">-₱{discountVal.toFixed(2)}</span>
                    </div>
                  )}
                  <div className="flex justify-between pt-2 border-t border-gray-700 font-semibold">
                    <span className="text-gray-300">Total:</span>
                    <span className="text-green-400">₱{totalVal.toFixed(2)}</span>
                  </div>
                </>
              )}
              {paymentType === 'in-kind' && inKindDescription && (
                <div className="text-xs text-gray-300 pt-2 border-t border-gray-700">
                  <p className="text-gray-400 mb-1">Description:</p>
                  <p className="text-amber-50">{inKindDescription}</p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-amber-500/20 bg-gray-800/50 flex gap-3">
          <button
            onClick={onClose}
            disabled={loading || isCountdownActive}
            className="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Cancel
          </button>
          <button
            onClick={onConfirm}
            disabled={loading || isCountdownActive}
            className="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            {loading ? (
              <>
                <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                Processing
              </>
            ) : isCountdownActive ? (
              `Wait... ${countdown}s`
            ) : (
              '✓ Confirm'
            )}
          </button>
        </div>
      </div>
    </div>
  );
};

// Action Log Modal Component - Display full action details
const ActionLogModal = ({ isOpen, onClose, logData, isDarkMode = true }) => {
  if (!isOpen || !logData) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`w-full max-w-md ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow-2xl overflow-hidden`}>
        {/* Header */}
        <div className={`p-4 border-b ${isDarkMode ? 'border-amber-500/20 bg-gradient-to-r from-gray-800 to-gray-900' : 'border-amber-300/40 bg-gradient-to-r from-gray-50 to-gray-100'} flex items-center justify-between`}>
          <div>
            <div className="text-sm font-semibold text-amber-500">📋 ACTION DETAILS</div>
            <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mt-0.5`}>ID #{logData.id}</div>
          </div>
          <button onClick={onClose} className={`${isDarkMode ? 'text-gray-400 hover:text-amber-400 hover:bg-gray-800' : 'text-gray-500 hover:text-amber-600 hover:bg-gray-100'} p-2 rounded transition-colors`}>
            <XMarkIcon className="h-5 w-5" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
          {/* Date & Time */}
          <div>
            <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-1`}>Date & Time</p>
            <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>{new Date(logData.created_at).toLocaleString()}</p>
          </div>

          {/* User */}
          <div>
            <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-1`}>User</p>
            <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} font-medium`}>
              {logData.user ? `${logData.user.first_name} ${logData.user.last_name}` : 'Unknown'}
            </p>
            {logData.user && (
              <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mt-1`}>Role: {logData.user.role}</p>
            )}
          </div>

          {/* Action */}
          <div>
            <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-1`}>Action</p>
            <span className="inline-block px-2 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-500">
              {logData.action}
            </span>
          </div>

          {/* Description */}
          <div>
            <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-1`}>Description</p>
            <p className={`text-sm ${isDarkMode ? 'text-gray-300 bg-gray-800/50' : 'text-gray-600 bg-gray-100'} rounded p-3`}>
              {logData.description}
            </p>
          </div>

          {/* Model Info */}
          {(logData.model_type || logData.model_id) && (
            <div className={`pt-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
              <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-2`}>Related To</p>
              <div className="grid grid-cols-2 gap-3">
                {logData.model_type && (
                  <div>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Type</p>
                    <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>{logData.model_type}</p>
                  </div>
                )}
                {logData.model_id && (
                  <div>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>ID</p>
                    <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>#{logData.model_id}</p>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className={`p-4 border-t ${isDarkMode ? 'border-amber-500/20 bg-gray-800/50' : 'border-amber-300/40 bg-gray-50'} flex gap-2`}>
          <button
            onClick={onClose}
            className={`flex-1 px-4 py-2 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded text-sm font-medium transition-colors`}
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

// Receipt Modal Component - Professional design matching admin theme
const ReceiptModal = ({ isOpen, onClose, receiptData, isDarkMode = true }) => {
  const [emailing, setEmailing] = useState(false);

  const handlePrint = () => {
    window.print();
  };

  const handleSavePdf = () => {
    // Open a new window with receipt content and trigger print (user can Save as PDF)
    const content = document.getElementById('receipt-content');
    if (!content) return;
    const win = window.open('', '_blank', 'width=800,height=900');
    if (!win) return;
    win.document.write('<html><head><title>Receipt</title>');
    win.document.write('<style>body{font-family:system-ui,-apple-system,sans-serif;padding:20px;color:#111;background:#fff} .title{font-weight:700;} .receipt-container{max-width:600px;margin:0 auto;}</style>');
    win.document.write('</head><body>');
    win.document.write(content.innerHTML);
    win.document.write('</body></html>');
    win.document.close();
    setTimeout(() => { win.print(); }, 500);
  };

  const handleEmail = async () => {
    if (!receiptData?.id) return;
    setEmailing(true);
    try {
      const response = await axios.post(`/api/cashier/appointments/${receiptData.id}/email-receipt`);
      if (response.data?.success) {
        if (window?.showToast) window.showToast('Receipt', 'Receipt emailed to client', 'success');
      } else {
        throw new Error(response.data?.message || 'Unknown error');
      }
    } catch (err) {
      console.error('Email receipt error', err);
      const errorMsg = err.response?.data?.message || err.message || 'Failed to email receipt';
      if (window?.showToast) window.showToast('Receipt', errorMsg, 'error');
    } finally {
      setEmailing(false);
    }
  };

  // Early return AFTER all hooks
  if (!isOpen || !receiptData) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`w-full max-w-md ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow-2xl overflow-hidden`}>
        <style>{`
          @media print {
            body * { visibility: hidden; }
            #receipt-content, #receipt-content * { visibility: visible; }
            #receipt-content { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
          }
        `}</style>

        {/* Header */}
        <div className={`p-4 border-b ${isDarkMode ? 'border-amber-500/20 bg-gradient-to-r from-gray-800 to-gray-900' : 'border-amber-300/40 bg-gradient-to-r from-gray-50 to-gray-100'} flex items-center justify-between no-print`}>
          <div>
            <div className="text-sm font-semibold text-amber-500">📋 RECEIPT</div>
            <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mt-0.5`}>Reference #{receiptData.id}</div>
          </div>
          <button onClick={onClose} className={`${isDarkMode ? 'text-gray-400 hover:text-amber-400 hover:bg-gray-800' : 'text-gray-500 hover:text-amber-600 hover:bg-gray-100'} p-2 rounded transition-colors`}>
            <XMarkIcon className="h-5 w-5" />
          </button>
        </div>

        {/* Receipt Content */}
        <div id="receipt-content" className="p-6 text-gray-900 bg-white">
          {/* Header */}
          <div className="text-center mb-6 pb-4 border-b-2 border-gray-200">
            <h2 className="text-2xl font-bold text-gray-900">DE GUZMAN LAW OFFICE</h2>
            <div className="text-sm text-gray-600 mt-1">Legal Ease</div>
            <div className="text-xs text-gray-500 mt-2">peejaydeguzmanlegal@gmail.com • 09765075274</div>
          </div>

          {/* Receipt Header Info */}
          <div className="mb-6 grid grid-cols-3 gap-4 text-xs">
            <div>
              <p className="text-gray-500 font-semibold">Receipt No</p>
              <p className="text-gray-900 font-mono text-sm">#{receiptData.id}</p>
            </div>
            <div>
              <p className="text-gray-500 font-semibold">Date</p>
              <p className="text-gray-900 text-sm">{new Date(receiptData.date).toLocaleDateString()}</p>
            </div>
            <div>
              <p className="text-gray-500 font-semibold">Time</p>
              <p className="text-gray-900 text-sm">{new Date(receiptData.date).toLocaleTimeString()}</p>
            </div>
          </div>

          {/* Client Information */}
          <div className="mb-5 pb-4 border-b border-gray-300">
            <p className="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Client Information</p>
            <p className="text-sm font-semibold text-gray-900">{receiptData.clientName}</p>
            <p className="text-xs text-gray-600">{receiptData.clientEmail}</p>
          </div>

          {/* Service Information */}
          <div className="mb-5 pb-4 border-b border-gray-300">
            <p className="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Service Details</p>
            <div className="flex justify-between text-sm mb-2">
              <span className="text-gray-700">Service:</span>
              <span className="font-semibold text-gray-900">{receiptData.service}</span>
            </div>
            <div className="flex justify-between text-sm mb-2">
              <span className="text-gray-700">Appointment Date:</span>
              <span className="text-gray-900">{new Date(receiptData.appointmentDate).toLocaleDateString()}</span>
            </div>
            {receiptData.cashierName && receiptData.cashierName !== 'N/A' && (
              <div className="flex justify-between text-sm text-gray-700">
                <span className="text-gray-700">Processed By:</span>
                <span className="text-gray-900">{receiptData.cashierName}</span>
              </div>
            )}
          </div>

          {/* Payment Summary */}
          <div className="mb-4 bg-gray-50 rounded p-4">
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-600">Subtotal:</span>
                <span className="text-gray-900 font-medium">₱{(Number(receiptData.subtotal) || 0).toFixed(2)}</span>
              </div>
              {receiptData.discount > 0 && (
                <div className="flex justify-between text-green-700">
                  <span>Discount ({receiptData.discountType}):</span>
                  <span className="font-medium">-₱{(Number(receiptData.discount) || 0).toFixed(2)}</span>
                </div>
              )}
              <div className="border-t-2 border-gray-300 pt-2 flex justify-between font-bold text-base">
                <span className="text-gray-900">Total Paid:</span>
                <span className="text-green-700">₱{(Number(receiptData.totalPaid) || 0).toFixed(2)}</span>
              </div>
            </div>
          </div>

          {/* Footer */}
          <div className="text-center pt-4 border-t border-gray-200">
            <p className="text-xs text-gray-500">Thank you for your business</p>
            <p className="text-xs text-gray-400 mt-1">Please keep this receipt for your records</p>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="p-4 border-t border-amber-500/20 flex gap-2 no-print bg-gray-800">
          <button 
            onClick={handlePrint} 
            className="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-100 rounded text-sm flex items-center justify-center gap-2 transition-colors duration-200"
          >
            <PrinterIcon className="h-4 w-4" />
            Print
          </button>
          <button 
            onClick={handleSavePdf} 
            className="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-100 rounded text-sm flex items-center justify-center gap-2 transition-colors duration-200"
          >
            <DocumentArrowDownIcon className="h-4 w-4" />
            Save PDF
          </button>
          <button 
            onClick={handleEmail} 
            disabled={emailing} 
            className="flex-1 px-3 py-2 bg-amber-600 hover:bg-amber-700 disabled:bg-amber-700/50 text-white rounded text-sm flex items-center justify-center gap-2 transition-colors duration-200 font-medium"
          >
            {emailing ? (
              <>
                <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                Sending...
              </>
            ) : (
              'Email Receipt'
            )}
          </button>
        </div>
      </div>
    </div>
  );
};

// Build a receipt object from an appointment (used for completed appointments)
const buildReceiptFromAppointment = (appointment) => {
  if (!appointment) return null;
  const paymentAmount = Number(appointment.payment_amount) || 0;
  const discount = Number(appointment.discount_amount) || 0;
  const subtotal = paymentAmount + discount;
  return {
    id: appointment.id,
    date: appointment.payment_date || appointment.updated_at || new Date().toISOString(),
    clientName: `${appointment.user?.first_name || ''} ${appointment.user?.last_name || ''}`.trim(),
    clientEmail: appointment.user?.email || '',
    service: appointment.service?.name || appointment.service_type || 'N/A',
    appointmentDate: appointment.appointment_date || appointment.payment_date || new Date().toISOString(),
    subtotal: subtotal,
    discount: discount,
    discountType: appointment.discount_type || '',
    totalPaid: paymentAmount
  };
};

// Appointment details modal used by cashier when viewing an appointment
const AppointmentModal = ({ isOpen, onClose, appointment, isViewOnly = false,
  paymentAmount, setPaymentAmount, paymentType, setPaymentType,
  inKindDescription, setInKindDescription, selectedDiscounts, setSelectedDiscounts,
  calculateDiscount, onComplete
}) => {
  if (!isOpen || !appointment) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 flex items-start justify-center z-50 p-6">
      <div className="bg-gray-900 border border-gray-800 rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-4 border-b border-gray-800">
          <div>
            <h3 className="text-xl font-semibold text-amber-50">Appointment Details</h3>
            <p className="text-xs text-gray-300 mt-0.5">Reference #{appointment.id} • {appointment.service?.name || 'Service'}</p>
          </div>
          <div>
            <button onClick={onClose} className="text-gray-400 hover:text-amber-300 p-2 rounded focus:outline-none">
              <XMarkIcon className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="p-4 space-y-4">
          <div className="bg-gray-800 border border-gray-700 rounded-lg p-3">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-xs text-gray-400">Client</p>
                <p className="text-lg font-medium text-amber-50">{appointment.user?.first_name} {appointment.user?.last_name}</p>
                <p className="text-sm text-gray-300">{appointment.user?.email}</p>
                <p className="text-sm text-gray-300">{appointment.user?.phone || 'N/A'}</p>
              </div>
              <div className="text-right">
                <p className="text-xs text-gray-400">Date</p>
                <p className="text-sm text-amber-50">{new Date(appointment.appointment_date).toLocaleDateString()}</p>
                <p className="text-xs text-gray-400 mt-2">Time</p>
                <p className="text-sm text-amber-50">{appointment.start_time || '-'} {appointment.end_time ? `- ${appointment.end_time}` : ''}</p>
              </div>
            </div>
          </div>

          {!isViewOnly && (
            <>
              <div className="bg-gray-800 border border-gray-700 rounded-lg p-3">
                <h4 className="text-sm font-semibold text-amber-400 mb-2">Payment</h4>
                <div className="flex gap-4 mb-3">
                  <label className="flex items-center gap-2 text-sm text-gray-200">
                    <input type="radio" name={`paytype-modal-${appointment.id}`} value="cash" checked={paymentType === 'cash'} onChange={() => setPaymentType('cash')} className="mr-1" />
                    <span>Cash</span>
                  </label>
                  <label className="flex items-center gap-2 text-sm text-gray-200">
                    <input type="radio" name={`paytype-modal-${appointment.id}`} value="partial" checked={paymentType === 'partial'} onChange={() => setPaymentType('partial')} className="mr-1" />
                    <span>Partial</span>
                  </label>
                  <label className="flex items-center gap-2 text-sm text-gray-200">
                    <input type="radio" name={`paytype-modal-${appointment.id}`} value="in-kind" checked={paymentType === 'in-kind'} onChange={() => setPaymentType('in-kind')} className="mr-1" />
                    <span>In-kind</span>
                  </label>
                </div>

                {paymentType !== 'in-kind' ? (
                  <input type="number" step="0.01" value={paymentAmount} onChange={(e) => setPaymentAmount(e.target.value)} placeholder="Enter payment amount" className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded text-sm text-white" />
                ) : (
                  <textarea value={inKindDescription} onChange={(e) => setInKindDescription(e.target.value)} rows={3} placeholder="Describe items received (e.g. 2kg rice)" className="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded text-sm text-white"></textarea>
                )}

                <div className="mt-3 grid grid-cols-3 gap-2">
                  <label className="flex items-center gap-2 text-sm text-gray-200 px-2 py-2 bg-gray-800 border border-gray-700 rounded">
                    <input type="checkbox" checked={selectedDiscounts.includes('pwd')} onChange={(e) => setSelectedDiscounts(e.target.checked ? ['pwd'] : [])} className="mr-1" />
                    <div className="leading-tight"><div className="font-medium">PWD</div><div className="text-xs text-gray-400">20%</div></div>
                  </label>
                  <label className="flex items-center gap-2 text-sm text-gray-200 px-2 py-2 bg-gray-800 border border-gray-700 rounded">
                    <input type="checkbox" checked={selectedDiscounts.includes('senior')} onChange={(e) => setSelectedDiscounts(e.target.checked ? ['senior'] : [])} className="mr-1" />
                    <div className="leading-tight"><div className="font-medium">Senior</div><div className="text-xs text-gray-400">20%</div></div>
                  </label>
                  <label className="flex items-center gap-2 text-sm text-gray-200 px-2 py-2 bg-gray-800 border border-gray-700 rounded">
                    <input type="checkbox" checked={selectedDiscounts.includes('student')} onChange={(e) => setSelectedDiscounts(e.target.checked ? ['student'] : [])} className="mr-1" />
                    <div className="leading-tight"><div className="font-medium">Student</div><div className="text-xs text-gray-400">10%</div></div>
                  </label>
                </div>
              </div>

              <div className="bg-gray-800 border border-gray-700 rounded-lg p-3">
                <div className="flex items-center justify-between mb-2">
                  <div>
                    <p className="text-xs text-gray-400">Service</p>
                    <p className="text-sm text-amber-50">{appointment.service?.name || ''}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-xs text-gray-400">Price</p>
                    <p className="text-sm text-amber-50">₱{(Number(appointment.service?.price) || 0).toFixed(2)}</p>
                  </div>
                </div>

                <div className="border-t border-gray-700 pt-3">
                  {(() => {
                    const rawSubtotal = (paymentAmount && !Number.isNaN(parseFloat(paymentAmount))) ? parseFloat(paymentAmount) : (Number(appointment.service?.price) || 0);
                    const discountObj = calculateDiscount(rawSubtotal) || { discount: 0 };
                    const discountVal = Number(discountObj.discount) || 0;
                    const totalVal = rawSubtotal - discountVal;
                    return (
                      <>
                        <div className="flex justify-between text-gray-300 text-sm"><span>Subtotal</span><span className="font-medium text-amber-50">₱{rawSubtotal.toFixed(2)}</span></div>
                        {selectedDiscounts.length > 0 && <div className="flex justify-between text-green-400 text-sm"><span>Discount</span><span>-₱{discountVal.toFixed(2)}</span></div>}
                        <div className="flex justify-between font-semibold text-base border-t border-gray-700 pt-2"><span>Total</span><span className="text-amber-400">₱{totalVal.toFixed(2)}</span></div>
                      </>
                    );
                  })()}
                </div>

                <div className="mt-3 flex gap-2">
                  <button onClick={onComplete} className="flex-1 px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm font-medium">Complete & Receipt</button>
                  <button onClick={onClose} className="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded text-sm">Close</button>
                </div>
              </div>
            </>
          )}

          {isViewOnly && (
            <div className="bg-gray-800 border border-gray-700 rounded-lg p-3">
              <div className="flex items-center justify-between mb-2">
                <div>
                  <p className="text-xs text-gray-400">Service</p>
                  <p className="text-sm text-amber-50">{appointment.service?.name || ''}</p>
                </div>
                <div className="text-right">
                  <p className="text-xs text-gray-400">Price</p>
                  <p className="text-sm text-amber-50">₱{(Number(appointment.service?.price) || 0).toFixed(2)}</p>
                </div>
              </div>
              <div className="border-t border-gray-700 pt-3 mb-3">
                <p className="text-xs text-gray-400 mb-2">Status</p>
                <p className="text-sm text-green-400 font-semibold">✓ Completed</p>
              </div>
              <button onClick={onClose} className="w-full px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded text-sm">Close</button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

// Confirm payment modal shown before processing payment
const ConfirmPaymentModal = ({ isOpen, onClose, appointment, onConfirm, paymentAmount, paymentType, selectedDiscounts, calculateDiscount, inKindDescription, isDarkMode = true }) => {
  if (!isOpen || !appointment) return null;

  const rawSubtotal = (paymentAmount && !Number.isNaN(parseFloat(paymentAmount))) ? parseFloat(paymentAmount) : (Number(appointment.service?.price) || 0);
  const discountObj = calculateDiscount(rawSubtotal) || { discount: 0, discountType: '' };
  const discountVal = Number(discountObj.discount) || 0;
  const totalVal = rawSubtotal - discountVal;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow-xl w-full max-w-xl p-5`}>
        <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-2`}>Confirm Payment</h3>
        <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} mb-4`}>Please review the payment details before confirming.</p>

        <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border rounded p-4 text-sm space-y-2`}>
          <div className="flex justify-between"><span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Client</span><span className={isDarkMode ? 'text-amber-50' : 'text-amber-900'}>{appointment.user?.first_name} {appointment.user?.last_name}</span></div>
          <div className="flex justify-between"><span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Service</span><span className={isDarkMode ? 'text-amber-50' : 'text-amber-900'}>{appointment.service?.name || 'N/A'}</span></div>
          <div className="flex justify-between"><span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Payment Type</span><span className={isDarkMode ? 'text-amber-50' : 'text-amber-900'}>{paymentType}</span></div>
          {paymentType !== 'in-kind' && <div className="flex justify-between"><span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Amount</span><span className={isDarkMode ? 'text-amber-50' : 'text-amber-900'}>₱{rawSubtotal.toFixed(2)}</span></div>}
          {paymentType === 'in-kind' && <div><span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>In-kind Description</span><p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>{inKindDescription || '—'}</p></div>}
          {selectedDiscounts.length > 0 && <div className="flex justify-between text-green-500"><span>Discount</span><span>-₱{discountVal.toFixed(2)} ({discountObj.discountType || selectedDiscounts[0]})</span></div>}
          <div className="flex justify-between font-bold"><span className={isDarkMode ? 'text-gray-300' : 'text-gray-700'}>Total</span><span className="text-amber-500">₱{totalVal.toFixed(2)}</span></div>
        </div>

        <div className="mt-4 flex gap-2">
          <button onClick={() => { onConfirm(appointment); onClose(); }} className="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">Confirm Payment</button>
          <button onClick={onClose} className={`px-4 py-2 ${isDarkMode ? 'bg-gray-800 hover:bg-gray-700' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} text-white rounded`}>Cancel</button>
        </div>
      </div>
    </div>
  );
};

// Generic confirmation modal for exports/refunds
const ConfirmModal = ({ isOpen, title = 'Confirm', message = '', onCancel, onConfirm, loading = false, isDarkMode = true }) => {
  if (!isOpen) return null;
  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow-xl w-full max-w-md p-5`}>
        <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-2`}>{title}</h3>
        <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} mb-4`}>{message}</p>
        <div className="flex gap-2 justify-end">
          <button onClick={onCancel} className={`px-3 py-2 ${isDarkMode ? 'bg-gray-800 text-white' : 'bg-gray-200 text-gray-800'} rounded`}>Cancel</button>
          <button onClick={onConfirm} disabled={loading} className="px-3 py-2 bg-amber-600 text-white rounded">{loading ? 'Working...' : 'Confirm'}</button>
        </div>
      </div>
    </div>
  );
};

// Main Cashier Dashboard Component
const CashierDashboard = () => {
  const { user, logout, setUser } = useAuth();
  const { callApi, loading: apiLoading } = useApi();
  
  const [activeSection, setActiveSection] = useState('dashboard');
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  const [isCollapsedDesktop, setIsCollapsedDesktop] = useState(false);
  const [openDropdowns, setOpenDropdowns] = useState({});
  const [showLogoutModal, setShowLogoutModal] = useState(false);
  const [showReceiptModal, setShowReceiptModal] = useState(false);
  const [currentReceipt, setCurrentReceipt] = useState(null);
  
  // Dashboard Data
  const [stats, setStats] = useState({
    totalRevenue: 0,
    totalSales: 0,
    todayRevenue: 0,
    todaySales: 0
  });
  const [revenueData, setRevenueData] = useState([]);
  const [salesByService, setSalesByService] = useState([]);
  const [showAllServices, setShowAllServices] = useState(false);
  const [timeframe, setTimeframe] = useState('monthly');
  
  // Appointments Data
  const [appointmentsTab, setAppointmentsTab] = useState('approved');
  const [appointments, setAppointments] = useState([]);
  const [appointmentsLoading, setAppointmentsLoading] = useState(false);
  const [expandedAppointment, setExpandedAppointment] = useState(null);
  const [paymentAmount, setPaymentAmount] = useState('');
  const [paymentType, setPaymentType] = useState('cash');
  const [inKindDescription, setInKindDescription] = useState('');
  const [selectedDiscounts, setSelectedDiscounts] = useState([]);
  const [viewModalAppointment, setViewModalAppointment] = useState(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(5);
  const [isDense, setIsDense] = useState(false);
  const [totalPages, setTotalPages] = useState(1);
  const [totalAppointments, setTotalAppointments] = useState(0);
  const [showConfirmPaymentModal, setShowConfirmPaymentModal] = useState(false);
  const [confirmAppointment, setConfirmAppointment] = useState(null);
  const [showCompletionConfirmation, setShowCompletionConfirmation] = useState(false);
  const [completionCountdown, setCompletionCountdown] = useState(5);
  const [isCompletionLoading, setIsCompletionLoading] = useState(false);
  
  // Calendar Data
  const [currentMonth, setCurrentMonth] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState(null);
  const [calendarAppointments, setCalendarAppointments] = useState([]);
  const [calendarFilters, setCalendarFilters] = useState({});
  const [calendarLoading, setCalendarLoading] = useState(false);
  const [monthSummary, setMonthSummary] = useState(null);
  
  // Action Logs Data
  const [logsTab, setLogsTab] = useState('cashier');
  const [actionLogs, setActionLogs] = useState([]);
  const [selectedActionLog, setSelectedActionLog] = useState(null);
  const [showActionLogModal, setShowActionLogModal] = useState(false);
  const [logsPage, setLogsPage] = useState(1);
  const [logsPerPage, setLogsPerPage] = useState(10);
  const [totalLogs, setTotalLogs] = useState(0);
  const [totalLogPages, setTotalLogPages] = useState(1);
  
  const [loading, setLoading] = useState(false);

  // Transactions (Sales History) Data
  const [transactions, setTransactions] = useState([]);
  const [txLoading, setTxLoading] = useState(false);
  const [txSearch, setTxSearch] = useState('');
  const [txFilters, setTxFilters] = useState({ from: '', to: '', service: '', cashier: '', status: '' });
  const [txPage, setTxPage] = useState(1);
  const [txPerPage, setTxPerPage] = useState(10);
  const [txTotalPages, setTxTotalPages] = useState(1);
  const [txTotal, setTxTotal] = useState(0);
  const [selectedTransaction, setSelectedTransaction] = useState(null);
  const [txActionLoading, setTxActionLoading] = useState({});

  // Profile Editing State
  const [isEditingProfile, setIsEditingProfile] = useState(false);
  const [profileFormData, setProfileFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: ''
  });
  const [profileSaving, setProfileSaving] = useState(false);

  // Shift reports / accounting export
  const [shiftReportSummary, setShiftReportSummary] = useState(null);
  const [shiftLoading, setShiftLoading] = useState(false);
  const [shiftRange, setShiftRange] = useState({ from: '', to: '' });
  const [showConfirmModal, setShowConfirmModal] = useState(false);
  const [confirmModalProps, setConfirmModalProps] = useState({ title: '', message: '', onConfirm: null, loading: false });
  const [isDarkMode, setIsDarkMode] = useState(true);

  // Safe toggle for expanding appointment entries (prevents unexpected crashes)
  const toggleExpanded = useCallback((id) => {
    try {
      setExpandedAppointment(prev => (prev === id ? null : id));
    } catch (err) {
      console.error('Error toggling expanded appointment:', err);
      if (window?.sowTohast) window.showToast('Error', 'An unexpected error occurred while opening the appointment. Check console for details.', 'error');
    }
  }, []);

  // Normalize monthly revenue trend to always include last 12 months
  const normalizeMonthlyTrend = (trend = []) => {
    const now = new Date();
    const months = [];
    for (let i = 11; i >= 0; i--) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      const keyYYYYMM = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
      const display = d.toLocaleString('en-US', { month: 'short' });
      months.push({ date: d, key: keyYYYYMM, label: display, value: 0 });
    }

    // attempt to map incoming trend entries to these months
    const trendMap = {};
    (trend || []).forEach(item => {
      const lbl = (item.label || '').toString();
      const val = Number(item.value) || 0;

      // direct YYYY-MM match
      const yyyyMmMatch = lbl.match(/(\d{4})-(\d{1,2})/);
      if (yyyyMmMatch) {
        const ky = `${yyyyMmMatch[1]}-${String(Number(yyyyMmMatch[2])).padStart(2,'0')}`;
        trendMap[ky] = (trendMap[ky] || 0) + val;
        return;
      }

      // month name with optional year
      const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const found = monthNames.find((m, idx) => {
        const re = new RegExp(m, 'i');
        return re.test(lbl);
      });
      if (found) {
        // try to extract year from label
        const yearMatch = lbl.match(/(20\d{2}|19\d{2})/);
        let year = yearMatch ? Number(yearMatch[0]) : now.getFullYear();
        const monthIdx = monthNames.indexOf(found);
        const ky = `${year}-${String(monthIdx + 1).padStart(2,'0')}`;
        trendMap[ky] = (trendMap[ky] || 0) + val;
        return;
      }

      // fallback: if label is numeric month index
      const num = Number(lbl);
      if (!Number.isNaN(num) && num >=1 && num <=12) {
        const ky = `${now.getFullYear()}-${String(num).padStart(2,'0')}`;
        trendMap[ky] = (trendMap[ky] || 0) + val;
      }
    });

    // map to months array
    return months.map(m => ({ label: m.label, value: trendMap[m.key] || 0 }));
  };

  // Navigation items with sections (similar to admin)
  const navigation = [
    { 
      name: 'Dashboard', 
      icon: HomeIcon, 
      key: 'dashboard' 
    },
    { 
      section: 'Operations',
      items: [
        { 
          name: 'Appointments', 
          icon: CalendarIcon, 
          key: 'appointments',
          badge: appointmentsLoading ? null : appointments.length
        },
        { 
          name: 'Calendar', 
          icon: CalendarIcon, 
          key: 'calendar'
        },
        { 
          name: 'Transactions', 
          icon: DocumentTextIcon, 
          key: 'transactions',
          badge: txTotal
        }
      ]
    },
    { 
      section: 'Reports & Analytics',
      items: [
        { 
          name: 'Reports', 
          icon: ChartBarIcon, 
          key: 'reports'
        },
        { 
          name: 'Action Logs', 
          icon: ClockIcon, 
          key: 'action-logs'
        }
      ]
    },
    { 
      name: 'Profile', 
      icon: UserCircleIcon, 
      key: 'profile' 
    }
  ];

  // Load dashboard data
  const loadDashboardData = useCallback(async () => {
    setLoading(true);
    try {
      const response = await callApi((signal) =>
        axios.get(`/api/cashier/dashboard-stats?timeframe=${timeframe}`, { signal })
      );
      if (response && response.success) {
        const payload = response.data || {};

        setStats({
          totalRevenue: payload.stats?.totalRevenue || 0,
          totalSales: payload.stats?.totalSales || 0,
          todayRevenue: payload.stats?.todayRevenue || 0,
          todaySales: payload.stats?.todaySales || 0
        });

        // Set revenue data from backend; normalize if monthly timeframe
        const rawTrend = payload.revenueTrend || [];
        const normalized = timeframe === 'monthly' ? normalizeMonthlyTrend(rawTrend) : rawTrend;
        setRevenueData(normalized);

        // Set sales by service from backend
        setSalesByService(payload.salesByService || []);
      }
      } catch (error) {
        console.error('Error loading dashboard data:', error);
      } finally {
        setLoading(false);
      }
    }, [callApi, timeframe]);

  // Export revenue CSV
  const exportRevenueCSV = useCallback(() => {
    if (!revenueData || revenueData.length === 0) return;
    const rows = [['label', 'value']];
    revenueData.forEach(r => rows.push([r.label, r.value]));
    const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `revenue-${timeframe}-${Date.now()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }, [revenueData, timeframe]);

  // Load appointments
  const loadAppointments = useCallback(async () => {
    setAppointmentsLoading(true);
    try {
      const url = appointmentsTab === 'completed' ? '/api/cashier/appointments/completed' : '/api/cashier/appointments/approved';
      const params = { page: currentPage, per_page: perPage };
      const response = await callApi((signal) =>
        axios.get(url, { signal, params })
      );

      if (response && response.success) {
        const payload = response.data || {};

        // Backend may return several shapes:
        // 1) { data: [...] } (array)
        // 2) { data: { data: [...], total, last_page } } (paginator envelope)
        // 3) { appointments: [...] }
        // 4) direct array

        let list = [];
        if (Array.isArray(payload)) {
          list = payload;
        } else if (Array.isArray(payload.data)) {
          // shape: { data: [...] }
          list = payload.data;
        } else if (payload.data && Array.isArray(payload.data.data)) {
          // shape: { data: { data: [...], total, last_page } }
          list = payload.data.data;
        } else if (Array.isArray(payload.appointments)) {
          list = payload.appointments;
        }

        setAppointments(list || []);

        // totals / pagination values may live at different places
        const total = payload.total || (payload.data && payload.data.total) || (payload.data && payload.data.total) || (Array.isArray(list) ? list.length : 0) || 0;
        const lastPage = payload.last_page || (payload.data && payload.data.last_page) || Math.max(1, Math.ceil((total || 0) / perPage));
        setTotalAppointments(total || 0);
        setTotalPages(lastPage || 1);
      }
    } catch (error) {
      console.error('Error loading appointments:', error);
    } finally {
      setAppointmentsLoading(false);
    }
  }, [callApi, appointmentsTab, currentPage, perPage]);

  // Load calendar appointments (grouped by date) from backend for the current month/year
  const loadCalendarAppointments = useCallback(async (month = null, year = null) => {
    try {
      setCalendarLoading(true);
      const m = month || (currentMonth.getMonth() + 1);
      const y = year || currentMonth.getFullYear();
      const params = { month: m, year: y };
      const response = await callApi((signal) => axios.get('/api/cashier/calendar/appointments', { signal, params }));
      if (response && response.success) {
        const payload = response.data || {};
        // Backend returns appointments as a flat array or grouped by date
        const incomingAppts = payload.appointments || payload.data || [];
        
        // If it's an object (grouped by date), convert to flat array
        let flatAppts = [];
        if (Array.isArray(incomingAppts)) {
          flatAppts = incomingAppts;
        } else if (typeof incomingAppts === 'object') {
          Object.values(incomingAppts).forEach(dateAppts => {
            if (Array.isArray(dateAppts)) {
              flatAppts.push(...dateAppts);
            }
          });
        }
        
        setCalendarAppointments(flatAppts);
        
        // Calculate monthly summary
        const summary = {
          totalApproved: flatAppts.filter(a => a.status === 'approved').length,
          totalCompleted: flatAppts.filter(a => a.payment_status === 'paid' || a.status === 'completed').length,
          expectedRevenue: flatAppts
            .filter(a => a.status === 'approved')
            .reduce((sum, a) => sum + (Number(a.service?.price) || 0), 0),
          actualRevenue: flatAppts
            .filter(a => a.payment_status === 'paid')
            .reduce((sum, a) => sum + (Number(a.amount_paid) || Number(a.service?.price) || 0), 0),
        };
        setMonthSummary(summary);
      }
    } catch (err) {
      console.error('Error loading calendar appointments:', err);
    } finally {
      setCalendarLoading(false);
    }
  }, [callApi, currentMonth]);

  // Reload appointments when appointments tab changes while viewing appointments
  useEffect(() => {
    if (activeSection === 'appointments' || activeSection === 'calendar') {
      loadAppointments();
    }
  }, [appointmentsTab, activeSection, loadAppointments]);

  // Load action logs
  const loadActionLogs = useCallback(async () => {
    setLoading(true);
    try {
      // Ensure we pass the correct type parameter with pagination
      const url = `/api/cashier/action-logs?type=${logsTab}&page=${logsPage}&per_page=${logsPerPage}`;
      
      const response = await callApi((signal) =>
        axios.get(url, { signal }),
        { skipCache: true } // Skip cache to ensure fresh data for each tab
      );
      
      // Backend returns Laravel paginated response directly
      if (response && response.data) {
        const payload = response.data;
        // Extract data from paginated response
        const logs = Array.isArray(payload.data) ? payload.data : [];
        setActionLogs(logs);
        
        // Set pagination info
        setTotalLogs(payload.total || 0);
        setTotalLogPages(payload.last_page || 1);
      }
    } catch (error) {
      console.error('Error loading action logs:', error);
      if (window?.showToast) window.showToast('Action Logs', 'Failed to load logs', 'error');
    } finally {
      setLoading(false);
    }
  }, [callApi, logsTab, logsPage, logsPerPage]);

  // Load section data - optimized with parallel loading where applicable
  useEffect(() => {
    if (activeSection === 'dashboard') {
      loadDashboardData();
    } else if (activeSection === 'appointments') {
      loadAppointments();
    } else if (activeSection === 'calendar') {
      // Load current and adjacent months in parallel for smooth navigation
      const year = currentMonth.getFullYear();
      const month = currentMonth.getMonth();
      const prevMonth = new Date(year, month - 1, 1);
      const nextMonth = new Date(year, month + 1, 1);
      
      // Load all three months in parallel
      Promise.all([
        loadCalendarAppointments(month + 1, year),
        loadCalendarAppointments(prevMonth.getMonth() + 1, prevMonth.getFullYear()),
        loadCalendarAppointments(nextMonth.getMonth() + 1, nextMonth.getFullYear())
      ]);
    } else if (activeSection === 'action-logs') {
      loadActionLogs();
    }
  }, [activeSection, currentMonth, logsTab, loadActionLogs, loadAppointments, loadCalendarAppointments, loadDashboardData]);

  // Reset pagination when logs tab changes
  useEffect(() => {
    setLogsPage(1);
  }, [logsTab]);

  // Load calendar appointments when month changes - preload adjacent months for smooth navigation
  useEffect(() => {
    if (activeSection === 'calendar') {
      const year = currentMonth.getFullYear();
      const month = currentMonth.getMonth();
      
      // Load current month
      loadCalendarAppointments(month + 1, year);
      
      // Preload previous and next months for smooth navigation
      const prevMonth = new Date(year, month - 1, 1);
      const nextMonth = new Date(year, month + 1, 1);
      loadCalendarAppointments(prevMonth.getMonth() + 1, prevMonth.getFullYear());
      loadCalendarAppointments(nextMonth.getMonth() + 1, nextMonth.getFullYear());
    }
  }, [activeSection, currentMonth, loadCalendarAppointments]);

  // Reset pagination when appointments tab changes
  useEffect(() => {
    setCurrentPage(1);
  }, [appointmentsTab]);

  // Load appointments when page or perPage changes
  useEffect(() => {
    if (activeSection === 'appointments') {
      loadAppointments();
    }
  }, [activeSection, currentPage, perPage, loadAppointments]);

  // Load action logs when page or perPage changes
  useEffect(() => {
    if (activeSection === 'action-logs') {
      loadActionLogs();
    }
  }, [activeSection, logsPage, logsPerPage, loadActionLogs]);

  // Completion confirmation countdown timer
  useEffect(() => {
    if (!showCompletionConfirmation || isCompletionLoading || completionCountdown <= 0) return;
    const timer = setInterval(() => {
      setCompletionCountdown(prev => Math.max(0, prev - 1));
    }, 1000);
    return () => clearInterval(timer);
  }, [showCompletionConfirmation, isCompletionLoading, completionCountdown]);

  // Polling fallback for cashier dashboard data (appointments + stats)
  useEffect(() => {
    const POLL_INTERVAL_MS = 15000; // 15s
    const id = setInterval(() => {
      if (activeSection === 'dashboard') {
        loadDashboardData();
      }
      if (activeSection === 'appointments') {
        loadAppointments();
      }
      if (activeSection === 'calendar') {
        // Refresh current month's calendar data
        loadCalendarAppointments(currentMonth.getMonth() + 1, currentMonth.getFullYear());
      }
    }, POLL_INTERVAL_MS);

    return () => clearInterval(id);
  }, [activeSection, currentMonth, loadDashboardData, loadAppointments, loadCalendarAppointments]); // Include all data loading functions

  // Real-time subscription via Laravel Echo for cashier dashboard
  useEffect(() => {
    if (!window?.Echo || typeof window.Echo.channel !== 'function') return;

    const channel = window.Echo.channel('appointments');

    const handler = (payload) => {
      try {
        // If appointment data present, refresh relevant data
        if (payload && (payload.appointment || payload.data || payload)) {
          // If we're viewing dashboard, refresh stats too
          if (activeSection === 'dashboard') loadDashboardData();
          // Always refresh appointments if on appointments/calendar
          if (activeSection === 'appointments' || activeSection === 'calendar') {
            loadAppointments();
            // refresh calendar grouped data when calendar view is active
            if (activeSection === 'calendar') {
              loadCalendarAppointments(currentMonth.getMonth() + 1, currentMonth.getFullYear());
            }
          }
        }
      } catch (e) {
        console.debug('Realtime cashier handler error', e);
      }
    };

    try {
      channel.listen('AppointmentUpdated', handler);
      channel.listen('AppointmentCreated', handler);
      channel.listen('PaymentProcessed', handler);
    } catch (e) {
      try {
        if (channel._pusher) {
          channel._pusher.bind('AppointmentUpdated', handler);
          channel._pusher.bind('AppointmentCreated', handler);
          channel._pusher.bind('PaymentProcessed', handler);
        }
      } catch (err) {
        console.debug('Failed to attach realtime cashier listeners', err);
      }
    }

    return () => {
      try { channel.stopListening('AppointmentUpdated'); } catch (e) {}
      try { channel.stopListening('AppointmentCreated'); } catch (e) {}
      try { channel.stopListening('PaymentProcessed'); } catch (e) {}
      try { 
        if (channel._pusher) { 
          channel._pusher.unbind('AppointmentUpdated'); 
          channel._pusher.unbind('AppointmentCreated'); 
          channel._pusher.unbind('PaymentProcessed'); 
        } 
      } catch (e) {}
    };
  }, [activeSection, currentMonth, loadDashboardData, loadAppointments, loadCalendarAppointments]);

  // Reload action logs when tab changes
  useEffect(() => {
    if (activeSection === 'action-logs') {
      loadActionLogs();
    }
  }, [logsTab, activeSection, loadActionLogs]);

  // Load transactions (sales history)
  const loadTransactions = useCallback(async (page = txPage) => {
    setTxLoading(true);
    try {
      const params = {
        page,
        per_page: txPerPage,
        search: txSearch,
        from: txFilters.from,
        to: txFilters.to,
        service: txFilters.service,
        cashier: txFilters.cashier,
        status: txFilters.status
      };

      // Use existing backend endpoint for completed (paid) appointments as transactions
      const response = await axios.get('/api/cashier/appointments/completed', { params });

      if (response && response.data) {
        const payload = response.data || {};
        // Laravel paginated response comes with data, total, last_page etc
        const list = payload.data || [];
        setTransactions(list || []);
        const total = payload.total || (Array.isArray(list) ? list.length : 0);
        const last = payload.last_page || Math.max(1, Math.ceil(total / txPerPage));
        setTxTotal(total || 0);
        setTxTotalPages(last || 1);
        setTxPage(page);
      }
    } catch (err) {
      console.error('Error loading transactions:', err);
      if (window?.showToast) window.showToast('Transactions', 'Failed to load transactions', 'error');
    } finally {
      setTxLoading(false);
    }
  }, [txFilters, txPerPage, txSearch, txPage]);

  useEffect(() => {
    if (activeSection === 'transactions') {
      loadTransactions(1);
    }
  }, [activeSection, txFilters, txPerPage, txSearch, loadTransactions]);

  // Export transactions CSV
  const exportTransactionsCSV = useCallback(() => {
    if (!transactions || transactions.length === 0) return;
    const rows = [['Date','Ref','Client','Service','Cashier','Status','Amount']];
    transactions.forEach(t => rows.push([
      t.date || t.created_at || '',
      t.ref || t.id || '',
      `${t.client_name || t.user?.first_name || ''} ${t.user?.last_name || ''}`.trim(),
      t.service?.name || t.service || '',
      t.cashier_name || t.cashier || '',
      t.status || '',
      t.amount || t.payment_amount || ''
    ]));
    const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `transactions-${Date.now()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }, [transactions]);

  // Reprint receipt / view receipt for a transaction
  const reprintReceipt = useCallback((txId, tx) => {
    if (!txId || !tx) return;
    
    // Build receipt data from transaction
    const receiptData = {
      id: tx.id,
      date: tx.payment_date || tx.created_at || new Date(),
      clientName: `${tx.user?.first_name || ''} ${tx.user?.last_name || ''}`.trim() || 'Client',
      clientEmail: tx.user?.email || 'N/A',
      service: tx.service?.name || 'N/A',
      appointmentDate: tx.appointment_date || new Date(),
      subtotal: tx.payment_amount || 0,
      discount: tx.discount_amount || 0,
      discountType: tx.discount_type || '',
      totalPaid: (tx.payment_amount || 0) - (tx.discount_amount || 0),
      cashierName: tx.processedBy ? `${tx.processedBy.first_name || ''} ${tx.processedBy.last_name || ''}`.trim() : 'N/A'
    };
    
    setCurrentReceipt(receiptData);
    setShowReceiptModal(true);
  }, []);

  // Profile Management
  const handleEditProfile = useCallback(() => {
    if (user) {
      setProfileFormData({
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        email: user.email || '',
        phone: user.phone || ''
      });
      setIsEditingProfile(true);
    }
  }, [user]);

  const handleCancelEdit = useCallback(() => {
    setIsEditingProfile(false);
    setProfileFormData({
      first_name: '',
      last_name: '',
      email: '',
      phone: ''
    });
  }, []);

  const handleSaveProfile = useCallback(async () => {
    setProfileSaving(true);
    try {
      const response = await callApi((signal) =>
        axios.put('/api/cashier/profile', profileFormData, { signal })
      );

      if (response && response.success) {
        // Update user data
        setUser(prev => ({
          ...prev,
          ...profileFormData
        }));
        setIsEditingProfile(false);
        if (window?.showToast) window.showToast('Profile', 'Profile updated successfully', 'success');
      } else {
        if (window?.showToast) window.showToast('Profile', response?.message || 'Failed to update profile', 'error');
      }
    } catch (error) {
      console.error('Error updating profile:', error);
      if (window?.showToast) window.showToast('Profile', 'Failed to update profile', 'error');
    } finally {
      setProfileSaving(false);
    }
  }, [profileFormData, callApi]);

  // Shift reports / accounting exports
  const loadShiftReport = useCallback(async (from, to) => {
    setShiftLoading(true);
    try {
      const params = { from: from || shiftRange.from, to: to || shiftRange.to };
      const response = await callApi((signal) => axios.get('/api/cashier/shift-reports', { signal, params }));
      if (response && response.success) {
        // Backend returns data directly in response, not nested under data key
        setShiftReportSummary(response || null);
      }
    } catch (err) {
      console.error('Error loading shift report:', err);
      if (window?.showToast) window.showToast('Shift Report', 'Failed to load shift report', 'error');
    } finally {
      setShiftLoading(false);
    }
  }, [callApi, shiftRange]);

  useEffect(() => {
    if (activeSection === 'reports') {
      // default: load today's report when opening
      const today = new Date().toISOString().split('T')[0];
      setShiftRange({ from: today, to: today });
      loadShiftReport(today, today);
    }
  }, [activeSection, loadShiftReport]);

  const exportShiftCSV = useCallback(() => {
    if (!shiftReportSummary) return;
    const rows = [['Metric','Value']];
    Object.keys(shiftReportSummary).forEach(k => rows.push([k, shiftReportSummary[k]]));
    const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `shift-report-${shiftRange.from || 'report'}-${shiftRange.to || ''}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }, [shiftReportSummary, shiftRange]);

  // Handle logout
  const handleLogout = async () => {
    try {
      await logout();
      window.location.href = '/';
    } catch (error) {
      console.error('Logout error:', error);
    }
  };

  // Filter appointments by status
  const filteredAppointments = useMemo(() => {
    const list = Array.isArray(appointments) ? appointments : [];
    return list.filter(apt => {
      const status = (apt && apt.status) || '';
      const paymentStatus = (apt && apt.payment_status) || (apt && apt.payment_status === 0 ? 0 : apt?.payment_status) || '';
      if (appointmentsTab === 'approved') {
        return status === 'approved' && paymentStatus !== 'paid';
      } else if (appointmentsTab === 'completed') {
        return paymentStatus === 'paid' || status === 'completed';
      }
      return false;
    });
  }, [appointments, appointmentsTab]);

  // Filter calendar appointments based on active filters
  const filteredCalendarAppts = useMemo(() => {
    if (!Array.isArray(calendarAppointments)) return [];
    
    // If no filters, show approved appointments
    if (!calendarFilters || Object.keys(calendarFilters).length === 0) {
      return calendarAppointments.filter(a => a.status === 'approved');
    }

    return calendarAppointments.filter(apt => {
      if (calendarFilters.approved && apt.status === 'approved') return true;
      if (calendarFilters.completed && (apt.payment_status === 'paid' || apt.status === 'completed')) return true;
      if (calendarFilters.unpaid && (apt.payment_status === 'unpaid' || apt.payment_status === 0 || !apt.payment_status)) return true;
      if (calendarFilters.partiallyPaid && (apt.payment_status === 'partially_paid' || apt.payment_status === 'partial')) return true;
      if (calendarFilters.pending && apt.status === 'pending') return true;
      return false;
    });
  }, [calendarAppointments, calendarFilters]);

  // Get today's approved appointments
  const todayAppointments = useMemo(() => {
    const today = new Date().toISOString().split('T')[0];
    return filteredAppointments.filter(apt => 
      apt.appointment_date === today && apt.status === 'approved'
    );
  }, [filteredAppointments]);

  // Calculate discount
  const calculateDiscount = useCallback((amount) => {
    let discount = 0;
    let discountType = '';
    
    if (selectedDiscounts.includes('pwd')) {
      discount = amount * 0.20;
      discountType = '20% PWD Discount';
    } else if (selectedDiscounts.includes('senior')) {
      discount = amount * 0.20;
      discountType = '20% Senior Discount';
    } else if (selectedDiscounts.includes('student')) {
      discount = amount * 0.10;
      discountType = '10% Student Discount';
    }
    
    return { discount, discountType };
  }, [selectedDiscounts]);

  // Complete payment
  const handleCompletePayment = useCallback(async (appointment) => {
    const appointmentId = appointment?.id;

    // Determine base amount for discount calculation: prefer entered amount, fall back to service price
    const baseAmount = paymentAmount && !Number.isNaN(parseFloat(paymentAmount))
      ? parseFloat(paymentAmount)
      : (appointment?.service?.price ? Number(appointment.service.price) : 0);

    // For non in-kind payments, require a positive numeric amount
    if (paymentType !== 'in-kind' && (!paymentAmount || parseFloat(paymentAmount) <= 0)) {
      if (window?.showToast) window.showToast('Payment', 'Please enter a valid payment amount', 'warning');
      return;
    }

    const amount = paymentType === 'in-kind' ? 0 : parseFloat(paymentAmount || 0);
    const { discount, discountType } = calculateDiscount(baseAmount);
    const totalPaid = amount - discount;

    try {
      setLoading(true);

      // Build request body
      const body = {
        payment_amount: amount,
        discount_amount: discount,
        discount_type: discountType,
        payment_notes: paymentType === 'in-kind' ? (inKindDescription || 'In-kind payment') : undefined,
        payment_type: paymentType
      };

      // Process payment through cashier endpoint using proper axios call
      const response = await callApi((signal) =>
        axios.post(`/api/cashier/appointments/${appointmentId}/process-payment`, body, { signal })
      );

      // Check if payment was processed successfully
      if (response && response.success) {
        // Use receipt data from backend response
        if (response.data && response.data.receipt) {
          setCurrentReceipt(response.data.receipt);
        }
        setShowReceiptModal(true);

        if (window?.showToast) window.showToast('Payment', 'Payment processed successfully', 'success');

        // Reset form
        setExpandedAppointment(null);
        setViewModalAppointment(null);
        setPaymentAmount('');
        setSelectedDiscounts([]);
        setPaymentType('cash');
        setInKindDescription('');
        
        // Reset pagination to first page
        setCurrentPage(1);
        
        // Close the completion modal
        setShowCompletionConfirmation(false);
        
        // Refresh shift report if currently viewing it (for real-time updates)
        if (activeSection === 'reports') {
          loadShiftReport(shiftRange.from, shiftRange.to);
        }
        
        // Switch to completed appointments tab to show the new completed appointment
        // Adding a small delay to ensure backend is updated before frontend queries
        setTimeout(() => {
          // Make sure we're in the appointments section first
          if (activeSection !== 'appointments') {
            setActiveSection('appointments');
          }
          setAppointmentsTab('completed');
        }, 500);
      } else {
        if (window?.showToast) window.showToast('Payment', response?.error || 'Failed to process payment', 'error');
      }
    } catch (error) {
      console.error('Error completing payment:', error);
      if (window?.showToast) window.showToast('Payment', 'Failed to complete payment. Please try again.', 'error');
    } finally {
      setLoading(false);
    }
  }, [paymentAmount, selectedDiscounts, calculateDiscount, callApi, paymentType, inKindDescription, activeSection, loadShiftReport, shiftRange]);

  // Render Dashboard Section
  const renderDashboard = () => (
    <div className="space-y-6">
      {/* Redesigned analytics + charts */}
      <div className="space-y-4">
        {/* Analytics quick cards */}
        {/* header-level controls relocated to top header */}

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4`}>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Total Revenue</p>
            <p className="text-2xl font-bold text-amber-400 transition-all duration-500">{formatPrice(stats.totalRevenue)}</p>
            <p className="text-xs text-gray-500 mt-1">{revenueData.length} periods</p>
            <div className="mt-3"><Sparkline data={revenueData.slice(-12)} width={140} height={28} type="area" stroke="#f59e0b" /></div>
          </div>

          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4`}>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Total Sales</p>
            <p className="text-2xl font-bold text-amber-400 transition-all duration-500">{stats.totalSales}</p>
            <p className="text-xs text-gray-500 mt-1">Paid orders in timeframe</p>
            <div className="mt-3"><Sparkline data={revenueData.slice(-12)} width={140} height={28} type="bars" stroke="#60a5fa" /></div>
          </div>

          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4`}>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Avg. Sale</p>
            <p className="text-2xl font-bold text-amber-400 transition-all duration-500">{formatPrice(stats.totalSales ? (stats.totalRevenue / stats.totalSales) : 0)}</p>
            <p className="text-xs text-gray-500 mt-1">Revenue / Sale</p>
            <div className="mt-3"><Sparkline data={revenueData.slice(-12)} width={140} height={28} type="dots" stroke="#34d399" /></div>
          </div>

          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4`}>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Today's Revenue</p>
            <p className="text-2xl font-bold text-amber-400 transition-all duration-500">{formatPrice(stats.todayRevenue)}</p>
            <p className="text-xs text-gray-500 mt-1">Today: {stats.todaySales} sales</p>
            <div className="mt-3"><Sparkline data={revenueData.slice(-12)} width={140} height={28} type="line" stroke="#f97316" /></div>
          </div>
        </div>

        {/* Charts area: large line chart + side widgets */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <div className={`lg:col-span-2 ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4`}>
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3`}>Revenue Trend (Last periods)</h3>
            <LineChart data={revenueData} title="Revenue Trend" height={120} embedded variant="bars" responsive maxHeight={260} />
          </div>

          <div className="space-y-4">
            <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4`}>
              <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3`}>Sales by Service</h3>
              <PieChart data={salesByService} title="Sales by Service" />
            </div>

            <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4`}>
              <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3`}>Monthly Breakdown</h3>
              <BarChart data={revenueData} title="Monthly Revenue" height={120} />
            </div>
          </div>
        </div>

        {/* Top services removed: simplified view to avoid overwhelming layout */}
      </div>

      {/* Dev debug: show raw payload returned from API */}
      {/* debug panel removed */}
    </div>
  );

  // Render Appointments Section
  const renderAppointments = () => (
    <div className="space-y-4">
      {/* Tabs */}
      <div className="flex items-center gap-2 border-b border-gray-700">
        <button
          onClick={() => setAppointmentsTab('approved')}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            appointmentsTab === 'approved'
              ? 'border-amber-500 text-amber-400'
              : 'border-transparent text-gray-400 hover:text-amber-300'
          }`}
        >
          Approved Appointments ({appointmentsTab === 'approved' ? filteredAppointments.filter(apt => apt.status === 'approved' && (apt.payment_status !== 'paid')).length : ''})
        </button>
        <button
          onClick={() => setAppointmentsTab('completed')}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            appointmentsTab === 'completed'
              ? 'border-amber-500 text-amber-400'
              : 'border-transparent text-gray-400 hover:text-amber-300'
          }`}
        >
          Completed Appointments ({appointmentsTab === 'completed' ? filteredAppointments.filter(apt => apt.payment_status === 'paid' || apt.status === 'completed').length : ''})
        </button>
        <div className="ml-auto flex items-center gap-2">
          <label className="text-xs text-gray-400 mr-1">Rows</label>
          <select value={perPage} onChange={(e) => { setPerPage(Number(e.target.value)); setCurrentPage(1); }} className="bg-gray-800 border border-gray-700 text-xs text-gray-200 px-2 py-1 rounded">
            <option value={5}>5</option>
            <option value={10}>10</option>
            <option value={20}>20</option>
          </select>
          <button onClick={() => setIsDense(d => !d)} className={`px-2 py-1 text-xs rounded ${isDense ? 'bg-amber-600 text-white' : 'bg-gray-800 text-gray-300'}`} title="Toggle dense view">{isDense ? 'Dense' : 'Comfort'}</button>
        </div>
      </div>

      {/* Today's Appointments Notice */}
      {appointmentsTab === 'approved' && todayAppointments.length > 0 && (
        <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4 space-y-2">
            <div className="flex items-center justify-between">
            <p className="text-amber-400 text-sm font-medium">📅 You have {todayAppointments.length} appointment(s) scheduled for today</p>
            <button
              onClick={() => toggleExpanded('today-list')}
              className="px-2 py-1 text-xs bg-amber-600 text-white rounded"
            >
              {expandedAppointment === 'today-list' ? 'Collapse' : 'View Today'}
            </button>
          </div>

          {expandedAppointment === 'today-list' && (
            <div className="mt-2 space-y-2">
                    {todayAppointments.map((apt) => (
                      <div key={apt.id} className="bg-gray-800 border border-gray-700 rounded p-2 flex items-center justify-between text-xs">
                        <div>
                          <div className="font-medium text-amber-50">{apt.user?.first_name} {apt.user?.last_name}</div>
                          <div className="text-gray-400">{apt.service?.name || 'N/A'} — {apt.start_time}</div>
                        </div>
                        <div>
                          <button
                            onClick={() => setViewModalAppointment(apt)}
                            className="px-2 py-1 text-xs bg-amber-600 text-white rounded"
                          >Open</button>
                        </div>
                      </div>
                    ))}
            </div>
          )}
        </div>
      )}

      {/* Appointments List */}
      <div className="space-y-3">
        {loading ? (
          <div className="flex justify-center py-8">
            <LoadingSpinner />
          </div>
        ) : filteredAppointments.length === 0 ? (
          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-8 text-center`}>
            <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>No {appointmentsTab} appointments found</p>
          </div>
        ) : (
          (() => {
            const displayed = filteredAppointments; // server returns current page
            const start = totalAppointments > 0 ? ((currentPage - 1) * perPage) + 1 : 0;
            const end = Math.min(totalAppointments || displayed.length, currentPage * perPage);
            return (
              <>
                {displayed.map((appointment) => (
            <div
              key={appointment.id}
              className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg overflow-hidden hover:border-amber-500/40 transition-all ${isDense ? 'p-2 text-sm' : ''}`}
            >
              <div className={`${isDense ? 'p-2' : 'p-3'} text-sm`}>
                <div className="flex justify-between items-start">
                  <div className="flex-1">
                    <h3 className="text-sm font-semibold text-amber-50">
                      {appointment.user?.first_name} {appointment.user?.last_name}
                    </h3>
                    <p className="text-xs text-gray-400 mt-1">
                      Service: {appointment.service?.name || 'N/A'}
                    </p>
                    <p className="text-xs text-gray-400">
                      Date: {new Date(appointment.appointment_date).toLocaleDateString()}
                    </p>
                    <p className="text-xs text-gray-400">
                      Time: {appointment.start_time} - {appointment.end_time}
                    </p>
                  </div>
                  
                    <div className="flex items-center gap-2">
                      {appointmentsTab === 'approved' && (
                        <button
                          onClick={() => setViewModalAppointment(appointment)}
                          className="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs flex items-center gap-1"
                        >
                          <EyeIcon className="h-3 w-3" />
                          Complete
                        </button>
                      )}

                      {appointmentsTab === 'completed' && (
                        <button
                          onClick={() => setViewModalAppointment({ ...appointment, _viewOnly: true })}
                          className="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white rounded text-xs flex items-center gap-1"
                        >
                          <EyeIcon className="h-3 w-3" />
                          View
                        </button>
                      )}
                    </div>
                </div>

                
              </div>
            </div>
                ))}

                {/* Pagination Controls */}
                <div className="flex items-center gap-4 mt-3">
                  <div className="text-xs text-gray-400">Showing {start} - {end} of {totalAppointments || filteredAppointments.length}</div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => setCurrentPage(p => Math.max(1, p-1))}
                      disabled={currentPage === 1}
                      className="px-2 py-1 text-xs bg-gray-800 rounded disabled:opacity-50"
                    >Prev</button>
                    <div className="text-xs text-gray-300">Page {currentPage} / {Math.max(1, totalPages)}</div>
                    <button
                      onClick={() => setCurrentPage(p => Math.min(totalPages, p+1))}
                      disabled={currentPage >= totalPages}
                      className="px-2 py-1 text-xs bg-gray-800 rounded disabled:opacity-50"
                    >Next</button>
                  </div>
                </div>
              </>
            );
          })()
        )}
      </div>
    </div>
  );

  // Render Calendar Section
  const renderCalendar = () => {
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December'];

    return (
      <div className="space-y-6">
        {/* Enhanced Interactive Calendar */}
        <InteractiveCalendar
          appointments={filteredCalendarAppts}
          selectedDate={selectedDate}
          onDateSelect={setSelectedDate}
          currentMonth={currentMonth}
          onMonthChange={setCurrentMonth}
          filters={calendarFilters}
          onFiltersChange={setCalendarFilters}
          isLoading={calendarLoading}
          monthSummary={monthSummary}
        />

        {/* Calendar Detail Panel */}
        <CalendarDetailPanel
          selectedDate={selectedDate}
          appointments={filteredCalendarAppts}
          currentMonth={currentMonth}
          monthNames={monthNames}
          onAppointmentClick={(apt) => {
            setViewModalAppointment(apt);
          }}
          isLoading={calendarLoading}
        />
      </div>
    );
  };

  // Render Action Logs Section
  const renderActionLogs = () => (
    <div className="space-y-4">
      {/* Tabs */}
      <div className="flex gap-2 border-b border-gray-700">
        <button
          onClick={() => {
            setLogsTab('admin');
          }}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            logsTab === 'admin'
              ? 'border-amber-500 text-amber-400'
              : 'border-transparent text-gray-400 hover:text-amber-300'
          }`}
        >
          Admin Logs
        </button>
        <button
          onClick={() => {
            setLogsTab('cashier');
          }}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            logsTab === 'cashier'
              ? 'border-amber-500 text-amber-400'
              : 'border-transparent text-gray-400 hover:text-amber-300'
          }`}
        >
          My Logs
        </button>
      </div>

      {/* Pagination Controls on Left */}
      <div className="flex items-center gap-4 mb-4">
        <div className="flex items-center gap-2">
          <label className="text-xs text-gray-400 mr-1">Rows</label>
          <select 
            value={logsPerPage} 
            onChange={(e) => { 
              setLogsPerPage(Number(e.target.value)); 
              setLogsPage(1); 
            }} 
            className="bg-gray-800 border border-gray-700 text-xs text-gray-200 px-2 py-1 rounded"
          >
            <option value={5}>5</option>
            <option value={10}>10</option>
            <option value={20}>20</option>
            <option value={50}>50</option>
          </select>
        </div>

        <div className="text-xs text-gray-400">
          {actionLogs.length > 0 ? (
            <>
              Showing {((logsPage - 1) * logsPerPage) + 1} - {Math.min(logsPage * logsPerPage, totalLogs)} of {totalLogs}
            </>
          ) : (
            'No logs'
          )}
        </div>

        {/* Pagination Navigation */}
        {totalLogPages > 1 && (
          <div className="flex items-center gap-1 ml-auto lg:ml-0">
            <button
              onClick={() => setLogsPage(1)}
              disabled={logsPage === 1}
              className="px-2 py-1 text-xs bg-gray-800 hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed text-gray-300 hover:text-white rounded transition-colors"
              title="First page"
            >
              «
            </button>
            <button
              onClick={() => setLogsPage(prev => Math.max(1, prev - 1))}
              disabled={logsPage === 1}
              className="px-2 py-1 text-xs bg-gray-800 hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed text-gray-300 hover:text-white rounded transition-colors"
              title="Previous page"
            >
              ‹
            </button>
            <span className="text-xs text-gray-400 px-2">
              {logsPage} / {totalLogPages}
            </span>
            <button
              onClick={() => setLogsPage(prev => Math.min(totalLogPages, prev + 1))}
              disabled={logsPage === totalLogPages}
              className="px-2 py-1 text-xs bg-gray-800 hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed text-gray-300 hover:text-white rounded transition-colors"
              title="Next page"
            >
              ›
            </button>
            <button
              onClick={() => setLogsPage(totalLogPages)}
              disabled={logsPage === totalLogPages}
              className="px-2 py-1 text-xs bg-gray-800 hover:bg-amber-600 disabled:opacity-50 disabled:cursor-not-allowed text-gray-300 hover:text-white rounded transition-colors"
              title="Last page"
            >
              »
            </button>
          </div>
        )}
      </div>

      {/* Logs Table */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg overflow-hidden`}>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border-b`}>
              <tr>
                <th className="px-4 py-3 text-left text-amber-400 font-semibold">Date & Time</th>
                <th className="px-4 py-3 text-left text-amber-400 font-semibold">User</th>
                <th className="px-4 py-3 text-left text-amber-400 font-semibold">Action</th>
                <th className="px-4 py-3 text-left text-amber-400 font-semibold">Details</th>
                <th className="px-4 py-3 text-left text-amber-400 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr>
                  <td colSpan="5" className="px-4 py-8 text-center">
                    <LoadingSpinner />
                  </td>
                </tr>
              ) : actionLogs.length === 0 ? (
                <tr>
                  <td colSpan="5" className="px-4 py-8 text-center text-gray-400">
                    No {logsTab === 'admin' ? 'admin' : 'cashier'} logs found
                  </td>
                </tr>
              ) : (
                actionLogs.map((log) => (
                  <tr key={log.id} className="border-b border-gray-800 hover:bg-gray-800/50 transition-colors">
                    <td className="px-4 py-3 text-gray-300">
                      {new Date(log.created_at).toLocaleString()}
                    </td>
                    <td className="px-4 py-3 text-amber-50 font-medium">
                      {log.user ? `${log.user.first_name || ''} ${log.user.last_name || ''}`.trim() : 'Unknown'}
                    </td>
                    <td className="px-4 py-3">
                      <span className="px-2 py-1 rounded-full text-xs font-semibold bg-amber-500/20 text-amber-400">
                        {log.action}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-gray-300">
                      <div className="max-w-xs truncate" title={log.description}>
                        {log.description}
                      </div>
                    </td>
                    <td className="px-4 py-3 text-gray-300">
                      <button
                        onClick={() => {
                          setSelectedActionLog(log);
                          setShowActionLogModal(true);
                        }}
                        className="px-2 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-medium transition-colors"
                      >
                        View
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );

  // Render Profile Section (Editable)
  const renderProfile = () => (
    <div className="max-w-3xl space-y-4">
      {/* Profile Header */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-6`}>
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-center gap-4">
            <div className="w-16 h-16 bg-gradient-to-br from-amber-500 to-amber-600 rounded-full flex items-center justify-center shadow-lg">
              <UserIcon className="h-8 w-8 text-white" />
            </div>
            <div>
              <h2 className={`text-2xl font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>{user?.first_name} {user?.last_name}</h2>
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-sm capitalize`}>{user?.role} • Active</p>
            </div>
          </div>
          <button
            onClick={isEditingProfile ? handleCancelEdit : handleEditProfile}
            className={`px-4 py-2 rounded text-sm font-medium transition-all ${
              isEditingProfile
                ? 'bg-gray-700 text-gray-200 hover:bg-gray-600'
                : 'bg-amber-600 text-white hover:bg-amber-700'
            }`}
          >
            {isEditingProfile ? 'Cancel' : 'Edit Profile'}
          </button>
        </div>
      </div>

      {/* Profile Form/Display */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-6`}>
        <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-6 flex items-center`}>
          <UserCircleIcon className="h-5 w-5 mr-2" />
          Personal Information
        </h3>

        {isEditingProfile ? (
          // Edit Mode
          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs text-gray-400 mb-2 font-semibold">First Name</label>
                <input
                  type="text"
                  value={profileFormData.first_name}
                  onChange={(e) => setProfileFormData(prev => ({ ...prev, first_name: e.target.value }))}
                  className="w-full bg-gray-800 border border-gray-700 px-3 py-2 rounded text-sm text-gray-200 focus:border-amber-500 focus:outline-none"
                  placeholder="Enter first name"
                />
              </div>
              <div>
                <label className="block text-xs text-gray-400 mb-2 font-semibold">Last Name</label>
                <input
                  type="text"
                  value={profileFormData.last_name}
                  onChange={(e) => setProfileFormData(prev => ({ ...prev, last_name: e.target.value }))}
                  className="w-full bg-gray-800 border border-gray-700 px-3 py-2 rounded text-sm text-gray-200 focus:border-amber-500 focus:outline-none"
                  placeholder="Enter last name"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs text-gray-400 mb-2 font-semibold">Email</label>
              <input
                type="email"
                value={profileFormData.email}
                onChange={(e) => setProfileFormData(prev => ({ ...prev, email: e.target.value }))}
                className="w-full bg-gray-800 border border-gray-700 px-3 py-2 rounded text-sm text-gray-200 focus:border-amber-500 focus:outline-none"
                placeholder="Enter email address"
              />
            </div>

            <div>
              <label className="block text-xs text-gray-400 mb-2 font-semibold">Phone</label>
              <input
                type="tel"
                value={profileFormData.phone}
                onChange={(e) => setProfileFormData(prev => ({ ...prev, phone: e.target.value }))}
                className="w-full bg-gray-800 border border-gray-700 px-3 py-2 rounded text-sm text-gray-200 focus:border-amber-500 focus:outline-none"
                placeholder="Enter phone number"
              />
            </div>

            <div className="pt-4 flex gap-3 justify-end">
              <button
                onClick={handleCancelEdit}
                className="px-4 py-2 bg-gray-700 text-gray-200 rounded text-sm font-medium hover:bg-gray-600 transition-all"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveProfile}
                disabled={profileSaving}
                className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded text-sm font-medium hover:from-amber-700 hover:to-amber-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              >
                {profileSaving ? (
                  <>
                    <div className="animate-spin">⟳</div>
                    Saving...
                  </>
                ) : (
                  'Save Changes'
                )}
              </button>
            </div>
          </div>
        ) : (
          // Display Mode
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-4">
              <div className="bg-gray-800/50 rounded p-4 border border-gray-700/50">
                <p className="text-xs text-gray-400 mb-1">First Name</p>
                <p className="text-sm text-amber-50 font-medium">{user?.first_name || 'Not set'}</p>
              </div>
              <div className="bg-gray-800/50 rounded p-4 border border-gray-700/50">
                <p className="text-xs text-gray-400 mb-1">Last Name</p>
                <p className="text-sm text-amber-50 font-medium">{user?.last_name || 'Not set'}</p>
              </div>
            </div>
            <div className="space-y-4">
              <div className="bg-gray-800/50 rounded p-4 border border-gray-700/50">
                <p className="text-xs text-gray-400 mb-1">Email Address</p>
                <p className="text-sm text-amber-50 font-medium break-all">{user?.email || 'Not set'}</p>
              </div>
              <div className="bg-gray-800/50 rounded p-4 border border-gray-700/50">
                <p className="text-xs text-gray-400 mb-1">Phone Number</p>
                <p className="text-sm text-amber-50 font-medium">{user?.phone || 'Not set'}</p>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Account Information */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-6`}>
        <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-4 flex items-center`}>
          <ShieldCheckIcon className="h-5 w-5 mr-2" />
          Account Information
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="bg-gray-800/50 rounded p-4 border border-gray-700/50">
            <p className="text-xs text-gray-400 mb-1">User ID</p>
            <p className="text-sm font-mono text-amber-50">#{user?.id || 'N/A'}</p>
          </div>
          <div className="bg-gray-800/50 rounded p-4 border border-gray-700/50">
            <p className="text-xs text-gray-400 mb-1">Role</p>
            <p className="text-sm text-amber-50 capitalize font-medium">{user?.role || 'N/A'}</p>
          </div>
          <div className="bg-gray-800/50 rounded p-4 border border-gray-700/50">
            <p className="text-xs text-gray-400 mb-1">Account Status</p>
            <p className="text-sm text-green-400 font-medium">✓ Active</p>
          </div>
          <div className="bg-gray-800/50 rounded p-4 border border-gray-700/50">
            <p className="text-xs text-gray-400 mb-1">Member Since</p>
            <p className="text-sm text-amber-50">{user?.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}</p>
          </div>
        </div>
      </div>

      {/* Security Section */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-6`}>
        <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-4 flex items-center`}>
          <LockClosedIcon className="h-5 w-5 mr-2" />
          Security
        </h3>
        <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-sm mb-4`}>Manage your account security settings</p>
        <button className="px-4 py-2 bg-gray-700 text-gray-200 rounded text-sm font-medium hover:bg-gray-600 transition-all">
          Change Password
        </button>
      </div>
    </div>
  );

  // Render Transactions (Sales History) Section
  const renderTransactions = () => (
    <div className="space-y-4">
      <div className="flex items-center gap-2 flex-wrap">
        <input type="text" value={txSearch} onChange={(e) => setTxSearch(e.target.value)} placeholder="Search by client, ref, service..." className={`flex-1 min-w-[200px] border px-3 py-2 rounded text-sm ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'}`} />
        <input type="date" value={txFilters.from} onChange={(e) => setTxFilters(f => ({ ...f, from: e.target.value }))} className={`border px-3 py-2 rounded text-sm ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-white border-gray-300 text-gray-900'}`} />
        <input type="date" value={txFilters.to} onChange={(e) => setTxFilters(f => ({ ...f, to: e.target.value }))} className={`border px-3 py-2 rounded text-sm ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-white border-gray-300 text-gray-900'}`} />
        <select value={txFilters.status} onChange={(e) => setTxFilters(f => ({ ...f, status: e.target.value }))} className={`border px-3 py-2 rounded text-sm ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-white border-gray-300 text-gray-900'}`}>
          <option value="">All</option>
          <option value="paid">Paid</option>
          <option value="pending">Pending</option>
          <option value="refunded">Refunded</option>
        </select>
        <button onClick={() => loadTransactions(1)} className="px-3 py-2 bg-amber-600 text-white rounded text-sm">Filter</button>
        <button onClick={exportTransactionsCSV} className={`px-3 py-2 rounded text-sm ${isDarkMode ? 'bg-gray-800 text-gray-200' : 'bg-gray-200 text-gray-800'}`}>Export CSV</button>
      </div>

      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg overflow-hidden`}>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border-b`}>
              <tr>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Date</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Ref</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Client</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Service</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Amount</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Cashier</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Status</th>
                <th className="px-4 py-3 text-left text-amber-400 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody>
              {txLoading ? (
                <tr><td colSpan="8" className="px-4 py-8 text-center"><LoadingSpinner /></td></tr>
              ) : transactions.length === 0 ? (
                <tr><td colSpan="8" className="px-4 py-8 text-center text-gray-400">No transactions found</td></tr>
              ) : (
                transactions.map(tx => (
                  <tr key={tx.id} className="border-b border-gray-800 hover:bg-gray-800/50">
                    <td className="px-4 py-3 text-gray-300">{new Date(tx.payment_date || tx.created_at).toLocaleString()}</td>
                    <td className="px-4 py-3 text-amber-50">#{tx.id}</td>
                    <td className="px-4 py-3 text-gray-300">{`${tx.user?.first_name || ''} ${tx.user?.last_name || ''}`.trim()}</td>
                    <td className="px-4 py-3 text-gray-300">{tx.service?.name || '—'}</td>
                    <td className="px-4 py-3 text-amber-400">{formatPrice(tx.payment_amount || 0)}</td>
                    <td className="px-4 py-3 text-gray-300">
                      {tx.processedBy ? `${tx.processedBy.first_name || ''} ${tx.processedBy.last_name || ''}`.trim() : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <span className="px-2 py-1 rounded bg-green-500/20 text-green-400 font-medium">Paid</span>
                    </td>
                    <td className="px-4 py-3 text-gray-300">
                      <div className="flex gap-2">
                        <button
                          onClick={() => reprintReceipt(tx.id, tx)}
                          disabled={!!txActionLoading[tx.id]}
                          className={`px-2 py-1 rounded text-xs transition-colors ${txActionLoading[tx.id] ? 'bg-gray-700 text-gray-300 cursor-not-allowed' : 'bg-amber-600 text-white hover:bg-amber-700'}`}
                        >
                          {txActionLoading[tx.id] ? (
                            <span className="inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                          ) : (
                            'Reprint'
                          )}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="flex items-center gap-4 mt-3">
        <div className="text-xs text-gray-400">Showing page {txPage} of {txTotalPages} — {txTotal} transactions</div>
        <div className="flex items-center gap-2">
          <button onClick={() => loadTransactions(Math.max(1, txPage - 1))} disabled={txPage === 1} className="px-2 py-1 text-xs bg-gray-800 rounded disabled:opacity-50">Prev</button>
          <div className="text-xs text-gray-300">Page {txPage} / {txTotalPages}</div>
          <button onClick={() => loadTransactions(Math.min(txTotalPages, txPage + 1))} disabled={txPage >= txTotalPages} className="px-2 py-1 text-xs bg-gray-800 rounded disabled:opacity-50">Next</button>
        </div>
      </div>
    </div>
  );

  // Render Reports Section (Unified Reports)
  const renderReports = () => (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-lg font-bold text-amber-50">Reports & Analytics</h2>
          <p className="text-gray-400 text-sm">View your cashier performance and shift reports</p>
        </div>
      </div>

      {/* Date Range Selector */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4 space-y-3`}>
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Select Date Range</h3>
        <div className="flex items-center gap-2 flex-wrap">
          <input 
            type="date" 
            value={shiftRange.from} 
            onChange={(e) => setShiftRange(r => ({ ...r, from: e.target.value }))} 
            className="bg-gray-800 border border-gray-700 px-3 py-2 rounded text-sm text-gray-200 focus:border-amber-500 focus:outline-none"
          />
          <span className="text-gray-400">to</span>
          <input 
            type="date" 
            value={shiftRange.to} 
            onChange={(e) => setShiftRange(r => ({ ...r, to: e.target.value }))} 
            className="bg-gray-800 border border-gray-700 px-3 py-2 rounded text-sm text-gray-200 focus:border-amber-500 focus:outline-none"
          />
          <button 
            onClick={() => loadShiftReport(shiftRange.from, shiftRange.to)} 
            className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded text-sm hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium shadow transform hover:-translate-y-0.5"
          >
            Generate Report
          </button>
        </div>
      </div>

      {/* Shift Report Summary */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4`}>
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-4 flex items-center`}>
          <ChartBarIcon className="h-4 w-4 mr-2" />
          Shift Report Summary
        </h3>
        {shiftLoading ? (
          <div className="py-8 text-center"><LoadingSpinner /></div>
        ) : !shiftReportSummary ? (
          <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Select a date range and click "Generate Report" to view your shift data</p>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div className={`${isDarkMode ? 'bg-gray-800/50 border-gray-700/50' : 'bg-gray-100 border-gray-200'} rounded p-3 border`}>
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Total Revenue</p>
              <p className="text-2xl font-bold text-amber-500">{formatPrice(shiftReportSummary.total_revenue || 0)}</p>
            </div>
            <div className={`${isDarkMode ? 'bg-gray-800/50 border-gray-700/50' : 'bg-gray-100 border-gray-200'} rounded p-3 border`}>
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Total Sales</p>
              <p className="text-2xl font-bold text-green-500">{shiftReportSummary.total_sales || 0}</p>
            </div>
            <div className={`${isDarkMode ? 'bg-gray-800/50 border-gray-700/50' : 'bg-gray-100 border-gray-200'} rounded p-3 border`}>
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Total Discounts</p>
              <p className="text-2xl font-bold text-blue-500">{formatPrice(shiftReportSummary.total_discounts || 0)}</p>
            </div>
            <div className={`${isDarkMode ? 'bg-gray-800/50 border-gray-700/50' : 'bg-gray-100 border-gray-200'} rounded p-3 border`}>
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Cash Collected</p>
              <p className={`text-xl font-bold ${isDarkMode ? 'text-amber-300' : 'text-amber-600'}`}>{formatPrice(shiftReportSummary.cash_collected || 0)}</p>
            </div>
            <div className={`${isDarkMode ? 'bg-gray-800/50 border-gray-700/50' : 'bg-gray-100 border-gray-200'} rounded p-3 border`}>
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Card Collected</p>
              <p className={`text-xl font-bold ${isDarkMode ? 'text-blue-300' : 'text-blue-600'}`}>{formatPrice(shiftReportSummary.card_collected || 0)}</p>
            </div>
            <div className={`${isDarkMode ? 'bg-gray-800/50 border-gray-700/50' : 'bg-gray-100 border-gray-200'} rounded p-3 border`}>
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Refunds</p>
              <p className="text-xl font-bold text-red-500">{formatPrice(shiftReportSummary.total_refunds || 0)}</p>
            </div>
          </div>
        )}
      </div>

      {/* Dashboard Statistics */}
      {stats && (
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4`}>
          <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-4 flex items-center`}>
            <ChartPieIcon className="h-4 w-4 mr-2" />
            Overall Dashboard Statistics
          </h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div className="bg-gradient-to-br from-amber-500/10 to-amber-600/10 border border-amber-500/20 rounded p-3 hover:border-amber-500/40 transition-all">
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Monthly Revenue</p>
              <p className="text-xl font-bold text-amber-500">{formatPrice(stats.totalRevenue)}</p>
              <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-1`}>{stats.totalSales} sales</p>
            </div>
            <div className="bg-gradient-to-br from-green-500/10 to-green-600/10 border border-green-500/20 rounded p-3 hover:border-green-500/40 transition-all">
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Today's Revenue</p>
              <p className="text-xl font-bold text-green-500">{formatPrice(stats.todayRevenue)}</p>
              <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-1`}>{stats.todaySales} sales</p>
            </div>
            <div className="bg-gradient-to-br from-blue-500/10 to-blue-600/10 border border-blue-500/20 rounded p-3 hover:border-blue-500/40 transition-all">
              <p className="text-gray-400 text-xs mb-1">Total Sales (Period)</p>
              <p className="text-xl font-bold text-blue-400">{stats.totalSales}</p>
              <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-1`}>transactions</p>
            </div>
            <div className="bg-gradient-to-br from-purple-500/10 to-purple-600/10 border border-purple-500/20 rounded p-3 hover:border-purple-500/40 transition-all">
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Performance</p>
              <p className="text-xl font-bold text-purple-500">{((stats.totalSales / 100) * 100).toFixed(1)}%</p>
              <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-1`}>completion</p>
            </div>
          </div>
        </div>
      )}

      {/* Export Options */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg p-4`}>
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-4 flex items-center`}>
          <DocumentArrowDownIcon className="h-4 w-4 mr-2" />
          Export Options
        </h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <button 
            onClick={exportShiftCSV} 
            className="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-all font-medium flex items-center justify-center gap-2 shadow"
          >
            <DocumentTextIcon className="h-4 w-4" />
            Export to CSV
          </button>
          <button 
            onClick={() => {
              setConfirmModalProps({
                title: 'Export to Accounting',
                message: 'Exporting to Xero/QuickBooks requires backend integration. Proceed to trigger server export?',
                loading: false,
                onConfirm: async () => {
                  setConfirmModalProps(p => ({ ...p, loading: true }));
                  try {
                    await callApi((signal) => axios.post('/api/cashier/shift-reports/export', { from: shiftRange.from, to: shiftRange.to }, { signal }));
                    if (window?.showToast) window.showToast('Export', 'Export queued. Check Reports.', 'success');
                  } catch (err) {
                    console.error('Accounting export error', err);
                    if (window?.showToast) window.showToast('Export', 'Failed to queue export', 'error');
                  } finally {
                    setConfirmModalProps(p => ({ ...p, loading: false }));
                    setShowConfirmModal(false);
                  }
                }
              });
              setShowConfirmModal(true);
            }} 
            className="px-4 py-2 bg-purple-600 text-white rounded text-sm hover:bg-purple-700 transition-all font-medium flex items-center justify-center gap-2 shadow"
          >
            <BuildingLibraryIcon className="h-4 w-4" />
            Accounting Export
          </button>
          <button 
            onClick={() => {
              const today = new Date().toISOString().split('T')[0];
              setShiftRange({ from: today, to: today });
              loadShiftReport(today, today);
            }}
            className={`px-4 py-2 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600' : 'bg-gray-200 text-gray-800 hover:bg-gray-300'} text-white rounded text-sm transition-all font-medium flex items-center justify-center gap-2 shadow`}
          >
            <ArrowPathIcon className="h-4 w-4" />
            Today's Report
          </button>
        </div>
      </div>

      {/* Report Information */}
      <div className="bg-gray-900 border border-amber-500/20 rounded-lg p-4">
        <h3 className="text-sm font-semibold text-amber-50 mb-3 flex items-center">
          <InformationCircleIcon className="h-4 w-4 mr-2" />
          Report Information
        </h3>
        <div className="space-y-2 text-xs text-gray-400">
          <p>📊 <strong>Shift Reports:</strong> Detailed breakdown of your daily/custom period transactions including revenue, sales count, and payment methods.</p>
          <p>💾 <strong>CSV Export:</strong> Download raw data in CSV format for use in spreadsheet applications.</p>
          <p>🏢 <strong>Accounting Export:</strong> Send data directly to Xero or QuickBooks for accounting purposes.</p>
          <p>📈 <strong>Dashboard Stats:</strong> Real-time overview of monthly and daily performance metrics.</p>
        </div>
      </div>
    </div>
  );

  // Render content based on active section
  const renderContent = () => {
    switch (activeSection) {
      case 'dashboard':
        return renderDashboard();
      case 'transactions':
        return renderTransactions();
      case 'appointments':
        return renderAppointments();
      case 'calendar':
        return renderCalendar();
      case 'reports':
        return renderReports();
      case 'action-logs':
        return renderActionLogs();
      case 'profile':
        return renderProfile();
      default:
        return renderDashboard();
    }
  };

  return (
    <div className={`min-h-screen ${isDarkMode ? 'bg-gradient-to-br from-gray-900 to-black' : 'bg-gradient-to-br from-gray-50 to-gray-100'} flex flex-col lg:h-screen transition-colors duration-300`}>
      {/* Mobile Header */}
      <div className={`lg:hidden fixed top-0 left-0 right-0 z-40 flex items-center justify-between px-4 py-3 ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-gray-50 border-amber-300/40'} border-b shadow transition-colors duration-300`}>
        <div className="w-10"></div>
        <span className={`${isDarkMode ? 'text-amber-50' : 'text-amber-900'} font-bold text-base`}>Cashier Portal</span>
        <button
          onClick={() => setShowMobileSidebar(!showMobileSidebar)}
          className="text-amber-500 hover:text-amber-400 transition-colors p-2 rounded-lg hover:bg-amber-500/10"
        >
          <Bars3Icon className="h-6 w-6" />
        </button>
      </div>

      {/* Mobile Sidebar Overlay */}
      {showMobileSidebar && (
        <div
          className="lg:hidden fixed inset-0 bg-black/50 z-30"
          onClick={() => setShowMobileSidebar(false)}
        ></div>
      )}

      {/* Sidebar */}
      <div className={`fixed inset-y-0 right-0 lg:right-auto lg:left-0 z-40 h-screen lg:h-screen ${isDarkMode ? 'bg-gradient-to-b from-gray-900 to-black border-amber-500/20' : 'bg-gradient-to-b from-gray-50 to-gray-100 border-amber-300/40'} border-l lg:border-l-0 lg:border-r shadow-xl flex-shrink-0 transition-all duration-300 lg:translate-x-0 ${
        showMobileSidebar ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'
      } ${isCollapsedDesktop ? 'lg:w-20' : 'w-64'}`}>
        <div className="flex flex-col h-full overflow-hidden">
          <div className={`flex items-center justify-between h-16 shadow-md ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-gray-50 border-amber-300/40'} px-3 border-b transition-colors duration-300 flex-shrink-0`}>
            <div className="flex items-center space-x-2">
              <div className="w-8 h-8 bg-gradient-to-r from-amber-500 to-amber-600 rounded-lg flex items-center justify-center shadow">
                <BuildingLibraryIcon className="h-4 w-4 text-white" />
              </div>
              <span className={`text-sm lg:text-base font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300 truncate hidden lg:inline ${isCollapsedDesktop ? 'lg:hidden' : ''}`}>CASHIER</span>
            </div>
            <div className="flex items-center space-x-1">
              <button
                onClick={() => setIsCollapsedDesktop(!isCollapsedDesktop)}
                className="hidden lg:flex text-gray-400 hover:text-amber-400 transition-colors p-1 rounded-lg hover:bg-amber-500/10 flex-shrink-0 items-center justify-center"
              >
                {isCollapsedDesktop ? <ChevronRightIcon className="h-5 w-5" /> : <ChevronLeftIcon className="h-5 w-5" />}
              </button>
              <button
                onClick={() => setShowMobileSidebar(false)}
                className="lg:hidden text-gray-400 hover:text-amber-400 transition-colors p-1 rounded-lg hover:bg-amber-500/10 flex-shrink-0"
              >
                <XMarkIcon className="h-5 w-5" />
              </button>
            </div>
          </div>

          {/* Navigation */}
          <nav className={`flex-1 py-2 lg:py-2.5 space-y-2 lg:space-y-2.5 overflow-y-auto transition-all duration-300 min-h-0 scrollbar-hide ${isCollapsedDesktop ? 'lg:px-2' : 'px-2 lg:px-2.5'}`}>
            {navigation.map((item, index) => {
              if (item.section) {
                const isDropdownOpen = openDropdowns[item.section];
                
                return (
                  <div key={item.section} className="space-y-1">
                    <div className="flex items-center justify-between px-3 py-1">
                      {!isCollapsedDesktop && (
                        <span className="text-xs font-semibold text-amber-400/70 uppercase tracking-wider">
                          {item.section}
                        </span>
                      )}
                      <button
                        onClick={() => setOpenDropdowns(prev => ({
                          ...prev,
                          [item.section]: !prev[item.section]
                        }))}
                        className={`flex items-center justify-center p-1 rounded transition-all duration-200 ${
                          isDropdownOpen 
                            ? 'text-amber-400' 
                            : 'text-gray-400 hover:text-amber-300'
                        } ${isCollapsedDesktop ? 'w-full' : ''}`}
                        title={`${isDropdownOpen ? 'Collapse' : 'Expand'} ${item.section}`}
                      >
                        <ChevronDownIcon className={`h-4 w-4 transition-transform duration-200 ${isDropdownOpen ? 'rotate-180' : ''}`} />
                      </button>
                    </div>
                    <div className="space-y-1">
                      {/* Dropdown items */}
                      {isDropdownOpen && (
                        item.items.map((subItem) => (
                          <button
                            key={subItem.key}
                            onClick={() => {
                              setActiveSection(subItem.key);
                              setShowMobileSidebar(false);
                            }}
                            className={`w-full flex items-center justify-center lg:justify-start px-2 lg:px-2.5 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 border group ${
                              activeSection === subItem.key
                                ? 'bg-amber-500/10 text-amber-400 border-amber-500/40 shadow shadow-amber-500/10'
                                : 'text-gray-400 border-transparent hover:bg-amber-500/5 hover:text-amber-300 hover:border-amber-500/20'
                            } ${isCollapsedDesktop ? 'lg:justify-center lg:px-2' : ''}`}
                            title={isCollapsedDesktop ? subItem.name : ''}
                          >
                            <div className="flex items-center min-w-0">
                              <subItem.icon className={`h-4 w-4 flex-shrink-0 transition-colors ${
                                activeSection === subItem.key ? 'text-amber-400' : 'text-gray-500 group-hover:text-amber-400'
                              } ${!isCollapsedDesktop ? 'mr-2' : ''}`} />
                              {!isCollapsedDesktop && (
                                <span className="flex items-center justify-between flex-1 min-w-0">
                                  <span className="truncate">{subItem.name}</span>
                                  {subItem.badge && <span className="text-xs bg-amber-500/30 text-amber-300 px-1.5 rounded ml-2 flex-shrink-0">{subItem.badge}</span>}
                                </span>
                              )}
                            </div>
                          </button>
                        ))
                      )}
                    </div>
                  </div>
                );
              }
              
              return (
                <button
                  key={item.key}
                  onClick={() => {
                    setActiveSection(item.key);
                    setShowMobileSidebar(false);
                  }}
                  className={`w-full flex items-center justify-center lg:justify-start px-2 lg:px-2.5 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 border group ${
                    activeSection === item.key
                      ? 'bg-amber-500/10 text-amber-400 border-amber-500/40 shadow shadow-amber-500/10'
                      : 'text-gray-400 border-transparent hover:bg-amber-500/5 hover:text-amber-300 hover:border-amber-500/20'
                  } ${isCollapsedDesktop ? 'lg:justify-center lg:px-2' : ''}`}
                  title={isCollapsedDesktop ? item.name : ''}
                >
                  <div className="flex items-center min-w-0">
                    <item.icon className={`h-4 w-4 flex-shrink-0 transition-colors ${
                      activeSection === item.key ? 'text-amber-400' : 'text-gray-500 group-hover:text-amber-400'
                    } ${!isCollapsedDesktop ? 'mr-2' : ''}`} />
                    {!isCollapsedDesktop && <span className="truncate">{item.name}</span>}
                  </div>
                </button>
              );
            })}
            
            {/* Logout */}
            <button
              onClick={() => setShowLogoutModal(true)}
              className={`w-full flex items-center justify-center lg:justify-start px-2 lg:px-2.5 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 border group ${isDarkMode ? 'text-red-400 border-transparent hover:bg-red-500/10 hover:border-red-500/30' : 'text-red-600 border-transparent hover:bg-red-500/20 hover:border-red-500/40'}`}
              title={isCollapsedDesktop ? 'Logout' : ''}
            >
              <div className="flex items-center min-w-0">
                <XCircleIcon className={`h-4 w-4 flex-shrink-0 ${!isCollapsedDesktop ? 'mr-2' : ''}`} />
                {!isCollapsedDesktop && <span className="truncate">Logout</span>}
              </div>
            </button>
          </nav>

          <div className={`p-2 lg:p-3 border-t border-amber-500/20 flex-shrink-0 transition-all duration-300 ${isCollapsedDesktop ? 'lg:flex lg:items-center lg:justify-center' : ''}`}>
              {/* sidebar profile removed per request (SA / System Administrator / admin) */}
              
              {/* sidebar logout removed per request - header will show confirmation-only logout */}
            </div>
        </div>
      </div>

      {/* Main Content */}
      <div className={`flex-1 flex flex-col min-w-0 mt-16 lg:mt-0 lg:h-screen lg:overflow-y-auto transition-all duration-300 ${isCollapsedDesktop ? 'lg:ml-20' : 'lg:ml-64'}`}>
        {/* Header */}
        <header className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-gray-50 border-amber-300/40'} border-b shadow-md flex-shrink-0 sticky top-0 z-30 transition-colors duration-300`}>
          <div className="flex justify-between items-center px-3 sm:px-4 lg:px-6 py-2 lg:py-3">
            <div className="flex items-center space-x-2 lg:space-x-3 min-w-0">
              <div>
                <h1 className={`text-base lg:text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300 capitalize truncate`}>
                  {activeSection.replace('-', ' ')}
                </h1>
                <p className={`${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} mt-0.5 text-xs lg:text-sm transition-colors duration-300 hidden sm:block`}>
                  Welcome back, {user?.first_name}
                </p>
              </div>
            </div>
            <div className="flex-shrink-0 flex items-center space-x-3">
              <div className={`text-xs lg:text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} transition-colors duration-300 hidden sm:block text-right`}>
                {new Date().toLocaleDateString('en-US', { 
                  weekday: 'short', 
                  year: 'numeric', 
                  month: 'short', 
                  day: 'numeric' 
                })}
              </div>
              <div className="flex items-center gap-2">
                <select
                  value={timeframe}
                  onChange={(e) => setTimeframe(e.target.value)}
                  className={`${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-gray-100 border-gray-300 text-gray-800'} border text-xs px-2 py-1 rounded transition-colors duration-300`}
                >
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                  <option value="yearly">Yearly</option>
                </select>
              </div>
            </div>
          </div>
        </header>

        {/* Main Content Area */}
        <main className="flex-1 p-3 lg:p-6 scrollbar-hide text-sm">
          {renderContent()}
        </main>
      </div>

      {/* Modals */}
      <LogoutModal
        isOpen={showLogoutModal}
        onClose={() => setShowLogoutModal(false)}
        onConfirm={handleLogout}
        loading={loading}
      />

      <ReceiptModal
        isOpen={showReceiptModal}
        onClose={() => setShowReceiptModal(false)}
        receiptData={currentReceipt}
      />

      <ActionLogModal
        isOpen={showActionLogModal}
        onClose={() => setShowActionLogModal(false)}
        logData={selectedActionLog}
      />
      
      <CompletionConfirmationModal
        isOpen={showCompletionConfirmation}
        onClose={() => {
          setShowCompletionConfirmation(false);
          setCompletionCountdown(5);
          setIsCompletionLoading(false);
        }}
        appointment={confirmAppointment}
        countdown={completionCountdown}
        onConfirm={async () => {
          setIsCompletionLoading(true);
          try {
            await handleCompletePayment(confirmAppointment);
          } finally {
            // Only close and reset if we're not showing receipt modal
            setTimeout(() => {
              if (!showReceiptModal) {
                setShowCompletionConfirmation(false);
              }
              setCompletionCountdown(5);
              setIsCompletionLoading(false);
            }, 300);
          }
        }}
        loading={isCompletionLoading}
        paymentAmount={paymentAmount}
        paymentType={paymentType}
        selectedDiscounts={selectedDiscounts}
        calculateDiscount={calculateDiscount}
        inKindDescription={inKindDescription}
      />
      
      <ConfirmPaymentModal
        isOpen={showConfirmPaymentModal}
        onClose={() => setShowConfirmPaymentModal(false)}
        appointment={confirmAppointment}
        onConfirm={handleCompletePayment}
        paymentAmount={paymentAmount}
        paymentType={paymentType}
        selectedDiscounts={selectedDiscounts}
        calculateDiscount={calculateDiscount}
        inKindDescription={inKindDescription}
      />
      <ConfirmModal
        isOpen={showConfirmModal}
        title={confirmModalProps.title}
        message={confirmModalProps.message}
        loading={confirmModalProps.loading}
        onCancel={() => setShowConfirmModal(false)}
        onConfirm={() => {
          if (typeof confirmModalProps.onConfirm === 'function') confirmModalProps.onConfirm();
        }}
      />
      
          {/* Appointment View Modal */}
          {viewModalAppointment && (
            <AppointmentModal
              isOpen={!!viewModalAppointment}
              onClose={() => setViewModalAppointment(null)}
              appointment={viewModalAppointment}
              isViewOnly={viewModalAppointment?._viewOnly || false}
              paymentAmount={paymentAmount}
              setPaymentAmount={setPaymentAmount}
              paymentType={paymentType}
              setPaymentType={setPaymentType}
              inKindDescription={inKindDescription}
              setInKindDescription={setInKindDescription}
              selectedDiscounts={selectedDiscounts}
              setSelectedDiscounts={setSelectedDiscounts}
              calculateDiscount={calculateDiscount}
              onComplete={() => {
                setConfirmAppointment(viewModalAppointment);
                setShowCompletionConfirmation(true);
                setCompletionCountdown(5);
                setViewModalAppointment(null);
              }}
            />
          )}
          
          {/* Completion Countdown Effect */}
          {showCompletionConfirmation && (
            <div className="fixed inset-0 z-40"></div>
          )}
          
          {showCompletionConfirmation && !isCompletionLoading && completionCountdown > 0 && (
            <div className="fixed inset-0 flex items-end justify-center z-40 pointer-events-none p-4">
              <div className="bg-amber-500/20 text-amber-400 px-4 py-2 rounded-lg text-sm font-semibold mb-4 animate-pulse">
                {completionCountdown} seconds remaining...
              </div>
            </div>
          )}
    </div>
  );
};

export default CashierDashboard;
