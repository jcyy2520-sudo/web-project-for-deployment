import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { useAuth } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
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
  LockClosedIcon,
  SunIcon,
  MoonIcon,
  BellIcon,
  ChatBubbleLeftRightIcon,
  ArrowUturnLeftIcon,
  EnvelopeIcon,
  PaperAirplaneIcon,
  EyeSlashIcon,
  MagnifyingGlassIcon
} from '@heroicons/react/24/outline';
import LoadingSpinner from '../components/LoadingSpinner';
import LineChart from '../components/charts/LineChart';
import InteractiveCalendar from '../components/calendar/InteractiveCalendar';
import CalendarDetailPanel from '../components/calendar/CalendarDetailPanel';
import NotificationBell from '../components/user/NotificationBell';
import ThemeToggle from '../components/ui/ThemeToggle';
import { formatServiceName, formatPrice, formatDateDisplay } from '../utils/format';

const ACTION_LOG_METADATA_LABELS = {
  receipt_id: 'Receipt ID',
  client_email: 'Client Email',
  service_price: 'Service Price',
  amount_entered: 'Amount Entered',
  discount_type: 'Discount Type',
  discount_rate_from_db: 'Discount Rate',
  discount_amount: 'Discount Amount',
  payment_type: 'Payment Type',
  total_paid: 'Total Paid',
  total_paid_so_far: 'Total Paid So Far',
  balance_remaining: 'Balance Remaining',
  shortfall: 'Shortfall',
  in_kind_description: 'In-Kind Description',
  in_kind_estimated_value: 'In-Kind Estimated Value',
  client_name: 'Client',
  refund_amount: 'Refund Amount',
  reason: 'Reason',
  original_payment_amount: 'Original Payment Amount',
  original_cashier_id: 'Original Cashier ID',
  requesting_user_id: 'Requested By',
};

const ACTION_LOG_CURRENCY_KEYS = new Set([
  'service_price',
  'amount_entered',
  'discount_amount',
  'total_paid',
  'total_paid_so_far',
  'balance_remaining',
  'shortfall',
  'in_kind_estimated_value',
  'refund_amount',
  'original_payment_amount',
]);

const formatActionLogLabel = (key) => {
  if (!key) return 'Detail';

  return ACTION_LOG_METADATA_LABELS[key] || key
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
};

const formatActionLogValue = (key, value) => {
  if (value === null || value === undefined || value === '') {
    return '—';
  }

  if (ACTION_LOG_CURRENCY_KEYS.has(key) && !Number.isNaN(Number(value))) {
    return formatPrice(value);
  }

  if (typeof value === 'boolean') {
    return value ? 'Yes' : 'No';
  }

  if (Array.isArray(value)) {
    return value.map((item) => String(item)).join(', ');
  }

  if (typeof value === 'object') {
    return JSON.stringify(value);
  }

  return String(value).replace(/_/g, ' ');
};

const getActionLogMetadataEntries = (metadata) => {
  if (!metadata || Array.isArray(metadata) || typeof metadata !== 'object') {
    return [];
  }

  return Object.entries(metadata)
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => ({
      key,
      label: formatActionLogLabel(key),
      value: formatActionLogValue(key, value),
    }));
};

const formatActionLogStatus = (status) => String(status || 'success').replace(/_/g, ' ');

const formatSentenceLabel = (value) => {
  const normalized = String(value || '')
    .replace(/_/g, ' ')
    .trim()
    .replace(/\s+/g, ' ')
    .toLowerCase();

  if (!normalized) {
    return '—';
  }

  return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const getActionLogStatusClasses = (status, isDarkMode = true) => {
  const normalizedStatus = String(status || 'success').toLowerCase();

  if (normalizedStatus === 'failed' || normalizedStatus === 'error') {
    return isDarkMode ? 'bg-red-500/20 text-red-400' : 'bg-red-100 text-red-700';
  }

  if (normalizedStatus === 'warning') {
    return isDarkMode ? 'bg-yellow-500/20 text-yellow-400' : 'bg-yellow-100 text-yellow-700';
  }

  return isDarkMode ? 'bg-emerald-500/20 text-emerald-400' : 'bg-emerald-100 text-emerald-700';
};

// Enhanced Chart Components
const BarChart = ({ data, title, color = 'amber', height = 160, isDarkMode = true }) => {
  const safeData = useMemo(() => 
    data.map(item => ({ ...item, value: Number(item.value) || 0 })), 
    [data]
  );
  const maxValue = Math.max(...safeData.map(item => item.value), 1);
  
  return (
    <div className={`${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'} border rounded-xl p-5 hover:shadow-lg hover:shadow-amber-500/5 transition-all duration-300`}>
      {title && (
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-gray-200' : 'text-gray-800'} mb-4 flex items-center gap-2`}>
          <ChartBarIcon className="h-4 w-4 text-amber-500" />
          {title}
        </h3>
      )}
      <div className="space-y-3" style={{ maxHeight: `${height}px`, overflow: 'auto' }}>
        {safeData.map((item, index) => {
          const pct = ((item.value / maxValue) * 100).toFixed(0);
          return (
            <div key={index} className="group" title={`${item.label}: ${item.value}`}>
              <div className="flex items-center justify-between mb-1">
                <span className={`text-xs font-medium ${isDarkMode ? 'text-gray-400 group-hover:text-gray-200' : 'text-gray-500 group-hover:text-gray-700'} truncate max-w-[45%] transition-colors`}>
                  {item.label}
                </span>
                <span className={`text-xs font-bold ${isDarkMode ? 'text-gray-300' : 'text-gray-700'} tabular-nums`}>
                  {item.value.toLocaleString()}
                </span>
              </div>
              <div className={`w-full h-2 rounded-full ${isDarkMode ? 'bg-gray-800' : 'bg-gray-100'} overflow-hidden`}>
                <div 
                  className="h-full rounded-full bg-gradient-to-r from-amber-500 to-amber-400 transition-all duration-700 ease-out group-hover:from-amber-400 group-hover:to-yellow-400 relative"
                  style={{ width: `${Math.max(2, pct)}%` }}
                >
                  <div className="absolute inset-0 bg-white/20 rounded-full"></div>
                </div>
              </div>
            </div>
          );
        })}
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
                  stroke={isDarkMode ? "#1f2937" : "var(--borders)"}
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

// Modern Donut Chart for service distribution analytics
const DonutChart = ({ data, title, isDarkMode = true, size = 160 }) => {
  const safeData = useMemo(() =>
    data.map(item => ({ ...item, value: Number(item.value) || 0 })),
    [data]
  );
  const total = Math.max(safeData.reduce((sum, item) => sum + item.value, 0), 1);
  const defaultColors = ['#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4', '#f97316', '#ec4899'];

  let cumulativePercent = 0;
  const segments = safeData.map((item, i) => {
    const percent = (item.value / total) * 100;
    const startAngle = cumulativePercent * 3.6;
    cumulativePercent += percent;
    const endAngle = cumulativePercent * 3.6;
    const color = item.color || defaultColors[i % defaultColors.length];
    return { ...item, percent, startAngle, endAngle, color };
  });

  const r = 44, innerR = 30, cx = 50, cy = 50;

  return (
    <div>
      {title && (
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-gray-200' : 'text-gray-800'} mb-4 flex items-center gap-2`}>
          <ChartPieIcon className="h-4 w-4 text-amber-500" />
          {title}
        </h3>
      )}
      <div className="flex flex-col items-center">
        <div className="relative" style={{ width: size, height: size }}>
          <svg viewBox="0 0 100 100" className="w-full h-full -rotate-90">
            {segments.map((seg, i) => {
              if (seg.percent <= 0) return null;
              const startRad = (seg.startAngle * Math.PI) / 180;
              const endRad = (seg.endAngle * Math.PI) / 180;
              const x1 = cx + r * Math.cos(startRad);
              const y1 = cy + r * Math.sin(startRad);
              const x2 = cx + r * Math.cos(endRad);
              const y2 = cy + r * Math.sin(endRad);
              const ix1 = cx + innerR * Math.cos(endRad);
              const iy1 = cy + innerR * Math.sin(endRad);
              const ix2 = cx + innerR * Math.cos(startRad);
              const iy2 = cy + innerR * Math.sin(startRad);
              const largeArc = seg.percent > 50 ? 1 : 0;
              const d = [
                `M ${x1} ${y1}`,
                `A ${r} ${r} 0 ${largeArc} 1 ${x2} ${y2}`,
                `L ${ix1} ${iy1}`,
                `A ${innerR} ${innerR} 0 ${largeArc} 0 ${ix2} ${iy2}`,
                'Z'
              ].join(' ');
              return (
                <path key={i} d={d} fill={seg.color} className="transition-all duration-300 hover:opacity-75 cursor-pointer" style={{ filter: 'drop-shadow(0 1px 3px rgba(0,0,0,0.15))' }}>
                  <title>{seg.label}: {seg.value} ({seg.percent.toFixed(1)}%)</title>
                </path>
              );
            })}
          </svg>
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="text-center">
              <div className={`text-xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>{total}</div>
              <div className={`text-[10px] font-medium ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Total</div>
            </div>
          </div>
        </div>
        <div className="mt-4 w-full space-y-1.5 max-h-36 overflow-auto">
          {segments.map((seg, i) => (
            <div key={i} className={`flex items-center gap-2 text-xs px-2 py-1.5 rounded-lg ${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'} transition-colors cursor-default`}>
              <div className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: seg.color }} />
              <span className={`flex-1 truncate ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{seg.label}</span>
              <span className={`font-semibold tabular-nums ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{seg.percent.toFixed(0)}%</span>
              <span className={`${isDarkMode ? 'text-gray-600' : 'text-gray-400'} tabular-nums`}>({seg.value})</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

// Enhanced sparkline for KPI cards — supports multiple mini chart types
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

const formatRelativeSync = (timestamp) => {
  if (!timestamp) return 'Waiting for first sync';
  const deltaMs = Math.max(0, Date.now() - new Date(timestamp).getTime());
  const deltaSeconds = Math.floor(deltaMs / 1000);
  if (deltaSeconds < 5) return 'Just now';
  if (deltaSeconds < 60) return `${deltaSeconds}s ago`;
  const deltaMinutes = Math.floor(deltaSeconds / 60);
  if (deltaMinutes < 60) return `${deltaMinutes}m ago`;
  const deltaHours = Math.floor(deltaMinutes / 60);
  return `${deltaHours}h ago`;
};

const extractCollectionPayload = (payload) => {
  if (Array.isArray(payload)) {
    return payload;
  }

  if (Array.isArray(payload?.data)) {
    return payload.data;
  }

  if (Array.isArray(payload?.data?.data)) {
    return payload.data.data;
  }

  if (Array.isArray(payload?.appointments)) {
    return payload.appointments;
  }

  return [];
};

const getDateOnly = (value) => String(value || '').match(/^(\d{4}-\d{2}-\d{2})/)?.[1] || '';

const CASHIER_SILENT_RESYNC_MIN_GAP_MS = 1500;
const CASHIER_SYNCABLE_SECTIONS = ['dashboard', 'appointments', 'calendar', 'action-logs', 'refunds', 'messages', 'notifications', 'transactions', 'reports'];

// Using shared LineChart component from components/charts/LineChart

// Logout Modal Component
const LogoutModal = ({ isOpen, onClose, onConfirm, loading, isDarkMode = true }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-amber-300/40'} border rounded-lg shadow-xl w-full max-w-md transform animate-scaleIn`}>
        <div className="p-4">
          <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3`}>Confirm Logout</h3>
          <p className={`${isDarkMode ? 'text-gray-300' : 'text-gray-700'} text-sm mb-4`}>Are you sure you want to logout?</p>
          <div className="flex justify-end space-x-2">
            <button
              onClick={onClose}
              disabled={loading}
              className={`px-3 py-2 border rounded-lg transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50 ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800 focus:ring-offset-gray-900' : 'border-gray-300 text-gray-700 hover:bg-gray-100 focus:ring-offset-white'}`}
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              disabled={loading}
              className={`px-3 py-2 rounded-lg transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 ${isDarkMode ? 'bg-amber-600 hover:bg-amber-700 text-white focus:ring-amber-500 focus:ring-offset-gray-900' : 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500 focus:ring-offset-white'}`}
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

// Completion Confirmation Modal with checkbox confirmation
const CompletionConfirmationModal = ({ isOpen, onClose, appointment, onConfirm, loading, paymentAmount, paymentType, selectedDiscounts, calculateDiscount, inKindDescription, inKindEstimatedValue, isDarkMode = true }) => {
  const [confirmed, setConfirmed] = useState(false);
  
  // Reset checkbox when modal opens/closes
  useEffect(() => { setConfirmed(false); }, [isOpen]);

  if (!isOpen || !appointment) return null;
  
  // Calculate totals
  const rawSubtotal = (paymentAmount && !Number.isNaN(parseFloat(paymentAmount))) ? parseFloat(paymentAmount) : (Number(appointment.service?.price) || 0);
  const discountObj = calculateDiscount(rawSubtotal) || { discount: 0, discountType: '' };
  const discountVal = Number(discountObj.discount) || 0;
  const totalVal = rawSubtotal - discountVal;
  const servicePrice = Number(appointment.service?.price) || 0;

  // Phase 4 #13: Detect price mismatch
  const amountPlusDiscount = rawSubtotal + discountVal;
  const shortfall = servicePrice - amountPlusDiscount;
  const isOverpayment = amountPlusDiscount > servicePrice;
  const isUnderpayment = shortfall > 0.01 && paymentType !== 'partial' && paymentType !== 'in-kind';

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-amber-300/40'} border rounded-lg shadow-2xl w-full max-w-md transform animate-scaleIn`}>
        {/* Header */}
        <div className={`px-6 py-4 border-b ${isDarkMode ? 'border-amber-500/20 bg-gradient-to-r from-gray-800 to-gray-900' : 'border-amber-200 bg-gradient-to-r from-amber-50 to-white'}`}>
          <div className="flex items-center justify-between">
            <h3 className={`text-lg font-bold ${isDarkMode ? 'text-amber-400' : 'text-amber-700'}`}>Complete Appointment</h3>
            <CheckCircleIcon className={`h-8 w-8 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
          </div>
        </div>

        {/* Content */}
        <div className="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
          {/* Client Info */}
          <div>
            <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-2`}>Client</p>
            <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{appointment.user?.first_name} {appointment.user?.last_name}</p>
            <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{appointment.user?.email}</p>
          </div>

          {/* Appointment Details */}
          <div className={`grid grid-cols-2 gap-4 pt-2 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
            <div>
              <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-1`}>Service</p>
              <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{appointment.service?.name || 'N/A'}</p>
            </div>
            <div>
              <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-1`}>Date</p>
              <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{formatDateDisplay(appointment.appointment_date)}</p>
            </div>
          </div>

          <div>
            <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-1`}>Time</p>
            <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{appointment.start_time} - {appointment.end_time}</p>
          </div>

          {/* Payment Summary */}
          <div className={`pt-2 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
            <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-2`}>Payment Details</p>
            <div className={`${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'} rounded p-3 space-y-2 text-sm`}>
              <div className="flex justify-between">
                <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Payment Type:</span>
                <span className={`${isDarkMode ? 'text-amber-50' : 'text-gray-900'} font-medium capitalize`}>{paymentType === 'in-kind' ? 'In-kind' : paymentType}</span>
              </div>
              {paymentType !== 'in-kind' && (
                <>
                  <div className="flex justify-between">
                    <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Amount:</span>
                    <span className={`${isDarkMode ? 'text-amber-50' : 'text-gray-900'} font-medium`}>₱{rawSubtotal.toFixed(2)}</span>
                  </div>
                  {discountVal > 0 && (
                    <div className="flex justify-between text-green-500">
                      <span>Discount:</span>
                      <span className="font-medium">-₱{discountVal.toFixed(2)}</span>
                    </div>
                  )}
                  <div className={`flex justify-between pt-2 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} font-semibold`}>
                    <span className={isDarkMode ? 'text-gray-300' : 'text-gray-700'}>Total:</span>
                    <span className="text-green-500">₱{totalVal.toFixed(2)}</span>
                  </div>
                </>
              )}
              {paymentType === 'in-kind' && inKindDescription && (
                <div className={`text-xs pt-2 border-t ${isDarkMode ? 'border-gray-700 text-gray-300' : 'border-gray-200 text-gray-600'}`}>
                  <p className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Description:</p>
                  <p className={isDarkMode ? 'text-amber-50' : 'text-gray-900'}>{inKindDescription}</p>
                  {inKindEstimatedValue && (
                    <div className="mt-1">
                      <p className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>Estimated Value:</p>
                      <p className={isDarkMode ? 'text-amber-50' : 'text-gray-900'}>₱{parseFloat(inKindEstimatedValue).toFixed(2)}</p>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Price mismatch warnings (#13) */}
        {paymentType !== 'in-kind' && (isUnderpayment || isOverpayment) && (
          <div className={`mx-6 mb-2 p-3 rounded text-xs ${isUnderpayment ? (isDarkMode ? 'bg-amber-900/30 border border-amber-500/30 text-amber-300' : 'bg-amber-50 border border-amber-300 text-amber-800') : (isDarkMode ? 'bg-blue-900/30 border border-blue-500/30 text-blue-300' : 'bg-blue-50 border border-blue-300 text-blue-800')}`}>
            <p className="font-semibold mb-1">{isUnderpayment ? '⚠ Underpayment Detected' : 'ℹ Overpayment Detected'}</p>
            <div className="space-y-0.5">
              <p>Service Price: ₱{servicePrice.toFixed(2)}</p>
              <p>Payment + Discount: ₱{amountPlusDiscount.toFixed(2)}</p>
              {isUnderpayment && <p className="font-medium">Shortfall: ₱{shortfall.toFixed(2)} — consider marking as partial payment</p>}
              {isOverpayment && <p className="font-medium">Excess: ₱{(amountPlusDiscount - servicePrice).toFixed(2)}</p>}
            </div>
          </div>
        )}

        {/* Confirmation checkbox (#12) */}
        <div className={`mx-6 mb-2`}>
          <label className={`flex items-start gap-2 cursor-pointer select-none text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
            <input
              type="checkbox"
              checked={confirmed}
              onChange={(e) => setConfirmed(e.target.checked)}
              className="mt-0.5 h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
            />
            <span>I confirm this payment is correct and verified</span>
          </label>
        </div>

        {/* Footer */}
        <div className={`px-6 py-4 border-t ${isDarkMode ? 'border-amber-500/20 bg-gray-800/50' : 'border-amber-200 bg-gray-50'} flex gap-3`}>
          <button
            onClick={onClose}
            disabled={loading}
            className={`flex-1 px-4 py-2 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed`}
          >
            Cancel
          </button>
          <button
            onClick={onConfirm}
            disabled={loading || !confirmed}
            className="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            {loading ? (
              <>
                <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                Processing
              </>
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

  const metadataEntries = getActionLogMetadataEntries(logData.metadata);

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

          <div>
            <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-1`}>Status</p>
            <span className={`inline-block px-2 py-1 rounded-full text-xs font-semibold ${getActionLogStatusClasses(logData.status, isDarkMode)}`}>
              {formatActionLogStatus(logData.status)}
            </span>
          </div>

          {/* Description */}
          <div>
            <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-1`}>Description</p>
            <p className={`text-sm ${isDarkMode ? 'text-gray-300 bg-gray-800/50' : 'text-gray-600 bg-gray-100'} rounded p-3`}>
              {logData.description || 'No description provided.'}
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

          {metadataEntries.length > 0 && (
            <div className={`pt-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
              <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-2`}>Additional Details</p>
              <div className="space-y-2">
                {metadataEntries.map((entry) => (
                  <div key={entry.key} className={`rounded-lg px-3 py-2 ${isDarkMode ? 'bg-gray-800/60' : 'bg-gray-100'}`}>
                    <p className={`text-[11px] font-medium ${isDarkMode ? 'text-gray-500' : 'text-gray-500'} uppercase tracking-wide mb-1`}>
                      {entry.label}
                    </p>
                    <p className={`text-sm break-words ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                      {entry.value}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          )}

          {(logData.ip_address || logData.user_agent) && (
            <div className={`pt-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
              <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-2`}>Request Source</p>
              <div className="space-y-2">
                {logData.ip_address && (
                  <div>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>IP Address</p>
                    <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{logData.ip_address}</p>
                  </div>
                )}
                {logData.user_agent && (
                  <div>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>User Agent</p>
                    <p className={`text-sm break-all ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{logData.user_agent}</p>
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
    // Use textContent and DOM cloning to prevent XSS from user-supplied data
    const sanitizedContent = content.cloneNode(true);
    sanitizedContent.querySelectorAll('script').forEach(el => el.remove());
    win.document.write(sanitizedContent.innerHTML);
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
              <p className="text-gray-900 text-sm">{formatDateDisplay(receiptData.date)}</p>
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
              <span className="text-gray-900">{formatDateDisplay(receiptData.appointmentDate)}</span>
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

          {/* Integrity Hash */}
          {receiptData.integrityHash && (
            <div className="text-center mt-3 mb-3 px-4 py-2 bg-gray-100 rounded">
              <p className="text-[9px] text-gray-400 uppercase tracking-wider">Verification</p>
              <p className="text-[10px] text-gray-500 font-mono break-all">{receiptData.integrityHash}</p>
            </div>
          )}

          {/* Receipt ID */}
          {receiptData.receiptId && (
            <div className="text-center mb-2">
              <p className="text-xs text-gray-500 font-medium">{receiptData.receiptId}</p>
            </div>
          )}

          {/* Footer */}
          <div className="text-center pt-4 border-t border-gray-200">
            <p className="text-xs text-gray-500">Thank you for your business</p>
            <p className="text-xs text-gray-400 mt-1">Please keep this receipt for your records</p>
          </div>
        </div>

        {/* Action Buttons */}
        <div className={`p-4 border-t ${isDarkMode ? 'border-amber-500/20 bg-gray-800' : 'border-amber-200 bg-gray-50'} flex gap-2 no-print`}>
          <button 
            onClick={handlePrint} 
            className={`flex-1 px-3 py-2 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-gray-100' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded text-sm flex items-center justify-center gap-2 transition-colors duration-200`}
          >
            <PrinterIcon className="h-4 w-4" />
            Print
          </button>
          <button 
            onClick={handleSavePdf} 
            className={`flex-1 px-3 py-2 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-gray-100' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded text-sm flex items-center justify-center gap-2 transition-colors duration-200`}
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

// Payment Waiting Modal - shown while waiting for PayMongo online payment completion
const PaymentWaitingModal = ({ isOpen, onClose, onCancel, appointment, checkoutUrl, paymentStatus, isDarkMode = true }) => {
  if (!isOpen || !appointment) return null;

  const statusMessages = {
    active: 'Waiting for client to complete payment...',
    processing: 'Processing payment...',
    paid: 'Payment completed successfully!',
    expired: 'Checkout session has expired.',
    error: 'An error occurred while checking payment status.',
  };

  const isPaid = paymentStatus === 'paid';
  const isExpired = paymentStatus === 'expired';
  const isError = paymentStatus === 'error';

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-amber-300/40'} border rounded-xl shadow-2xl w-full max-w-md transform animate-scaleIn`}>
        {/* Header */}
        <div className={`px-6 py-4 border-b ${isDarkMode ? 'border-amber-500/20 bg-gradient-to-r from-gray-800 to-gray-900' : 'border-amber-200 bg-gradient-to-r from-amber-50 to-white'} rounded-t-xl`}>
          <div className="flex items-center gap-3">
            <div className={`w-10 h-10 rounded-full flex items-center justify-center ${isPaid ? 'bg-green-500/20' : isExpired || isError ? 'bg-red-500/20' : 'bg-amber-500/20'}`}>
              {isPaid ? (
                <CheckCircleIcon className="h-6 w-6 text-green-400" />
              ) : isExpired || isError ? (
                <XCircleIcon className="h-6 w-6 text-red-400" />
              ) : (
                <div className="w-5 h-5 border-2 border-amber-400 border-t-transparent rounded-full animate-spin" />
              )}
            </div>
            <div>
              <h3 className={`text-lg font-bold ${isDarkMode ? 'text-amber-400' : 'text-amber-700'}`}>Online Payment</h3>
              <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                {appointment.user?.first_name} {appointment.user?.last_name} • {appointment.service?.name}
              </p>
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="p-6 space-y-4">
          {/* Status Message */}
          <div className={`text-center py-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
            <p className="text-sm font-medium">{statusMessages[paymentStatus] || statusMessages.active}</p>
            {!isPaid && !isExpired && !isError && (
              <p className={`text-xs mt-2 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                The client can pay via Card, GCash, GrabPay, or Maya
              </p>
            )}
          </div>

          {/* Checkout Link */}
          {checkoutUrl && !isPaid && !isExpired && (
            <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border rounded-lg p-4`}>
              <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} uppercase tracking-wide mb-2`}>Checkout Link</p>
              <div className="flex gap-2">
                <button
                  onClick={() => window.open(checkoutUrl, '_blank')}
                  className="flex-1 px-3 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded text-sm font-medium transition-colors flex items-center justify-center gap-2"
                >
                  💳 Open Checkout Page
                </button>
                <button
                  onClick={() => {
                    navigator.clipboard.writeText(checkoutUrl);
                    if (window?.showToast) window.showToast('Copied', 'Checkout link copied to clipboard', 'success');
                  }}
                  className={`px-3 py-2 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded text-sm transition-colors`}
                >
                  📋 Copy
                </button>
              </div>
              <p className={`text-xs mt-2 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                Share this link with the client or open it on their device
              </p>
            </div>
          )}

          {/* Progress indicator */}
          {!isPaid && !isExpired && !isError && (
            <div className="flex items-center justify-center gap-3">
              <div className="flex gap-1">
                <div className="w-2 h-2 rounded-full bg-amber-500 animate-bounce" style={{ animationDelay: '0ms' }} />
                <div className="w-2 h-2 rounded-full bg-amber-500 animate-bounce" style={{ animationDelay: '150ms' }} />
                <div className="w-2 h-2 rounded-full bg-amber-500 animate-bounce" style={{ animationDelay: '300ms' }} />
              </div>
              <span className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Checking every 5 seconds...</span>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className={`px-6 py-4 border-t ${isDarkMode ? 'border-amber-500/20 bg-gray-800/50' : 'border-amber-200 bg-gray-50'} rounded-b-xl flex gap-3`}>
          {isPaid ? (
            <button
              onClick={onClose}
              className="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-sm font-medium transition-colors"
            >
              ✓ View Receipt
            </button>
          ) : (
            <button
              onClick={onCancel}
              className={`flex-1 px-4 py-2 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded text-sm font-medium transition-colors`}
            >
              Cancel Payment
            </button>
          )}
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
  inKindDescription, setInKindDescription, inKindEstimatedValue, setInKindEstimatedValue,
  selectedDiscounts, setSelectedDiscounts,
  discountRates,
  calculateDiscount, onComplete, onRequestRefund, onMarkNoShow, isDarkMode = true
}) => {
  if (!isOpen || !appointment) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-60 flex items-start justify-center z-50 p-6">
      <div className={`${isDarkMode ? 'bg-gray-900 border-gray-800' : 'bg-white border-gray-200'} border rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto`}>
        <div className={`flex items-center justify-between p-4 border-b ${isDarkMode ? 'border-gray-800' : 'border-gray-200'}`}>
          <div>
            <h3 className={`text-xl font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Appointment Details</h3>
            <p className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-500'} mt-0.5`}>Reference #{appointment.id} • {appointment.service?.name || 'Service'}</p>
          </div>
          <div>
            <button onClick={onClose} className={`${isDarkMode ? 'text-gray-400 hover:text-amber-300' : 'text-gray-500 hover:text-amber-600'} p-2 rounded focus:outline-none`}>
              <XMarkIcon className="h-5 w-5" />
            </button>
          </div>
        </div>

        <div className="p-4 space-y-4">
          <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border rounded-lg p-3`}>
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Client</p>
                <p className={`text-lg font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{appointment.user?.first_name} {appointment.user?.last_name}</p>
                <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>{appointment.user?.email}</p>
                <p className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>{appointment.user?.phone || 'N/A'}</p>
              </div>
              <div className="text-right">
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Date</p>
                <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{formatDateDisplay(appointment.appointment_date)}</p>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mt-2`}>Time</p>
                <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{appointment.start_time || '-'} {appointment.end_time ? `- ${appointment.end_time}` : ''}</p>
              </div>
            </div>
          </div>

          {!isViewOnly && (
            <>
              <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border rounded-lg p-3`}>
                <h4 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} mb-2`}>Payment</h4>

                {/* Partial payment balance info */}
                {appointment.payment_status === 'partially_paid' && (
                  <div className={`mb-3 p-2 rounded text-xs ${isDarkMode ? 'bg-orange-500/10 border border-orange-500/30 text-orange-300' : 'bg-orange-50 border border-orange-200 text-orange-700'}`}>
                    <div className="flex justify-between">
                      <span>Service Price:</span>
                      <span className="font-semibold">{formatPrice(appointment.service?.price)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span>Paid So Far:</span>
                      <span className="font-semibold">{formatPrice(appointment.payment_amount)}</span>
                    </div>
                    <div className="flex justify-between font-bold mt-1 pt-1 border-t border-current/20">
                      <span>Remaining:</span>
                      <span>{formatPrice(appointment.balance_remaining)}</span>
                    </div>
                  </div>
                )}
                <div className="flex flex-wrap gap-3 mb-3">
                  <label className={`flex items-center gap-2 text-sm ${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>
                    <input type="radio" name={`paytype-modal-${appointment.id}`} value="cash" checked={paymentType === 'cash'} onChange={() => setPaymentType('cash')} className="mr-1 accent-amber-500" />
                    <span>Cash</span>
                  </label>
                  <label className={`flex items-center gap-2 text-sm ${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>
                    <input type="radio" name={`paytype-modal-${appointment.id}`} value="partial" checked={paymentType === 'partial'} onChange={() => setPaymentType('partial')} className="mr-1 accent-amber-500" />
                    <span>Partial</span>
                  </label>
                  <label className={`flex items-center gap-2 text-sm ${isDarkMode ? 'text-gray-200' : 'text-gray-700'}`}>
                    <input type="radio" name={`paytype-modal-${appointment.id}`} value="in-kind" checked={paymentType === 'in-kind'} onChange={() => setPaymentType('in-kind')} className="mr-1 accent-amber-500" />
                    <span>In-kind</span>
                  </label>
                  <label className={`flex items-center gap-2 text-sm px-3 py-1 rounded-lg border transition-colors ${appointment.payment_status === 'partially_paid' || Number(appointment.payment_amount || 0) > 0 ? (isDarkMode ? 'text-gray-500 border-gray-800 cursor-not-allowed opacity-60' : 'text-gray-400 border-gray-200 cursor-not-allowed opacity-70') : paymentType === 'online' ? (isDarkMode ? 'bg-amber-500/20 border-amber-500/40 text-amber-300 font-semibold' : 'bg-amber-50 border-amber-400 text-amber-700 font-semibold') : (isDarkMode ? 'text-gray-200 border-gray-700 hover:border-amber-500/30' : 'text-gray-700 border-gray-300 hover:border-amber-400')}`}>
                    <input type="radio" name={`paytype-modal-${appointment.id}`} value="online" checked={paymentType === 'online'} onChange={() => setPaymentType('online')} disabled={appointment.payment_status === 'partially_paid' || Number(appointment.payment_amount || 0) > 0} className="mr-1 accent-amber-500" />
                    <span>💳 Online</span>
                  </label>
                </div>

                {(appointment.payment_status === 'partially_paid' || Number(appointment.payment_amount || 0) > 0) && (
                  <div className={`mb-2 text-xs px-3 py-2 rounded ${isDarkMode ? 'bg-gray-800 text-gray-400 border border-gray-700' : 'bg-gray-50 text-gray-600 border border-gray-200'}`}>
                    Online checkout is disabled for appointments with recorded installments. Use the remaining manual payment flow instead.
                  </div>
                )}

                {paymentType === 'online' && (
                  <div className={`mb-2 text-xs px-3 py-2 rounded ${isDarkMode ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-amber-50 text-amber-700 border border-amber-200'}`}>
                    💡 Client will pay via Card, GCash, GrabPay, or Maya through a secure PayMongo checkout page.
                  </div>
                )}

                {paymentType !== 'in-kind' ? (
                  <input type="number" step="0.01" value={paymentAmount} onChange={(e) => setPaymentAmount(e.target.value)} placeholder={paymentType === 'online' ? 'Enter amount to charge' : 'Enter payment amount'} className={`w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-900 border-gray-700 text-white placeholder-gray-500' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'}`} />
                ) : (
                  <div className="space-y-2">
                    <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-300' : 'text-amber-700'}`}>
                      Description of items received <span className="text-red-500">*</span>
                    </label>
                    <textarea value={inKindDescription} onChange={(e) => setInKindDescription(e.target.value)} rows={3} placeholder="Describe items received (e.g. 2kg rice, office supplies)" className={`w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${!inKindDescription.trim() ? 'border-red-500' : ''} ${isDarkMode ? 'bg-gray-900 border-gray-700 text-white placeholder-gray-500' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'}`}></textarea>
                    <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-300' : 'text-amber-700'}`}>
                      Estimated Value (₱) <span className="text-red-500">*</span>
                    </label>
                    <input type="number" step="0.01" min="0" value={inKindEstimatedValue} onChange={(e) => setInKindEstimatedValue(e.target.value)} placeholder="Enter estimated peso value" className={`w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${!inKindEstimatedValue ? 'border-red-500' : ''} ${isDarkMode ? 'bg-gray-900 border-gray-700 text-white placeholder-gray-500' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'}`} />
                    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Describe the items received and their estimated peso value.</p>
                  </div>
                )}

                <div className="mt-3 grid grid-cols-3 gap-2">
                  <label className={`flex items-center gap-2 text-sm px-2 py-2 border rounded cursor-pointer transition-colors ${selectedDiscounts.includes('pwd') ? (isDarkMode ? 'bg-amber-500/20 border-amber-500/40 text-amber-300' : 'bg-amber-50 border-amber-300 text-amber-700') : (isDarkMode ? 'text-gray-200 bg-gray-800 border-gray-700 hover:border-gray-600' : 'text-gray-700 bg-white border-gray-300 hover:border-gray-400')}`}>
                    <input type="checkbox" checked={selectedDiscounts.includes('pwd')} onChange={(e) => { setSelectedDiscounts(e.target.checked ? ['pwd'] : []); }} className="mr-1 accent-amber-500" />
                    <div className="leading-tight"><div className="font-medium">PWD</div><div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{discountRates.pwd ? `${discountRates.pwd.percentage}%` : '—'}</div></div>
                  </label>
                  <label className={`flex items-center gap-2 text-sm px-2 py-2 border rounded cursor-pointer transition-colors ${selectedDiscounts.includes('senior') ? (isDarkMode ? 'bg-amber-500/20 border-amber-500/40 text-amber-300' : 'bg-amber-50 border-amber-300 text-amber-700') : (isDarkMode ? 'text-gray-200 bg-gray-800 border-gray-700 hover:border-gray-600' : 'text-gray-700 bg-white border-gray-300 hover:border-gray-400')}`}>
                    <input type="checkbox" checked={selectedDiscounts.includes('senior')} onChange={(e) => { setSelectedDiscounts(e.target.checked ? ['senior'] : []); }} className="mr-1 accent-amber-500" />
                    <div className="leading-tight"><div className="font-medium">Senior</div><div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{discountRates.senior ? `${discountRates.senior.percentage}%` : '—'}</div></div>
                  </label>
                  <label className={`flex items-center gap-2 text-sm px-2 py-2 border rounded cursor-pointer transition-colors ${selectedDiscounts.includes('student') ? (isDarkMode ? 'bg-amber-500/20 border-amber-500/40 text-amber-300' : 'bg-amber-50 border-amber-300 text-amber-700') : (isDarkMode ? 'text-gray-200 bg-gray-800 border-gray-700 hover:border-gray-600' : 'text-gray-700 bg-white border-gray-300 hover:border-gray-400')}`}>
                    <input type="checkbox" checked={selectedDiscounts.includes('student')} onChange={(e) => { setSelectedDiscounts(e.target.checked ? ['student'] : []); }} className="mr-1 accent-amber-500" />
                    <div className="leading-tight"><div className="font-medium">Student</div><div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{discountRates.student ? `${discountRates.student.percentage}%` : '—'}</div></div>
                  </label>
                </div>
              </div>

              <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border rounded-lg p-3`}>
                <div className="flex items-center justify-between mb-2">
                  <div>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Service</p>
                    <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{appointment.service?.name || ''}</p>
                  </div>
                  <div className="text-right">
                    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Price</p>
                    <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>₱{(Number(appointment.service?.price) || 0).toFixed(2)}</p>
                  </div>
                </div>

                <div className={`border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} pt-3`}>
                  {(() => {
                    const rawSubtotal = (paymentAmount && !Number.isNaN(parseFloat(paymentAmount))) ? parseFloat(paymentAmount) : (Number(appointment.service?.price) || 0);
                    const discountObj = calculateDiscount(rawSubtotal) || { discount: 0 };
                    const discountVal = Number(discountObj.discount) || 0;
                    const totalVal = rawSubtotal - discountVal;
                    return (
                      <>
                        <div className={`flex justify-between ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} text-sm`}><span>Subtotal</span><span className={`font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>₱{rawSubtotal.toFixed(2)}</span></div>
                        {selectedDiscounts.length > 0 && <div className="flex justify-between text-green-500 text-sm"><span>Discount</span><span>-₱{discountVal.toFixed(2)}</span></div>}
                        <div className={`flex justify-between font-semibold text-base border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} pt-2`}><span className={isDarkMode ? 'text-gray-200' : 'text-gray-700'}>Total</span><span className={isDarkMode ? 'text-amber-400' : 'text-amber-600'}>₱{totalVal.toFixed(2)}</span></div>
                      </>
                    );
                  })()}
                </div>

                <div className="mt-3 flex gap-2">
                  <button onClick={onComplete} className={`flex-1 px-3 py-2 ${paymentType === 'online' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-green-600 hover:bg-green-700'} text-white rounded text-sm font-medium transition-colors`}>{paymentType === 'online' ? '💳 Create Checkout' : 'Complete & Receipt'}</button>
                  {onMarkNoShow && (
                    <button onClick={() => { if (window.confirm(`Mark ${appointment.user?.first_name || 'client'} as No Show?`)) { onMarkNoShow(appointment); onClose(); } }} className={`px-3 py-2 rounded text-sm font-medium transition-colors ${isDarkMode ? 'bg-gray-700 hover:bg-red-600 text-gray-300 hover:text-white' : 'bg-gray-200 hover:bg-red-500 text-gray-600 hover:text-white'}`}>No Show</button>
                  )}
                  <button onClick={onClose} className={`px-3 py-2 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded text-sm transition-colors`}>Close</button>
                </div>
              </div>
            </>
          )}

          {isViewOnly && (
            <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border rounded-lg p-3`}>
              <div className="flex items-center justify-between mb-2">
                <div>
                  <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Service</p>
                  <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{appointment.service?.name || ''}</p>
                </div>
                <div className="text-right">
                  <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Price</p>
                  <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>₱{(Number(appointment.service?.price) || 0).toFixed(2)}</p>
                </div>
              </div>

              {/* Payment breakdown */}
              {appointment.payment_amount > 0 && (
                <div className={`border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} pt-2 mb-2 space-y-1`}>
                  <div className={`flex justify-between text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}><span>Amount Paid</span><span className={`font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>₱{Number(appointment.payment_amount || 0).toFixed(2)}</span></div>
                  {appointment.discount_amount > 0 && <div className={`flex justify-between text-sm text-green-500`}><span>Discount ({appointment.discount_type || 'N/A'})</span><span>-₱{Number(appointment.discount_amount || 0).toFixed(2)}</span></div>}
                  {appointment.payment_type && <div className={`flex justify-between text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}><span>Payment Type</span><span className="capitalize">{appointment.payment_type}</span></div>}
                  {appointment.payment_date && <div className={`flex justify-between text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}><span>Payment Date</span><span>{formatDateDisplay(appointment.payment_date)}</span></div>}
                </div>
              )}

              <div className={`border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} pt-3 mb-3`}>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-2`}>Status</p>
                <p className="text-sm text-green-500 font-semibold">✓ Completed</p>
              </div>

              {/* Refund status display */}
              {appointment.active_refund && (
                <div className={`border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-200'} pt-3 mb-3`}>
                  <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-2`}>Refund Status</p>
                  <div className="flex items-center gap-2">
                    <span className={`px-2 py-1 rounded-full text-xs font-semibold ${
                      appointment.active_refund.status === 'completed' ? (isDarkMode ? 'bg-green-500/20 text-green-400' : 'bg-green-100 text-green-700') :
                      appointment.active_refund.status === 'pending' ? (isDarkMode ? 'bg-yellow-500/20 text-yellow-400' : 'bg-yellow-100 text-yellow-700') :
                      appointment.active_refund.status === 'approved' ? (isDarkMode ? 'bg-blue-500/20 text-blue-400' : 'bg-blue-100 text-blue-700') :
                      (isDarkMode ? 'bg-red-500/20 text-red-400' : 'bg-red-100 text-red-700')
                    }`}>{appointment.active_refund.status}</span>
                    <span className={`text-sm ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>₱{Number(appointment.active_refund.refund_amount || 0).toFixed(2)}</span>
                  </div>
                </div>
              )}

              <div className="flex gap-2">
                {/* Show Request Refund button only if paid and no active refund */}
                {appointment.payment_status === 'paid' && !appointment.active_refund && onRequestRefund && (
                  <button onClick={() => onRequestRefund(appointment)} className="flex-1 px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded text-sm font-medium transition-colors flex items-center justify-center gap-1">
                    <ArrowUturnLeftIcon className="h-4 w-4" /> Request Refund
                  </button>
                )}
                <button onClick={onClose} className={`${appointment.payment_status === 'paid' && !appointment.active_refund && onRequestRefund ? '' : 'w-full'} px-3 py-2 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded text-sm transition-colors`}>Close</button>
              </div>
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
          <button onClick={onClose} className={`px-4 py-2 ${isDarkMode ? 'bg-gray-800 hover:bg-gray-700 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded`}>Cancel</button>
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
  const { isDarkMode, toggleTheme } = useTheme();
  const { callApi, loading: apiLoading } = useApi();
  
  const [activeSection, setActiveSection] = useState('dashboard');
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  const [isCollapsedDesktop, setIsCollapsedDesktop] = useState(false);
  const [openDropdowns, setOpenDropdowns] = useState({ 'Operations': true, 'Communication': true, 'Reports & Analytics': true });
  const [showLogoutModal, setShowLogoutModal] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
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
  const [lastDashboardSync, setLastDashboardSync] = useState(null);
  const [cashierSyncStatus, setCashierSyncStatus] = useState(
    typeof navigator !== 'undefined' && !navigator.onLine ? 'offline' : 'syncing'
  );
  const [lastCashierSectionSync, setLastCashierSectionSync] = useState(null);
  
  // Appointments Data
  const [appointmentsTab, setAppointmentsTab] = useState('approved');
  const [appointmentSearch, setAppointmentSearch] = useState('');
  const [appointments, setAppointments] = useState([]);
  const [dashboardApprovedAppointments, setDashboardApprovedAppointments] = useState([]);
  const [appointmentsLoading, setAppointmentsLoading] = useState(false);
  const [dashboardAppointmentsLoading, setDashboardAppointmentsLoading] = useState(false);
  const [expandedAppointment, setExpandedAppointment] = useState(null);
  const [paymentAmount, setPaymentAmount] = useState('');
  const [paymentType, setPaymentType] = useState('cash');
  const [inKindDescription, setInKindDescription] = useState('');
  const [inKindEstimatedValue, setInKindEstimatedValue] = useState('');
  const [selectedDiscounts, setSelectedDiscounts] = useState([]);
  const [discountRates, setDiscountRates] = useState({});
  const [viewModalAppointment, setViewModalAppointment] = useState(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(5);
  const [isDense, setIsDense] = useState(false);
  const [totalPages, setTotalPages] = useState(1);
  const [totalAppointments, setTotalAppointments] = useState(0);
  const [showConfirmPaymentModal, setShowConfirmPaymentModal] = useState(false);
  const [confirmAppointment, setConfirmAppointment] = useState(null);
  const [showCompletionConfirmation, setShowCompletionConfirmation] = useState(false);
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

  // Password Change State
  const [passwordData, setPasswordData] = useState({ current_password: '', new_password: '', new_password_confirmation: '' });
  const [passwordErrors, setPasswordErrors] = useState({});
  const [passwordSuccess, setPasswordSuccess] = useState('');
  const [passwordSaving, setPasswordSaving] = useState(false);
  const [showPassword, setShowPassword] = useState({ current: false, new: false, confirm: false });

  // Refunds State
  const [refunds, setRefunds] = useState([]);
  const [refundsLoading, setRefundsLoading] = useState(false);
  const [refundReasons, setRefundReasons] = useState([]);
  const [sidebarCounts, setSidebarCounts] = useState({
    appointments: null,
    transactions: null,
    refunds: null,
  });

  // Cashier Refund Request State
  const [showRefundRequestModal, setShowRefundRequestModal] = useState(false);
  const [refundTargetAppointment, setRefundTargetAppointment] = useState(null);
  const [refundFormData, setRefundFormData] = useState({ refund_amount: '', reason: 'customer_request', description: '' });
  const [refundRequestLoading, setRefundRequestLoading] = useState(false);
  const [refundRequestError, setRefundRequestError] = useState('');

  // Online Payment State
  const [showPaymentWaitingModal, setShowPaymentWaitingModal] = useState(false);
  const [onlinePaymentStatus, setOnlinePaymentStatus] = useState('active');
  const [onlineCheckoutUrl, setOnlineCheckoutUrl] = useState('');
  const [onlinePaymentAppointment, setOnlinePaymentAppointment] = useState(null);
  const [onlinePaymentPolling, setOnlinePaymentPolling] = useState(null);
  const shiftRangeRef = useRef(shiftRange);
  const cashierSectionSyncInFlightRef = useRef(false);
  const cashierSectionSyncQueuedRef = useRef(false);
  const lastCashierSectionSyncAtRef = useRef(0);
  const previousActiveSectionRef = useRef(activeSection);
  const hasInitializedTimeframeRef = useRef(false);

  // Messages State
  const [messages, setMessages] = useState([]);
  const [messagesLoading, setMessagesLoading] = useState(false);
  const [messageUsers, setMessageUsers] = useState([]);
  const [selectedConversation, setSelectedConversation] = useState(null);
  const [conversationMessages, setConversationMessages] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [sendingMessage, setSendingMessage] = useState(false);

  // Notifications state for section view
  const [notificationsTab, setNotificationsTab] = useState('unread');
  const [notificationsList, setNotificationsList] = useState([]);
  const [notificationsLoading, setNotificationsLoading] = useState(false);

  // Safe toggle for expanding appointment entries (prevents unexpected crashes)
  const toggleExpanded = useCallback((id) => {
    try {
      setExpandedAppointment(prev => (prev === id ? null : id));
    } catch (err) {
      console.error('Error toggling expanded appointment:', err);
      if (window?.showToast) window.showToast('Error', 'An unexpected error occurred while opening the appointment. Check console for details.', 'error');
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
          badge: appointmentsLoading && sidebarCounts.appointments === null ? null : sidebarCounts.appointments
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
          badge: sidebarCounts.transactions
        },
        { 
          name: 'Refunds', 
          icon: ArrowUturnLeftIcon, 
          key: 'refunds',
          badge: sidebarCounts.refunds
        }
      ]
    },
    { 
      section: 'Communication',
      items: [
        { 
          name: 'Messages', 
          icon: ChatBubbleLeftRightIcon, 
          key: 'messages'
        },
        { 
          name: 'Notifications', 
          icon: BellIcon, 
          key: 'notifications'
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
    if (typeof navigator !== 'undefined' && !navigator.onLine) {
      setCashierSyncStatus('offline');
    } else {
      setCashierSyncStatus('syncing');
    }

    try {
      const response = await callApi((signal) =>
        axios.get(`/api/cashier/dashboard-stats?timeframe=${timeframe}`, { signal })
      , { abortPrevious: false });
      if (response && response.success) {
        // Handle both { success: true, stats, revenueTrend, salesByService }
        // and { data: { stats, revenueTrend, salesByService } } shapes
        const payload = response.data || response;

        const statsData = payload.stats || {};
        setStats({
          totalRevenue: statsData.totalRevenue || 0,
          totalSales: statsData.totalSales || 0,
          todayRevenue: statsData.todayRevenue || 0,
          todaySales: statsData.todaySales || 0
        });

        // Set revenue data from backend; normalize if monthly timeframe
        const rawTrend = payload.revenueTrend || [];
        const normalized = timeframe === 'monthly' ? normalizeMonthlyTrend(rawTrend) : rawTrend;
        setRevenueData(normalized);

        // Set sales by service from backend
        setSalesByService(payload.salesByService || []);
        setLastDashboardSync(new Date().toISOString());
        setCashierSyncStatus('live');
        return true;
      }

      setCashierSyncStatus(typeof navigator !== 'undefined' && !navigator.onLine ? 'offline' : 'reconnecting');
      return false;
    } catch (error) {
      console.error('Error loading dashboard data:', error);
      setCashierSyncStatus(typeof navigator !== 'undefined' && !navigator.onLine ? 'offline' : 'reconnecting');
      return false;
    }
  }, [callApi, timeframe]);

  const loadDashboardApprovedAppointments = useCallback(async ({ silent = false } = {}) => {
    if (!silent) {
      setDashboardAppointmentsLoading(true);
    }

    try {
      const response = await callApi((signal) =>
        axios.get('/api/cashier/appointments/approved', {
          signal,
          params: { page: 1, per_page: 100 },
        })
      , { abortPrevious: false });

      if (response && response.success) {
        const payload = response.data || {};
        const list = extractCollectionPayload(payload);
        const total = payload.total || payload.data?.total || list.length || 0;

        setDashboardApprovedAppointments(list);
        setSidebarCounts((prev) => ({
          ...prev,
          appointments: total || 0,
        }));
      }
    } catch (error) {
      console.error('Error loading dashboard appointments preview:', error);
    } finally {
      if (!silent) {
        setDashboardAppointmentsLoading(false);
      }
    }
  }, [callApi]);

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
        const list = extractCollectionPayload(payload);

        setAppointments(list || []);

        // totals / pagination values may live at different places
        const total = payload.total || (payload.data && payload.data.total) || (payload.data && payload.data.total) || (Array.isArray(list) ? list.length : 0) || 0;
        const lastPage = payload.last_page || (payload.data && payload.data.last_page) || Math.max(1, Math.ceil((total || 0) / perPage));
        setTotalAppointments(total || 0);
        setTotalPages(lastPage || 1);
        setSidebarCounts((prev) => ({
          ...prev,
          appointments: total || 0,
        }));
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
      const params = { month: m, year: y, status: 'approved,pending' };
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

        const normalizedAppts = flatAppts.map((apt) => ({
          ...apt,
          status: String(apt?.status || '').trim().toLowerCase(),
        }));

        const visibleAppts = normalizedAppts.filter((apt) => ['approved', 'pending'].includes(apt.status));
        setCalendarAppointments(visibleAppts);
        
        // Calculate monthly summary
        const summary = {
          totalPending: visibleAppts.filter(a => a.status === 'pending').length,
          totalApproved: visibleAppts.filter(a => a.status === 'approved').length,
          totalAppointments: visibleAppts.length,
          expectedRevenue: visibleAppts.reduce((sum, a) => sum + (Number(a.payment_amount) || Number(a.service?.price) || 0), 0),
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

  // Fetch discount rates from database on mount
  useEffect(() => {
    const fetchDiscountRates = async () => {
      try {
        const response = await axios.get('/api/cashier/discount-rates');
        if (response.data?.success && response.data.rates) {
          const ratesMap = {};
          response.data.rates.forEach(r => {
            // Map DB types to frontend keys
            const keyMap = { pwd: 'pwd', senior_citizen: 'senior', student: 'student' };
            const frontendKey = keyMap[r.type] || r.type;
            ratesMap[frontendKey] = { percentage: r.percentage, description: r.description, dbType: r.type };
          });
          setDiscountRates(ratesMap);
        }
      } catch (err) {
        console.error('Failed to fetch discount rates:', err);
      }
    };
    fetchDiscountRates();
  }, []);

  // Phase 4 #8: Auto-fill payment amount when appointment modal opens
  useEffect(() => {
    if (viewModalAppointment && !viewModalAppointment._viewOnly) {
      // If partially paid, pre-fill with remaining balance; otherwise use service price
      if (viewModalAppointment.payment_status === 'partially_paid' && viewModalAppointment.balance_remaining > 0) {
        setPaymentAmount(String(viewModalAppointment.balance_remaining));
      } else {
        const price = viewModalAppointment.service?.price;
        if (price) setPaymentAmount(String(price));
      }
    }
  }, [viewModalAppointment]);

  // Load action logs (silent = true for background polling to avoid loading spinner flicker)
  const loadActionLogs = useCallback(async (silent = false) => {
    if (!silent) {
      setLoading(true);
      setActionLogs([]);
      setTotalLogs(0);
      setTotalLogPages(1);
    }
    try {
      // Ensure we pass the correct type parameter with pagination
      const url = `/api/cashier/action-logs?type=${logsTab}&page=${logsPage}&per_page=${logsPerPage}`;
      
      const response = await callApi((signal) =>
        axios.get(url, { signal }),
        { skipCache: true } // Skip cache to ensure fresh data for each tab
      );
      
      // Backend returns Laravel paginated response directly
      if (response && response.success && response.data) {
        const payload = response.data;
        // Extract data from paginated response
        const logs = Array.isArray(payload.data) ? payload.data : [];
        setActionLogs(logs);
        
        // Set pagination info
        setTotalLogs(payload.total || 0);
        setTotalLogPages(payload.last_page || 1);
      } else if (!silent) {
        setActionLogs([]);
        setTotalLogs(0);
        setTotalLogPages(1);
      }
    } catch (error) {
      console.error('Error loading action logs:', error);
      if (!silent) {
        setActionLogs([]);
        setTotalLogs(0);
        setTotalLogPages(1);
      }
      if (!silent && window?.showToast) window.showToast('Action Logs', 'Failed to load logs', 'error');
    } finally {
      if (!silent) {
        setLoading(false);
      }
    }
  }, [callApi, logsTab, logsPage, logsPerPage]);

  // Load section data - optimized with parallel loading where applicable
  // Track loaded sections to avoid redundant fetches
  const [cashierDataLoaded, setCashierDataLoaded] = useState({
    dashboard: false,
    appointments: false,
    calendar: false,
    'action-logs': false
  });

  // Load section data based on active section — lazy loading with tracking
  useEffect(() => {
    if (activeSection === 'dashboard') {
      if (!cashierDataLoaded.dashboard) {
        Promise.all([
          loadDashboardData(),
          loadDashboardApprovedAppointments(),
        ]).then(() => {
          setCashierDataLoaded(prev => ({ ...prev, dashboard: true }));
        });
      }
    } else if (activeSection === 'appointments') {
      if (!cashierDataLoaded.appointments) {
        loadAppointments().then(() => {
          setCashierDataLoaded(prev => ({ ...prev, appointments: true }));
        });
      }
    } else if (activeSection === 'calendar') {
      if (!cashierDataLoaded.calendar) {
        // Load current month data into state
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        
        loadCalendarAppointments(month + 1, year).then(() => {
          setCashierDataLoaded(prev => ({ ...prev, calendar: true }));
        });
        
        // Warm backend cache for adjacent months (don't set state — avoids overwriting current month)
        const prevMonth = new Date(year, month - 1, 1);
        const nextMonth = new Date(year, month + 1, 1);
        axios.get('/api/cashier/calendar/appointments', { params: { month: prevMonth.getMonth() + 1, year: prevMonth.getFullYear() } }).catch(() => {});
        axios.get('/api/cashier/calendar/appointments', { params: { month: nextMonth.getMonth() + 1, year: nextMonth.getFullYear() } }).catch(() => {});
      }
    } else if (activeSection === 'action-logs') {
      loadActionLogs();
    }
  }, [activeSection, currentMonth, logsTab, loadActionLogs, loadAppointments, loadCalendarAppointments, loadDashboardApprovedAppointments, loadDashboardData, cashierDataLoaded]);

  // Reset pagination when logs tab changes
  useEffect(() => {
    setLogsPage(1);
  }, [logsTab]);

  // Load calendar appointments when month changes - preload adjacent months for smooth navigation
  useEffect(() => {
    if (activeSection === 'calendar') {
      const year = currentMonth.getFullYear();
      const month = currentMonth.getMonth();
      
      // Load current month into state
      loadCalendarAppointments(month + 1, year);
      
      // Warm backend cache for adjacent months (silent, no state update)
      const prevMonth = new Date(year, month - 1, 1);
      const nextMonth = new Date(year, month + 1, 1);
      axios.get('/api/cashier/calendar/appointments', { params: { month: prevMonth.getMonth() + 1, year: prevMonth.getFullYear() } }).catch(() => {});
      axios.get('/api/cashier/calendar/appointments', { params: { month: nextMonth.getMonth() + 1, year: nextMonth.getFullYear() } }).catch(() => {});
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
  // Reload action logs when tab changes
  useEffect(() => {
    if (activeSection === 'action-logs') {
      loadActionLogs();
    }
  }, [logsTab, activeSection, loadActionLogs]);

  // Load transactions (sales history)
  const loadTransactions = useCallback(async (page = txPage, { silent = false } = {}) => {
    if (!silent) {
      setTxLoading(true);
    }

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
        setSidebarCounts((prev) => ({
          ...prev,
          transactions: total || 0,
        }));
      }
    } catch (err) {
      console.error('Error loading transactions:', err);
      if (window?.showToast) window.showToast('Transactions', 'Failed to load transactions', 'error');
    } finally {
      if (!silent) {
        setTxLoading(false);
      }
    }
  }, [txFilters, txPerPage, txSearch, txPage]);

  useEffect(() => {
    let isCancelled = false;

    const loadSidebarCounts = async () => {
      try {
        const [approvedRes, completedRes, refundsRes] = await Promise.all([
          axios.get('/api/cashier/appointments/approved', { params: { page: 1, per_page: 1 } }).catch(() => ({ data: { total: 0, data: [] } })),
          axios.get('/api/cashier/appointments/completed', { params: { page: 1, per_page: 1 } }).catch(() => ({ data: { total: 0, data: [] } })),
          axios.get('/api/cashier/refunds').catch(() => ({ data: { data: [] } })),
        ]);

        if (isCancelled) {
          return;
        }

        const approvedTotal = approvedRes.data?.total || approvedRes.data?.data?.total || approvedRes.data?.data?.length || 0;
        const completedTotal = completedRes.data?.total || completedRes.data?.data?.total || completedRes.data?.data?.length || 0;
        const refundsList = refundsRes.data?.data || refundsRes.data || [];

        setSidebarCounts({
          appointments: approvedTotal,
          transactions: completedTotal,
          refunds: Array.isArray(refundsList) ? refundsList.length : 0,
        });
      } catch (error) {
        console.error('Error loading cashier sidebar counts:', error);
      }
    };

    void loadSidebarCounts();

    return () => {
      isCancelled = true;
    };
  }, []);

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

  // Password Change Handler
  const handlePasswordSubmit = useCallback(async () => {
    setPasswordErrors({});
    setPasswordSuccess('');
    if (!passwordData.current_password || !passwordData.new_password) {
      setPasswordErrors({ general: 'All fields are required' });
      return;
    }
    if (passwordData.new_password.length < 8) {
      setPasswordErrors({ general: 'New password must be at least 8 characters' });
      return;
    }
    if (passwordData.new_password !== passwordData.new_password_confirmation) {
      setPasswordErrors({ general: 'New password and confirmation do not match' });
      return;
    }
    setPasswordSaving(true);
    try {
      const response = await callApi((signal) =>
        axios.put('/api/cashier/password', passwordData, { signal })
      );
      if (response && response.success) {
        setPasswordData({ current_password: '', new_password: '', new_password_confirmation: '' });
        setPasswordSuccess('Password changed successfully!');
        setTimeout(() => setPasswordSuccess(''), 5000);
      } else {
        setPasswordErrors({ general: response?.message || 'Failed to change password' });
      }
    } catch (error) {
      const msg = error?.response?.data?.message || 'Failed to change password';
      setPasswordErrors({ general: msg });
    } finally {
      setPasswordSaving(false);
    }
  }, [passwordData, callApi]);

  // Load Refunds
  const loadRefunds = useCallback(async ({ silent = false } = {}) => {
    if (!silent) {
      setRefundsLoading(true);
    }

    try {
      const [refundsRes, reasonsRes] = await Promise.all([
        axios.get('/api/cashier/refunds').catch(() => ({ data: { data: [] } })),
        axios.get('/api/cashier/refund-reasons/active').catch(() => ({ data: { data: [] } }))
      ]);
      const refundsList = refundsRes.data?.data || refundsRes.data || [];
      setRefunds(refundsList);
      setRefundReasons(reasonsRes.data?.data || reasonsRes.data || []);
      setSidebarCounts((prev) => ({
        ...prev,
        refunds: Array.isArray(refundsList) ? refundsList.length : 0,
      }));
    } catch (err) {
      console.error('Error loading refunds:', err);
    } finally {
      if (!silent) {
        setRefundsLoading(false);
      }
    }
  }, []);

  // Open Refund Request Modal (cashier initiating a refund for a completed appointment)
  const openCashierRefundModal = useCallback((appointment) => {
    if (!appointment || appointment.payment_status !== 'paid') {
      if (window?.showToast) window.showToast('Refund', 'Only paid appointments can be refunded', 'warning');
      return;
    }
    if (!appointment.payment_amount || appointment.payment_amount <= 0) {
      if (window?.showToast) window.showToast('Refund', 'No payment amount recorded for this appointment', 'error');
      return;
    }
    setRefundTargetAppointment(appointment);
    setRefundFormData({
      refund_amount: appointment.payment_amount || '',
      reason: (refundReasons.length > 0 ? refundReasons[0].key : 'customer_request'),
      description: ''
    });
    setRefundRequestError('');
    setShowRefundRequestModal(true);
  }, [refundReasons]);

  // Submit Cashier Refund Request
  const handleCashierRefundRequest = useCallback(async (e) => {
    e.preventDefault();
    setRefundRequestError('');
    if (!refundTargetAppointment) return;

    const amount = parseFloat(refundFormData.refund_amount);
    if (!amount || amount <= 0) {
      setRefundRequestError('Please enter a valid refund amount');
      return;
    }
    if (amount > (refundTargetAppointment.payment_amount || 0)) {
      setRefundRequestError('Refund amount cannot exceed the payment amount');
      return;
    }

    setRefundRequestLoading(true);
    try {
      const response = await axios.post('/api/cashier/refunds/request', {
        appointment_id: refundTargetAppointment.id,
        refund_amount: amount,
        reason: refundFormData.reason,
        description: refundFormData.description
      });

      if (response.data?.success) {
        setShowRefundRequestModal(false);
        setRefundTargetAppointment(null);
        setRefundFormData({ refund_amount: '', reason: 'customer_request', description: '' });
        if (window?.showToast) window.showToast('Refund', 'Refund request submitted successfully', 'success');
        // Refresh refunds list and appointments
        loadRefunds();
        loadAppointments();
      } else {
        setRefundRequestError(response.data?.message || 'Failed to submit refund request');
      }
    } catch (err) {
      console.error('Cashier refund request error:', err);
      setRefundRequestError(err.response?.data?.message || 'Failed to submit refund request');
    } finally {
      setRefundRequestLoading(false);
    }
  }, [refundTargetAppointment, refundFormData, loadRefunds, loadAppointments]);

  // Load Messages (conversation list)
  const loadMessages = useCallback(async ({ silent = false } = {}) => {
    if (!silent) {
      setMessagesLoading(true);
    }

    try {
      // Cashier should only communicate with admins, not regular users
      const response = await axios.get('/api/messages/admin-contacts');
      const payload = response.data?.data || response.data || [];
      setMessages(Array.isArray(payload) ? payload : []);
    } catch (err) {
      console.error('Error loading messages:', err);
    } finally {
      if (!silent) {
        setMessagesLoading(false);
      }
    }
  }, []);

  // Load conversation with a specific user
  const loadConversation = useCallback(async (userId) => {
    try {
      const response = await axios.get(`/api/messages/conversation/user/${userId}`);
      const msgs = response.data?.data || response.data || [];
      setConversationMessages(Array.isArray(msgs) ? msgs : []);
    } catch (err) {
      console.error('Error loading conversation:', err);
    }
  }, []);

  // Send message
  const handleSendMessage = useCallback(async () => {
    if (!newMessage.trim() || !selectedConversation) return;
    setSendingMessage(true);
    try {
      await axios.post('/api/messages', {
        receiver_id: selectedConversation.id || selectedConversation.user_id,
        message: newMessage.trim()
      });
      setNewMessage('');
      // Reload conversation
      await loadConversation(selectedConversation.id || selectedConversation.user_id);
      loadMessages(); // Refresh sidebar list
    } catch (err) {
      console.error('Error sending message:', err);
      if (window?.showToast) window.showToast('Messages', 'Failed to send message', 'error');
    } finally {
      setSendingMessage(false);
    }
  }, [newMessage, selectedConversation, loadConversation, loadMessages]);

  // Load Notifications for full view
  const loadNotifications = useCallback(async ({ silent = false } = {}) => {
    if (!silent) {
      setNotificationsLoading(true);
    }

    try {
      const url = notificationsTab === 'unread' ? '/api/notifications/unread' : '/api/notifications';
      const response = await axios.get(url);
      const list = response.data?.data || response.data || [];
      setNotificationsList(Array.isArray(list) ? list : []);
    } catch (err) {
      console.error('Error loading notifications:', err);
    } finally {
      if (!silent) {
        setNotificationsLoading(false);
      }
    }
  }, [notificationsTab]);

  // Load section-specific data
  useEffect(() => {
    if (activeSection === 'refunds') loadRefunds();
  }, [activeSection, loadRefunds]);

  useEffect(() => {
    if (activeSection === 'messages') loadMessages();
  }, [activeSection, loadMessages]);

  useEffect(() => {
    if (activeSection === 'notifications') loadNotifications();
  }, [activeSection, notificationsTab, loadNotifications]);

  useEffect(() => {
    shiftRangeRef.current = shiftRange;
  }, [shiftRange]);

  // Shift reports / accounting exports
  const loadShiftReport = useCallback(async (from, to, { silent = false } = {}) => {
    if (!silent) {
      setShiftLoading(true);
    }

    try {
      const params = {
        from: from || shiftRangeRef.current.from,
        to: to || shiftRangeRef.current.to,
      };
      const response = await callApi((signal) => axios.get('/api/cashier/shift-reports', { signal, params }));
      if (response && response.success) {
        // Backend returns data directly in response, not nested under data key
        setShiftReportSummary(response || null);
      }
    } catch (err) {
      console.error('Error loading shift report:', err);
      if (window?.showToast) window.showToast('Shift Report', 'Failed to load shift report', 'error');
    } finally {
      if (!silent) {
        setShiftLoading(false);
      }
    }
  }, [callApi]);

  const reconnectCashierRealtime = useCallback(() => {
    const pusher = window?.Echo?.connector?.pusher;

    if (!pusher || typeof pusher.connect !== 'function') {
      return;
    }

    const connectionState = pusher.connection?.state;
    if (connectionState === 'connected' || connectionState === 'connecting') {
      return;
    }

    try {
      pusher.connect();
    } catch (error) {
      console.debug('Cashier realtime reconnect failed:', error);
    }
  }, []);

  const requestCashierSectionResync = useCallback(async ({ section = activeSection, force = false, reconnectRealtime = false } = {}) => {
    if (!CASHIER_SYNCABLE_SECTIONS.includes(section)) {
      return;
    }

    if (!force && typeof document !== 'undefined' && document.hidden) {
      return;
    }

    if (typeof navigator !== 'undefined' && !navigator.onLine) {
      setCashierSyncStatus('offline');
      return;
    }

    const now = Date.now();
    if (!force && now - lastCashierSectionSyncAtRef.current < CASHIER_SILENT_RESYNC_MIN_GAP_MS) {
      return;
    }

    if (reconnectRealtime) {
      reconnectCashierRealtime();
    }

    if (cashierSectionSyncInFlightRef.current) {
      cashierSectionSyncQueuedRef.current = true;
      return;
    }

    cashierSectionSyncInFlightRef.current = true;
    lastCashierSectionSyncAtRef.current = now;
    setCashierSyncStatus('syncing');

    try {
      if (section === 'dashboard') {
        await Promise.all([
          loadDashboardData(),
          loadDashboardApprovedAppointments({ silent: true }),
        ]);
      } else if (section === 'appointments') {
        await loadAppointments();
      } else if (section === 'calendar') {
        await Promise.all([
          loadAppointments(),
          loadCalendarAppointments(currentMonth.getMonth() + 1, currentMonth.getFullYear())
        ]);
      } else if (section === 'action-logs') {
        await loadActionLogs(true);
      } else if (section === 'refunds') {
        await loadRefunds({ silent: true });
      } else if (section === 'messages') {
        await loadMessages({ silent: true });
        const conversationId = selectedConversation?.id || selectedConversation?.user_id;
        if (conversationId) {
          await loadConversation(conversationId);
        }
      } else if (section === 'notifications') {
        await loadNotifications({ silent: true });
      } else if (section === 'transactions') {
        await loadTransactions(txPage, { silent: true });
      } else if (section === 'reports') {
        const today = new Date().toISOString().split('T')[0];
        const from = shiftRange.from || today;
        const to = shiftRange.to || from;
        await loadShiftReport(from, to, { silent: true });
      }

      setLastCashierSectionSync(new Date().toISOString());
      setCashierSyncStatus('live');
    } catch (error) {
      console.error('Cashier silent resync failed:', error);
      setCashierSyncStatus(typeof navigator !== 'undefined' && !navigator.onLine ? 'offline' : 'reconnecting');
    } finally {
      cashierSectionSyncInFlightRef.current = false;

      if (cashierSectionSyncQueuedRef.current) {
        cashierSectionSyncQueuedRef.current = false;
        window.setTimeout(() => {
          void requestCashierSectionResync({ force: true });
        }, 0);
      }
    }
  }, [activeSection, currentMonth, loadActionLogs, loadAppointments, loadCalendarAppointments, loadConversation, loadDashboardApprovedAppointments, loadDashboardData, loadMessages, loadNotifications, loadRefunds, loadShiftReport, loadTransactions, reconnectCashierRealtime, selectedConversation, shiftRange.from, shiftRange.to, txPage]);

  useEffect(() => {
    if (activeSection === 'reports') {
      // default: load today's report when opening
      const today = new Date().toISOString().split('T')[0];
      setShiftRange((current) => (
        current.from === today && current.to === today
          ? current
          : { from: today, to: today }
      ));
      loadShiftReport(today, today);
    }
  }, [activeSection, loadShiftReport]);

  useEffect(() => {
    const previousSection = previousActiveSectionRef.current;
    previousActiveSectionRef.current = activeSection;

    if (previousSection !== activeSection && CASHIER_SYNCABLE_SECTIONS.includes(activeSection)) {
      if (activeSection === 'reports') {
        return;
      }

      void requestCashierSectionResync({ force: true, reconnectRealtime: true });
    }
  }, [activeSection, requestCashierSectionResync]);

  useEffect(() => {
    if (activeSection !== 'dashboard') {
      return;
    }

    if (!hasInitializedTimeframeRef.current) {
      hasInitializedTimeframeRef.current = true;
      return;
    }

    void requestCashierSectionResync({ section: 'dashboard', force: true });
  }, [timeframe, activeSection, requestCashierSectionResync]);

  // Polling fallback for cashier dashboard data (appointments + stats + action logs)
  useEffect(() => {
    const connectionState = window?.Echo?.connector?.pusher?.connection?.state;
    const POLL_INTERVAL_MS = connectionState === 'connected' ? 45000 : 20000;
    const ACTION_LOGS_POLL_INTERVAL_MS = 30000;

    const id = setInterval(() => {
      if (typeof document !== 'undefined' && document.hidden) {
        return;
      }

      if (activeSection !== 'action-logs') {
        void requestCashierSectionResync();
      }
    }, POLL_INTERVAL_MS);

    const logsId = setInterval(() => {
      if (typeof document !== 'undefined' && document.hidden) {
        return;
      }

      if (activeSection === 'action-logs') {
        void requestCashierSectionResync({ section: 'action-logs' });
      }
    }, ACTION_LOGS_POLL_INTERVAL_MS);

    const handleLogout = () => { clearInterval(id); clearInterval(logsId); };
    window.addEventListener('auth:logout', handleLogout);

    return () => {
      clearInterval(id);
      clearInterval(logsId);
      window.removeEventListener('auth:logout', handleLogout);
    };
  }, [activeSection, requestCashierSectionResync]);

  useEffect(() => {
    const handleVisibilityChange = () => {
      if (!document.hidden) {
        void requestCashierSectionResync({ force: true, reconnectRealtime: true });
      }
    };

    const handleFocus = () => {
      if (typeof document === 'undefined' || !document.hidden) {
        void requestCashierSectionResync({ reconnectRealtime: true });
      }
    };

    const handlePageShow = () => {
      void requestCashierSectionResync({ force: true, reconnectRealtime: true });
    };

    const handleOnline = () => {
      setCashierSyncStatus('reconnecting');
      void requestCashierSectionResync({ force: true, reconnectRealtime: true });
    };

    const handleOffline = () => {
      setCashierSyncStatus('offline');
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('focus', handleFocus);
    window.addEventListener('pageshow', handlePageShow);
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    const connection = window?.Echo?.connector?.pusher?.connection;
    const handleConnectionStateChange = (states) => {
      if (states.current === 'connected') {
        setCashierSyncStatus('live');
        void requestCashierSectionResync({ force: true });
        return;
      }

      if (states.current === 'connecting') {
        setCashierSyncStatus('reconnecting');
        return;
      }

      if (states.current === 'unavailable' || states.current === 'disconnected' || states.current === 'failed') {
        setCashierSyncStatus(typeof navigator !== 'undefined' && !navigator.onLine ? 'offline' : 'reconnecting');
      }
    };

    if (connection && typeof connection.bind === 'function') {
      connection.bind('state_change', handleConnectionStateChange);
    }

    reconnectCashierRealtime();

    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('focus', handleFocus);
      window.removeEventListener('pageshow', handlePageShow);
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);

      if (connection && typeof connection.unbind === 'function') {
        connection.unbind('state_change', handleConnectionStateChange);
      }
    };
  }, [reconnectCashierRealtime, requestCashierSectionResync]);

  // Real-time subscription via Laravel Echo for cashier dashboard
  useEffect(() => {
    if (!window?.Echo || typeof window.Echo.channel !== 'function') return;

    const channel = window.Echo.channel('appointments');

    const handler = (payload) => {
      try {
        if (payload && (payload.appointment || payload.data || payload)) {
          const realtimeSections = ['dashboard', 'appointments', 'calendar', 'transactions', 'reports', 'refunds', 'action-logs'];

          if (realtimeSections.includes(activeSection)) {
            void requestCashierSectionResync({ section: activeSection, force: true });
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
  }, [activeSection, requestCashierSectionResync]);

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
    setIsLoggingOut(true);
    try {
      await logout();
      setShowLogoutModal(false);
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      setIsLoggingOut(false);
    }
  };

  // Filter appointments by status
  const filteredAppointments = useMemo(() => {
    const list = Array.isArray(appointments) ? appointments : [];
    const query = appointmentSearch.trim().toLowerCase();
    return list.filter(apt => {
      const status = (apt && apt.status) || '';
      const paymentStatus = (apt && apt.payment_status) || (apt && apt.payment_status === 0 ? 0 : apt?.payment_status) || '';
      let tabMatch = false;
      if (appointmentsTab === 'approved') {
        tabMatch = status === 'approved' && paymentStatus !== 'paid';
      } else if (appointmentsTab === 'completed') {
        tabMatch = paymentStatus === 'paid' || paymentStatus === 'refunded' || paymentStatus === 'partially_refunded' || status === 'completed';
      }
      if (!tabMatch) return false;

      // Phase 7 #7: Client-side search filter
      if (query) {
        const clientName = `${apt.user?.first_name || ''} ${apt.user?.last_name || ''}`.toLowerCase();
        const serviceName = (apt.service?.name || '').toLowerCase();
        const aptDate = (apt.appointment_date || '').toLowerCase();
        const aptId = String(apt.id || '');
        return clientName.includes(query) || serviceName.includes(query) || aptDate.includes(query) || aptId.includes(query);
      }
      return true;
    });
  }, [appointments, appointmentsTab, appointmentSearch]);

  // Filter calendar appointments based on active filters
  const filteredCalendarAppts = useMemo(() => {
    if (!Array.isArray(calendarAppointments)) return [];

    const hasActiveFilters = calendarFilters && Object.values(calendarFilters).some(Boolean);
    if (!hasActiveFilters) {
      return calendarAppointments.filter((appointment) => ['approved', 'pending'].includes(String(appointment?.status || '').trim().toLowerCase()));
    }

    return calendarAppointments.filter((appointment) => {
      const status = String(appointment?.status || '').trim().toLowerCase();

      if (calendarFilters.approved && status === 'approved') return true;
      if (calendarFilters.pending && status === 'pending') return true;
      return false;
    });
  }, [calendarAppointments, calendarFilters]);

  // Get today's approved appointments
  const todayAppointments = useMemo(() => {
    const _t = new Date();
    const today = `${_t.getFullYear()}-${String(_t.getMonth()+1).padStart(2,'0')}-${String(_t.getDate()).padStart(2,'0')}`;
    return filteredAppointments.filter(apt => {
      const aptDate = getDateOnly(apt.appointment_date);
      return aptDate === today && apt.status === 'approved';
    });
  }, [filteredAppointments]);

  const dashboardPendingAppointments = useMemo(() => {
    if (!Array.isArray(dashboardApprovedAppointments)) {
      return [];
    }

    return dashboardApprovedAppointments.filter((appointment) => (
      appointment?.status === 'approved' && appointment?.payment_status !== 'paid'
    ));
  }, [dashboardApprovedAppointments]);

  const dashboardTodayAppointments = useMemo(() => {
    const _t = new Date();
    const today = `${_t.getFullYear()}-${String(_t.getMonth()+1).padStart(2,'0')}-${String(_t.getDate()).padStart(2,'0')}`;

    return dashboardPendingAppointments.filter((appointment) => getDateOnly(appointment?.appointment_date) === today);
  }, [dashboardPendingAppointments]);

  // Calculate discount using rates from database
  const calculateDiscount = useCallback((amount) => {
    let discount = 0;
    let discountType = '';
    
    if (selectedDiscounts.includes('pwd') && discountRates.pwd) {
      discount = amount * (discountRates.pwd.percentage / 100);
      discountType = discountRates.pwd.dbType;
    } else if (selectedDiscounts.includes('senior') && discountRates.senior) {
      discount = amount * (discountRates.senior.percentage / 100);
      discountType = discountRates.senior.dbType;
    } else if (selectedDiscounts.includes('student') && discountRates.student) {
      discount = amount * (discountRates.student.percentage / 100);
      discountType = discountRates.student.dbType;
    }
    
    return { discount: Math.round(discount * 100) / 100, discountType };
  }, [selectedDiscounts, discountRates]);

  const refreshCashierRealtimeData = useCallback(async () => {
    const month = currentMonth.getMonth() + 1;
    const year = currentMonth.getFullYear();

    await Promise.allSettled([
      loadAppointments(),
      loadDashboardApprovedAppointments({ silent: true }),
      loadDashboardData(),
      loadCalendarAppointments(month, year),
      loadRefunds({ silent: true }),
      loadTransactions(1, { silent: true }),
      loadActionLogs(true),
    ]);
  }, [currentMonth, loadActionLogs, loadAppointments, loadCalendarAppointments, loadDashboardApprovedAppointments, loadDashboardData, loadRefunds, loadTransactions]);

  // Start online payment polling
  const startOnlinePaymentPolling = useCallback((appointmentId) => {
    // Clear any existing polling
    if (onlinePaymentPolling) {
      clearInterval(onlinePaymentPolling);
    }

    const pollId = setInterval(async () => {
      try {
        const response = await axios.get(`/api/cashier/paymongo/status/${appointmentId}`);
        const data = response.data;

        if (data.status === 'paid') {
          clearInterval(pollId);
          setOnlinePaymentPolling(null);
          setOnlinePaymentStatus('paid');

          // Show receipt
          if (data.receipt) {
            setCurrentReceipt(data.receipt);
          }

          if (window?.showToast) window.showToast('Payment', 'Online payment completed successfully!', 'success');

          // Auto-close waiting modal and show receipt after a brief moment
          setTimeout(() => {
            setShowPaymentWaitingModal(false);
            setShowReceiptModal(true);

            // Reset form
            setExpandedAppointment(null);
            setViewModalAppointment(null);
            setPaymentAmount('');
            setSelectedDiscounts([]);
            setPaymentType('cash');
            setInKindDescription('');
            setInKindEstimatedValue('');
            setShowCompletionConfirmation(false);
            setCurrentPage(1);

            // Refresh data
            refreshCashierRealtimeData();
            if (activeSection === 'reports') loadShiftReport(shiftRange.from, shiftRange.to);

            setTimeout(() => {
              if (activeSection !== 'appointments') setActiveSection('appointments');
              setAppointmentsTab('completed');
            }, 500);
          }, 1500);
        } else if (data.status === 'expired') {
          clearInterval(pollId);
          setOnlinePaymentPolling(null);
          setOnlinePaymentStatus('expired');
        }
      } catch (err) {
        console.error('Payment polling error:', err);
      }
    }, 5000); // Poll every 5 seconds

    setOnlinePaymentPolling(pollId);
  }, [onlinePaymentPolling, activeSection, loadShiftReport, shiftRange, refreshCashierRealtimeData]);

  // Cancel online payment
  const handleCancelOnlinePayment = useCallback(async () => {
    if (onlinePaymentPolling) {
      clearInterval(onlinePaymentPolling);
      setOnlinePaymentPolling(null);
    }

    if (onlinePaymentAppointment?.id) {
      try {
        await axios.post(`/api/cashier/paymongo/expire/${onlinePaymentAppointment.id}`);
      } catch (err) {
        console.error('Failed to expire checkout session:', err);
      }
    }

    setShowPaymentWaitingModal(false);
    setOnlinePaymentStatus('active');
    setOnlineCheckoutUrl('');
    setOnlinePaymentAppointment(null);
    if (window?.showToast) window.showToast('Payment', 'Online payment cancelled', 'info');
  }, [onlinePaymentPolling, onlinePaymentAppointment]);

  // Cleanup polling on unmount
  useEffect(() => {
    return () => {
      if (onlinePaymentPolling) {
        clearInterval(onlinePaymentPolling);
      }
    };
  }, [onlinePaymentPolling]);

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

    // Phase 5 #5: In-kind guardrails — require description and estimated value
    if (paymentType === 'in-kind') {
      if (!inKindDescription.trim()) {
        if (window?.showToast) window.showToast('Payment', 'Please describe the items received for in-kind payment', 'warning');
        return;
      }
      if (!inKindEstimatedValue || parseFloat(inKindEstimatedValue) <= 0) {
        if (window?.showToast) window.showToast('Payment', 'Please enter a valid estimated peso value for in-kind payment', 'warning');
        return;
      }
    }

    const amount = paymentType === 'in-kind' ? 0 : parseFloat(paymentAmount || 0);
    const { discount, discountType } = calculateDiscount(baseAmount);
    const totalPaid = amount - discount;

    if (paymentType === 'online' && (appointment?.payment_status === 'partially_paid' || Number(appointment?.payment_amount || 0) > 0)) {
      if (window?.showToast) window.showToast('Payment', 'Online checkout is currently available only for unpaid appointments with no prior installments.', 'warning');
      return;
    }

    // ===== ONLINE PAYMENT FLOW =====
    if (paymentType === 'online') {
      try {
        setLoading(true);

        const body = {
          payment_amount: amount,
          discount_type: discountType,
        };

        const response = await callApi((signal) =>
          axios.post(`/api/cashier/paymongo/checkout/${appointmentId}`, body, { signal })
        );

        if (response && response.success) {
          const checkoutUrl = response.data?.checkout_url || response.checkout_url;

          // Close the appointment/confirm modals
          setExpandedAppointment(null);
          setViewModalAppointment(null);
          setShowCompletionConfirmation(false);
          setShowConfirmPaymentModal(false);

          // Open the waiting modal
          setOnlinePaymentAppointment(appointment);
          setOnlineCheckoutUrl(checkoutUrl);
          setOnlinePaymentStatus('active');
          setShowPaymentWaitingModal(true);

          // Open checkout page in new tab
          window.open(checkoutUrl, '_blank');

          // Start polling for payment status
          startOnlinePaymentPolling(appointmentId);

          if (window?.showToast) window.showToast('Payment', 'Checkout page opened. Waiting for payment...', 'info');
        } else {
          if (window?.showToast) window.showToast('Payment', response?.error || response?.message || 'Failed to create checkout session', 'error');
        }
      } catch (error) {
        console.error('Error creating online checkout:', error);
        const msg = error.response?.data?.message || 'Failed to create online checkout. Please try again.';
        if (window?.showToast) window.showToast('Payment', msg, 'error');
      } finally {
        setLoading(false);
      }
      return;
    }

    // ===== STANDARD PAYMENT FLOW (Cash, Partial, In-kind) =====
    try {
      setLoading(true);

      // Build request body
      const body = {
        payment_amount: amount,
        discount_amount: discount,
        discount_type: discountType,
        payment_notes: paymentType === 'in-kind' ? (inKindDescription || 'In-kind payment') : undefined,
        payment_type: paymentType,
        goods_description: paymentType === 'in-kind' ? inKindDescription : undefined,
        in_kind_estimated_value: paymentType === 'in-kind' ? parseFloat(inKindEstimatedValue) || undefined : undefined,
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
        setInKindEstimatedValue('');
        
        // Reset pagination to first page
        setCurrentPage(1);
        
        // Close the completion modal
        setShowCompletionConfirmation(false);

        await refreshCashierRealtimeData();
        
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
  }, [paymentAmount, selectedDiscounts, calculateDiscount, callApi, paymentType, inKindDescription, inKindEstimatedValue, activeSection, loadShiftReport, shiftRange, startOnlinePaymentPolling, refreshCashierRealtimeData]);

  // Mark appointment as No Show
  const handleMarkNoShow = useCallback(async (appointment) => {
    if (!appointment?.id) return;
    try {
      setLoading(true);
      await callApi((signal) =>
        axios.put(`/api/appointments/${appointment.id}/no-show`, {}, { signal })
      );
      if (window?.showToast) window.showToast('No Show', `${appointment.user?.first_name || 'Client'} marked as no show`, 'info');
      if (activeSection === 'dashboard') {
        await Promise.all([
          loadDashboardData(),
          loadDashboardApprovedAppointments({ silent: true }),
        ]);
      } else {
        await loadAppointments();
      }
    } catch (error) {
      console.error('Error marking no-show:', error);
      if (window?.showToast) window.showToast('No Show', error.response?.data?.message || 'Failed to mark as no show', 'error');
    } finally {
      setLoading(false);
    }
  }, [activeSection, callApi, loadAppointments, loadDashboardApprovedAppointments, loadDashboardData]);

  // Render Dashboard Section
  const renderDashboard = () => {
    const avgSale = stats.totalSales > 0 ? stats.totalRevenue / stats.totalSales : 0;
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good Morning' : hour < 17 ? 'Good Afternoon' : 'Good Evening';
    const todayStr = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

    return (
      <div className="space-y-6">
        {/* Welcome Banner */}
        <div className={`relative overflow-hidden rounded-2xl ${isDarkMode ? 'bg-gradient-to-br from-amber-600/20 via-gray-900 to-gray-900 border-amber-500/20' : 'bg-gradient-to-br from-amber-50 via-white to-amber-50/50 border-amber-200'} border p-6`}>
          <div className="absolute top-0 right-0 w-64 h-64 bg-amber-500/5 rounded-full -translate-y-1/2 translate-x-1/3 pointer-events-none" />
          <div className="absolute bottom-0 left-0 w-40 h-40 bg-amber-500/5 rounded-full translate-y-1/2 -translate-x-1/4 pointer-events-none" />
          <div className="relative flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
              <h2 className={`text-xl lg:text-2xl font-bold ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>
                {greeting}, {user?.first_name || 'Cashier'}
              </h2>
              <p className={`text-sm mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{todayStr}</p>
              {!dashboardAppointmentsLoading && dashboardTodayAppointments.length > 0 && (
                <div className="mt-3 flex items-center gap-2">
                  <span className="relative flex h-2.5 w-2.5">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                  </span>
                  <span className={`text-sm font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    {dashboardTodayAppointments.length} appointment{dashboardTodayAppointments.length !== 1 ? 's' : ''} scheduled today
                  </span>
                </div>
              )}
            </div>
            <div className="flex items-center gap-3">
              <button onClick={() => setActiveSection('appointments')} className="px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-medium transition-all shadow-lg shadow-amber-500/20 hover:shadow-amber-500/30 hover:-translate-y-0.5">
                Process Payments
              </button>
            </div>
          </div>
        </div>

        {/* KPI Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
          {/* Total Revenue */}
          <div className={`group relative overflow-hidden rounded-xl ${isDarkMode ? 'bg-gray-900/80 border-gray-800 hover:border-amber-500/30' : 'bg-white border-gray-200 hover:border-amber-300'} border p-5 transition-all duration-300 hover:shadow-lg hover:shadow-amber-500/5`}>
            <div className="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 rounded-full -translate-y-1/2 translate-x-1/2 group-hover:scale-150 transition-transform duration-500" />
            <div className="relative flex items-start justify-between">
              <div>
                <p className={`text-xs font-medium uppercase tracking-wider ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Total Revenue</p>
                <p className={`text-2xl font-bold mt-1.5 ${isDarkMode ? 'text-white' : 'text-gray-900'} tabular-nums`}>{formatPrice(stats.totalRevenue)}</p>
                <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>{timeframe} period</p>
              </div>
              <div className={`p-2.5 rounded-xl ${isDarkMode ? 'bg-amber-500/10' : 'bg-amber-50'} group-hover:scale-110 transition-transform`}>
                <CurrencyDollarIcon className="h-5 w-5 text-amber-500" />
              </div>
            </div>
            <div className="mt-4"><Sparkline data={revenueData.slice(-12)} width={160} height={32} type="area" stroke="#f59e0b" /></div>
          </div>

          {/* Total Sales */}
          <div className={`group relative overflow-hidden rounded-xl ${isDarkMode ? 'bg-gray-900/80 border-gray-800 hover:border-blue-500/30' : 'bg-white border-gray-200 hover:border-blue-300'} border p-5 transition-all duration-300 hover:shadow-lg hover:shadow-blue-500/5`}>
            <div className="absolute top-0 right-0 w-24 h-24 bg-blue-500/5 rounded-full -translate-y-1/2 translate-x-1/2 group-hover:scale-150 transition-transform duration-500" />
            <div className="relative flex items-start justify-between">
              <div>
                <p className={`text-xs font-medium uppercase tracking-wider ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Total Sales</p>
                <p className={`text-2xl font-bold mt-1.5 ${isDarkMode ? 'text-white' : 'text-gray-900'} tabular-nums`}>{stats.totalSales}</p>
                <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Completed transactions</p>
              </div>
              <div className={`p-2.5 rounded-xl ${isDarkMode ? 'bg-blue-500/10' : 'bg-blue-50'} group-hover:scale-110 transition-transform`}>
                <DocumentTextIcon className="h-5 w-5 text-blue-500" />
              </div>
            </div>
            <div className="mt-4"><Sparkline data={revenueData.slice(-12)} width={160} height={32} type="bars" stroke="#3b82f6" /></div>
          </div>

          {/* Average Sale */}
          <div className={`group relative overflow-hidden rounded-xl ${isDarkMode ? 'bg-gray-900/80 border-gray-800 hover:border-emerald-500/30' : 'bg-white border-gray-200 hover:border-emerald-300'} border p-5 transition-all duration-300 hover:shadow-lg hover:shadow-emerald-500/5`}>
            <div className="absolute top-0 right-0 w-24 h-24 bg-emerald-500/5 rounded-full -translate-y-1/2 translate-x-1/2 group-hover:scale-150 transition-transform duration-500" />
            <div className="relative flex items-start justify-between">
              <div>
                <p className={`text-xs font-medium uppercase tracking-wider ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Average Sale</p>
                <p className={`text-2xl font-bold mt-1.5 ${isDarkMode ? 'text-white' : 'text-gray-900'} tabular-nums`}>{formatPrice(avgSale)}</p>
                <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Per transaction</p>
              </div>
              <div className={`p-2.5 rounded-xl ${isDarkMode ? 'bg-emerald-500/10' : 'bg-emerald-50'} group-hover:scale-110 transition-transform`}>
                <ChartBarIcon className="h-5 w-5 text-emerald-500" />
              </div>
            </div>
            <div className="mt-4"><Sparkline data={revenueData.slice(-12)} width={160} height={32} type="area" stroke="#10b981" /></div>
          </div>

          {/* Today's Revenue */}
          <div className={`group relative overflow-hidden rounded-xl ${isDarkMode ? 'bg-gray-900/80 border-gray-800 hover:border-purple-500/30' : 'bg-white border-gray-200 hover:border-purple-300'} border p-5 transition-all duration-300 hover:shadow-lg hover:shadow-purple-500/5`}>
            <div className="absolute top-0 right-0 w-24 h-24 bg-purple-500/5 rounded-full -translate-y-1/2 translate-x-1/2 group-hover:scale-150 transition-transform duration-500" />
            <div className="relative flex items-start justify-between">
              <div>
                <p className={`text-xs font-medium uppercase tracking-wider ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>Today's Revenue</p>
                <p className={`text-2xl font-bold mt-1.5 ${isDarkMode ? 'text-white' : 'text-gray-900'} tabular-nums`}>{formatPrice(stats.todayRevenue)}</p>
                <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>{stats.todaySales} sale{stats.todaySales !== 1 ? 's' : ''} today</p>
              </div>
              <div className={`p-2.5 rounded-xl ${isDarkMode ? 'bg-purple-500/10' : 'bg-purple-50'} group-hover:scale-110 transition-transform`}>
                <CalendarIcon className="h-5 w-5 text-purple-500" />
              </div>
            </div>
            <div className="mt-4"><Sparkline data={revenueData.slice(-7)} width={160} height={32} type="line" stroke="#8b5cf6" /></div>
          </div>
        </div>

        {/* Charts Row */}
        <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
          {/* Revenue Trend - Large Chart */}
          <div className={`xl:col-span-2 ${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'} border rounded-xl p-5 hover:shadow-lg hover:shadow-amber-500/5 transition-all duration-300`}>
            <div className="flex items-center justify-between mb-4">
              <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-gray-200' : 'text-gray-800'} flex items-center gap-2`}>
                <div className="w-1 h-4 bg-amber-500 rounded-full" />
                Revenue Trend
              </h3>
              <button onClick={exportRevenueCSV} className={`text-xs px-3 py-1.5 rounded-lg transition-colors font-medium ${isDarkMode ? 'text-gray-400 hover:text-white hover:bg-gray-800' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'}`}>
                Export CSV
              </button>
            </div>
            <LineChart data={revenueData} title="" height={180} embedded variant="bars" responsive maxHeight={280} />
          </div>

          {/* Service Distribution */}
          <div className={`${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'} border rounded-xl p-5 hover:shadow-lg hover:shadow-amber-500/5 transition-all duration-300`}>
            <DonutChart data={salesByService} title="Service Distribution" isDarkMode={isDarkMode} />
          </div>
        </div>

        {/* Bottom Widgets Row */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Today's Schedule */}
          <div className={`${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'} border rounded-xl p-5 hover:shadow-lg hover:shadow-amber-500/5 transition-all duration-300`}>
            <div className="flex items-center justify-between mb-4">
              <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-gray-200' : 'text-gray-800'} flex items-center gap-2`}>
                <div className="w-1 h-4 bg-blue-500 rounded-full" />
                Today's Schedule
              </h3>
              <button onClick={() => setActiveSection('calendar')} className={`text-xs ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-amber-600 hover:text-amber-700'} font-medium transition-colors`}>
                View Calendar &rarr;
              </button>
            </div>
            <div className="space-y-2 max-h-[280px] overflow-auto">
              {dashboardAppointmentsLoading ? (
                <div className="flex justify-center py-8">
                  <LoadingSpinner />
                </div>
              ) : dashboardTodayAppointments.length === 0 ? (
                <div className={`text-center py-8 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`}>
                  <CalendarIcon className="h-10 w-10 mx-auto mb-2 opacity-40" />
                  <p className="text-sm">No appointments today</p>
                  <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-700' : 'text-gray-300'}`}>Enjoy the quiet day!</p>
                </div>
              ) : (
                dashboardTodayAppointments.map((apt) => (
                  <div key={apt.id} onClick={() => setViewModalAppointment(apt)} className={`flex items-center gap-3 p-3 rounded-xl cursor-pointer transition-all ${isDarkMode ? 'bg-gray-800/50 hover:bg-gray-800 border border-gray-800 hover:border-gray-700' : 'bg-gray-50 hover:bg-gray-100 border border-gray-100 hover:border-gray-200'}`}>
                    <div className="flex-shrink-0 w-1 h-10 bg-amber-500 rounded-full" />
                    <div className="flex-1 min-w-0">
                      <p className={`text-sm font-medium truncate ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>
                        {apt.user?.first_name} {apt.user?.last_name}
                      </p>
                      <p className={`text-xs truncate ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                        {apt.service?.name || 'Service'} &middot; {apt.start_time}
                      </p>
                    </div>
                    <span className={`text-xs font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} tabular-nums`}>
                      {formatPrice(apt.service?.price)}
                    </span>
                  </div>
                ))
              )}
            </div>
          </div>

          {/* Pending Payments */}
          <div className={`${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'} border rounded-xl p-5 hover:shadow-lg hover:shadow-amber-500/5 transition-all duration-300`}>
            <div className="flex items-center justify-between mb-4">
              <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-gray-200' : 'text-gray-800'} flex items-center gap-2`}>
                <div className="w-1 h-4 bg-emerald-500 rounded-full" />
                Pending Payments
              </h3>
              <button onClick={() => setActiveSection('appointments')} className={`text-xs ${isDarkMode ? 'text-amber-400 hover:text-amber-300' : 'text-amber-600 hover:text-amber-700'} font-medium transition-colors`}>
                View all &rarr;
              </button>
            </div>
            <div className="space-y-2 max-h-[280px] overflow-auto">
              {(() => {
                if (dashboardAppointmentsLoading) {
                  return (
                    <div className="flex justify-center py-8">
                      <LoadingSpinner />
                    </div>
                  );
                }

                if (dashboardPendingAppointments.length === 0) return (
                  <div className={`text-center py-8 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`}>
                    <CheckCircleIcon className="h-10 w-10 mx-auto mb-2 opacity-40" />
                    <p className="text-sm">All caught up!</p>
                    <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-700' : 'text-gray-300'}`}>No pending payments</p>
                  </div>
                );
                return dashboardPendingAppointments.slice(0, 6).map(apt => (
                  <div key={apt.id} onClick={() => setViewModalAppointment(apt)} className={`flex items-center justify-between gap-3 p-3 rounded-xl cursor-pointer transition-all ${isDarkMode ? 'bg-gray-800/50 hover:bg-gray-800 border border-gray-800 hover:border-amber-500/30' : 'bg-gray-50 hover:bg-gray-100 border border-gray-100 hover:border-amber-200'}`}>
                    <div className="flex-1 min-w-0">
                      <p className={`text-sm font-medium truncate ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>{apt.user?.first_name} {apt.user?.last_name}</p>
                      <p className={`text-xs truncate ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>{apt.service?.name || 'Service'}</p>
                    </div>
                    <span className={`text-xs font-bold tabular-nums ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>{formatPrice(apt.service?.price)}</span>
                  </div>
                ));
              })()}
            </div>
          </div>

          {/* Revenue Breakdown */}
          <div className={`${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'} border rounded-xl p-5 hover:shadow-lg hover:shadow-amber-500/5 transition-all duration-300`}>
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-gray-200' : 'text-gray-800'} mb-4 flex items-center gap-2`}>
              <div className="w-1 h-4 bg-purple-500 rounded-full" />
              Revenue Breakdown
            </h3>
            <div className="space-y-3 max-h-[280px] overflow-auto">
              {revenueData.slice(-8).map((item, index) => {
                const maxVal = Math.max(...revenueData.slice(-8).map(d => Number(d.value) || 0), 1);
                const pct = ((Number(item.value) || 0) / maxVal) * 100;
                return (
                  <div key={index} className="group">
                    <div className="flex items-center justify-between mb-1">
                      <span className={`text-xs font-medium ${isDarkMode ? 'text-gray-400 group-hover:text-gray-200' : 'text-gray-500 group-hover:text-gray-700'} transition-colors`}>{item.label}</span>
                      <span className={`text-xs font-bold tabular-nums ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{formatPrice(item.value)}</span>
                    </div>
                    <div className={`w-full h-2 rounded-full ${isDarkMode ? 'bg-gray-800' : 'bg-gray-100'} overflow-hidden`}>
                      <div className="h-full rounded-full bg-gradient-to-r from-amber-500 to-amber-400 transition-all duration-700 ease-out group-hover:from-amber-400 group-hover:to-yellow-400" style={{ width: `${Math.max(2, pct)}%` }} />
                    </div>
                  </div>
                );
              })}
              {revenueData.length === 0 && (
                <div className={`text-center py-6 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`}>
                  <ChartBarIcon className="h-8 w-8 mx-auto mb-2 opacity-40" />
                  <p className="text-xs">No revenue data yet</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    );
  };

  // Render Appointments Section
  const renderAppointments = () => (
    <div className="space-y-4">
      {/* Tabs */}
      <div className={`flex items-center gap-2 border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
        <button
          onClick={() => setAppointmentsTab('approved')}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            appointmentsTab === 'approved'
              ? `border-amber-500 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`
              : `border-transparent ${isDarkMode ? 'text-gray-400 hover:text-amber-300' : 'text-gray-500 hover:text-amber-500'}`
          }`}
        >
          Approved ({appointmentsTab === 'approved' ? filteredAppointments.filter(apt => apt.status === 'approved' && (apt.payment_status !== 'paid')).length : ''})
        </button>
        <button
          onClick={() => setAppointmentsTab('completed')}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            appointmentsTab === 'completed'
              ? `border-amber-500 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`
              : `border-transparent ${isDarkMode ? 'text-gray-400 hover:text-amber-300' : 'text-gray-500 hover:text-amber-500'}`
          }`}
        >
          Completed ({appointmentsTab === 'completed' ? filteredAppointments.filter(apt => ['paid', 'refunded', 'partially_refunded'].includes(apt.payment_status) || apt.status === 'completed').length : ''})
        </button>
        <div className="ml-auto flex items-center gap-2">
          <label className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mr-1`}>Rows</label>
          <select value={perPage} onChange={(e) => { setPerPage(Number(e.target.value)); setCurrentPage(1); }} className={`border text-xs px-2 py-1 rounded ${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-white border-gray-300 text-gray-800'}`}>
            <option value={5}>5</option>
            <option value={10}>10</option>
            <option value={20}>20</option>
          </select>
          <button onClick={() => setIsDense(d => !d)} className={`px-2 py-1 text-xs rounded transition-colors ${isDense ? 'bg-amber-600 text-white' : (isDarkMode ? 'bg-gray-800 text-gray-300' : 'bg-gray-200 text-gray-600')}`} title="Toggle dense view">{isDense ? 'Dense' : 'Comfort'}</button>
        </div>
      </div>

      {/* Search bar — Phase 7 #7 */}
      <div className="relative">
        <input
          type="text"
          value={appointmentSearch}
          onChange={(e) => setAppointmentSearch(e.target.value)}
          placeholder="Search by name, service, date, or ID..."
          className={`w-full px-3 py-2 pl-9 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white placeholder-gray-500' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'}`}
        />
        <MagnifyingGlassIcon className={`absolute left-3 top-2.5 h-4 w-4 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />
        {appointmentSearch && (
          <button onClick={() => setAppointmentSearch('')} className={`absolute right-3 top-2.5 ${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-500 hover:text-amber-600'}`}>
            <XMarkIcon className="h-4 w-4" />
          </button>
        )}
      </div>

      {/* Today's Appointments Notice */}
      {appointmentsTab === 'approved' && todayAppointments.length > 0 && (
        <div className={`${isDarkMode ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50 border-amber-200'} border rounded-lg p-4 space-y-2`}>
            <div className="flex items-center justify-between">
            <p className={`${isDarkMode ? 'text-amber-400' : 'text-amber-700'} text-sm font-medium`}>📅 You have {todayAppointments.length} appointment(s) scheduled for today</p>
            <button
              onClick={() => toggleExpanded('today-list')}
              className="px-2 py-1 text-xs bg-amber-600 text-white rounded hover:bg-amber-700 transition-colors"
            >
              {expandedAppointment === 'today-list' ? 'Collapse' : 'View Today'}
            </button>
          </div>

          {expandedAppointment === 'today-list' && (
            <div className="mt-2 space-y-2">
                    {todayAppointments.map((apt) => (
                      <div key={apt.id} className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'} border rounded p-2 flex items-center justify-between text-xs`}>
                        <div>
                          <div className={`font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{apt.user?.first_name} {apt.user?.last_name}</div>
                          <div className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>{apt.service?.name || 'N/A'} — {apt.start_time}</div>
                        </div>
                        <div>
                          <button
                            onClick={() => setViewModalAppointment(apt)}
                            className="px-2 py-1 text-xs bg-amber-600 text-white rounded hover:bg-amber-700 transition-colors"
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
              className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20 hover:border-amber-500/40' : 'bg-white border-gray-200 hover:border-amber-300'} border rounded-lg overflow-hidden transition-all ${isDense ? 'p-2 text-sm' : ''}`}
            >
              <div className={`${isDense ? 'p-2' : 'p-3'} text-sm`}>
                <div className="flex justify-between items-start">
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                        {appointment.user?.first_name} {appointment.user?.last_name}
                      </h3>
                      {appointment.status === 'no_show' && (
                        <span className={`px-1.5 py-0.5 rounded text-[10px] font-bold uppercase ${isDarkMode ? 'bg-red-500/20 text-red-400' : 'bg-red-100 text-red-600'}`}>No Show</span>
                      )}
                      {appointment.payment_status === 'partially_paid' && (
                        <span className={`px-1.5 py-0.5 rounded text-[10px] font-bold ${isDarkMode ? 'bg-orange-500/20 text-orange-400' : 'bg-orange-100 text-orange-600'}`}>
                          {formatPrice(appointment.balance_remaining)} remaining
                        </span>
                      )}
                    </div>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mt-1`}>
                      {appointment.service?.name || 'N/A'} &middot; <span className={`font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>{formatPrice(appointment.service?.price)}</span>
                    </p>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                      {formatDateDisplay(appointment.appointment_date)} &middot; {appointment.start_time} - {appointment.end_time}
                    </p>
                  </div>
                  
                    <div className="flex items-center gap-2">
                      {appointmentsTab === 'approved' && (
                        <>
                          <button
                            onClick={() => setViewModalAppointment(appointment)}
                            className="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-xs flex items-center gap-1 transition-colors"
                          >
                            <EyeIcon className="h-3 w-3" />
                            Complete
                          </button>
                          <button
                            onClick={(e) => { e.stopPropagation(); if (window.confirm(`Mark ${appointment.user?.first_name || 'this client'} as No Show?`)) handleMarkNoShow(appointment); }}
                            className={`px-2.5 py-1 rounded text-xs flex items-center gap-1 transition-colors ${isDarkMode ? 'bg-gray-700 hover:bg-red-600/80 text-gray-300 hover:text-white' : 'bg-gray-200 hover:bg-red-500 text-gray-600 hover:text-white'}`}
                            title="Mark as No Show"
                          >
                            <XCircleIcon className="h-3 w-3" />
                            No Show
                          </button>
                        </>
                      )}

                      {appointmentsTab === 'completed' && (
                        <>
                          {/* Show refund status badge if refund exists */}
                          {appointment.active_refund && (
                            <span className={`px-2 py-1 rounded-full text-xs font-semibold ${
                              appointment.active_refund.status === 'completed' ? (isDarkMode ? 'bg-green-500/20 text-green-400' : 'bg-green-100 text-green-700') :
                              appointment.active_refund.status === 'pending' ? (isDarkMode ? 'bg-yellow-500/20 text-yellow-400' : 'bg-yellow-100 text-yellow-700') :
                              appointment.active_refund.status === 'approved' ? (isDarkMode ? 'bg-blue-500/20 text-blue-400' : 'bg-blue-100 text-blue-700') :
                              (isDarkMode ? 'bg-red-500/20 text-red-400' : 'bg-red-100 text-red-700')
                            }`}>Refund: {appointment.active_refund.status}</span>
                          )}
                          {/* Request Refund button - only if paid and no active refund */}
                          {appointment.payment_status === 'paid' && !appointment.active_refund && (
                            <button
                              onClick={() => openCashierRefundModal(appointment)}
                              className="px-2 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs flex items-center gap-1 transition-colors"
                              title="Request refund for this appointment"
                            >
                              <ArrowUturnLeftIcon className="h-3 w-3" />
                              Refund
                            </button>
                          )}
                          <button
                            onClick={() => setViewModalAppointment({ ...appointment, _viewOnly: true })}
                            className={`px-3 py-1 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded text-xs flex items-center gap-1 transition-colors`}
                          >
                            <EyeIcon className="h-3 w-3" />
                            View
                          </button>
                        </>
                      )}
                    </div>
                </div>

                
              </div>
            </div>
                ))}

                {/* Pagination Controls */}
                <div className="flex items-center gap-4 mt-3">
                  <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Showing {start} - {end} of {totalAppointments || filteredAppointments.length}</div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => setCurrentPage(p => Math.max(1, p-1))}
                      disabled={currentPage === 1}
                      className={`px-2 py-1 text-xs rounded disabled:opacity-50 transition-colors ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
                    >Prev</button>
                    <div className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Page {currentPage} / {Math.max(1, totalPages)}</div>
                    <button
                      onClick={() => setCurrentPage(p => Math.min(totalPages, p+1))}
                      disabled={currentPage >= totalPages}
                      className={`px-2 py-1 text-xs rounded disabled:opacity-50 transition-colors ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
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
      <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,42rem)_minmax(0,1fr)] gap-6 items-start">
        <div className="max-w-xl xl:max-w-none">
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
        </div>

        <div className="min-w-0">
          <CalendarDetailPanel
            selectedDate={selectedDate}
            appointments={filteredCalendarAppts}
            currentMonth={currentMonth}
            monthNames={monthNames}
            onAppointmentClick={(apt) => {
              setViewModalAppointment(apt);
            }}
            isLoading={calendarLoading}
            isDarkMode={isDarkMode}
          />
        </div>
      </div>
    );
  };

  // Render Action Logs Section
  const renderActionLogs = () => (
    <div className="space-y-4">
      {/* Tabs */}
      <div className={`flex gap-2 border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
        <button
          onClick={() => {
            setLogsTab('admin');
          }}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            logsTab === 'admin'
              ? `border-amber-500 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`
              : `border-transparent ${isDarkMode ? 'text-gray-400 hover:text-amber-300' : 'text-gray-500 hover:text-amber-500'}`
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
              ? `border-amber-500 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`
              : `border-transparent ${isDarkMode ? 'text-gray-400 hover:text-amber-300' : 'text-gray-500 hover:text-amber-500'}`
          }`}
        >
          My Logs
        </button>
      </div>

      {/* Pagination Controls on Left */}
      <div className="flex items-center gap-4 mb-4">
        <div className="flex items-center gap-2">
          <label className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mr-1`}>Rows</label>
          <select 
            value={logsPerPage} 
            onChange={(e) => { 
              setLogsPerPage(Number(e.target.value)); 
              setLogsPage(1); 
            }} 
            className={`border text-xs px-2 py-1 rounded ${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-white border-gray-300 text-gray-800'}`}
          >
            <option value={5}>5</option>
            <option value={10}>10</option>
            <option value={20}>20</option>
            <option value={50}>50</option>
          </select>
        </div>

        <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
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
              className={`px-2 py-1 text-xs rounded disabled:opacity-50 disabled:cursor-not-allowed transition-colors ${isDarkMode ? 'bg-gray-800 hover:bg-amber-600 text-gray-300 hover:text-white' : 'bg-gray-200 hover:bg-amber-500 text-gray-600 hover:text-white'}`}
              title="First page"
            >
              «
            </button>
            <button
              onClick={() => setLogsPage(prev => Math.max(1, prev - 1))}
              disabled={logsPage === 1}
              className={`px-2 py-1 text-xs rounded disabled:opacity-50 disabled:cursor-not-allowed transition-colors ${isDarkMode ? 'bg-gray-800 hover:bg-amber-600 text-gray-300 hover:text-white' : 'bg-gray-200 hover:bg-amber-500 text-gray-600 hover:text-white'}`}
              title="Previous page"
            >
              ‹
            </button>
            <span className={`text-xs px-2 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
              {logsPage} / {totalLogPages}
            </span>
            <button
              onClick={() => setLogsPage(prev => Math.min(totalLogPages, prev + 1))}
              disabled={logsPage === totalLogPages}
              className={`px-2 py-1 text-xs rounded disabled:opacity-50 disabled:cursor-not-allowed transition-colors ${isDarkMode ? 'bg-gray-800 hover:bg-amber-600 text-gray-300 hover:text-white' : 'bg-gray-200 hover:bg-amber-500 text-gray-600 hover:text-white'}`}
              title="Next page"
            >
              ›
            </button>
            <button
              onClick={() => setLogsPage(totalLogPages)}
              disabled={logsPage === totalLogPages}
              className={`px-2 py-1 text-xs rounded disabled:opacity-50 disabled:cursor-not-allowed transition-colors ${isDarkMode ? 'bg-gray-800 hover:bg-amber-600 text-gray-300 hover:text-white' : 'bg-gray-200 hover:bg-amber-500 text-gray-600 hover:text-white'}`}
              title="Last page"
            >
              »
            </button>
          </div>
        )}
      </div>

      {/* Logs Table */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'} border rounded-lg overflow-hidden`}>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border-b`}>
              <tr>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Date & Time</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>User</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Action</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Details</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Actions</th>
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
                  <td colSpan="5" className={`px-4 py-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                    No {logsTab === 'admin' ? 'admin' : 'cashier'} logs found
                  </td>
                </tr>
              ) : (
                actionLogs.map((log) => {
                  const metadataPreview = getActionLogMetadataEntries(log.metadata).slice(0, 2);

                  return (
                  <tr key={log.id} className={`border-b ${isDarkMode ? 'border-gray-800 hover:bg-gray-800/50' : 'border-gray-100 hover:bg-gray-50'} transition-colors`}>
                    <td className={`px-4 py-3 align-top ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                      {new Date(log.created_at).toLocaleString()}
                    </td>
                    <td className={`px-4 py-3 align-top ${isDarkMode ? 'text-amber-50' : 'text-gray-900'} font-medium`}>
                      {log.user ? `${log.user.first_name || ''} ${log.user.last_name || ''}`.trim() : 'Unknown'}
                    </td>
                    <td className="px-4 py-3 align-top">
                      <span className={`px-2 py-1 rounded-full text-xs font-semibold ${isDarkMode ? 'bg-amber-500/20 text-amber-400' : 'bg-amber-100 text-amber-700'}`}>
                        {log.action}
                      </span>
                    </td>
                    <td className={`px-4 py-3 align-top ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                      <div className="max-w-md space-y-2">
                        <p className="text-xs leading-relaxed whitespace-normal break-words" title={log.description || ''}>
                          {log.description || 'No description provided.'}
                        </p>
                        <div className="flex flex-wrap gap-2">
                          <span className={`px-2 py-1 rounded-full text-[10px] font-semibold ${getActionLogStatusClasses(log.status, isDarkMode)}`}>
                            {formatActionLogStatus(log.status)}
                          </span>
                          {(log.model_type || log.model_id) && (
                            <span className={`px-2 py-1 rounded-full text-[10px] font-medium ${isDarkMode ? 'bg-gray-800 text-gray-300' : 'bg-gray-100 text-gray-700'}`}>
                              {log.model_type || 'Record'}{log.model_id ? ` #${log.model_id}` : ''}
                            </span>
                          )}
                          {metadataPreview.map((entry) => (
                            <span
                              key={`${log.id}-${entry.key}`}
                              className={`px-2 py-1 rounded-full text-[10px] font-medium ${isDarkMode ? 'bg-gray-800 text-gray-300' : 'bg-gray-100 text-gray-700'}`}
                              title={`${entry.label}: ${entry.value}`}
                            >
                              {entry.label}: {entry.value}
                            </span>
                          ))}
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-3 align-top">
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
                );
                })
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
      {/* Profile Card - Header + Personal Info combined */}
      <div className={`${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'} border rounded-xl overflow-hidden`}>
        {/* Gradient Banner */}
        <div className="h-24 bg-gradient-to-r from-amber-600 via-amber-500 to-yellow-500 relative">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_30%_50%,rgba(255,255,255,0.15),transparent_60%)]" />
        </div>
        {/* Avatar + Name + Actions */}
        <div className="px-6 pb-5 -mt-10 relative">
          <div className="flex items-end justify-between">
            <div className="flex items-end gap-4">
              <div className={`w-20 h-20 rounded-xl bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center shadow-lg ring-4 ${isDarkMode ? 'ring-gray-900' : 'ring-white'}`}>
                <span className="text-2xl font-bold text-white">{user?.first_name?.[0]}{user?.last_name?.[0]}</span>
              </div>
              <div className="pb-1">
                <h2 className={`text-xl font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{user?.first_name} {user?.last_name}</h2>
                <div className="flex items-center gap-2 mt-0.5">
                  <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/20 text-amber-400 uppercase tracking-wide">{user?.role}</span>
                  <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-500/20 text-green-400">Active</span>
                </div>
              </div>
            </div>
            <button
              onClick={isEditingProfile ? handleCancelEdit : handleEditProfile}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                isEditingProfile
                  ? (isDarkMode ? 'bg-gray-700 text-gray-200 hover:bg-gray-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300')
                  : 'bg-amber-600 text-white hover:bg-amber-700 shadow-sm'
              }`}
            >
              {isEditingProfile ? 'Cancel' : 'Edit Profile'}
            </button>
          </div>

          {/* Account Meta Row */}
          <div className={`mt-4 flex flex-wrap gap-4 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
            <span className="flex items-center gap-1"><ShieldCheckIcon className="h-3.5 w-3.5 text-amber-500" /> ID #{user?.id || 'N/A'}</span>
            <span className="flex items-center gap-1"><CalendarIcon className="h-3.5 w-3.5 text-amber-500" /> Joined {user?.created_at ? new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }) : 'N/A'}</span>
            <span className="flex items-center gap-1"><EnvelopeIcon className="h-3.5 w-3.5 text-amber-500" /> {user?.email || 'N/A'}</span>
          </div>
        </div>
      </div>

      {/* Personal Information */}
      <div className={`${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'} border rounded-xl p-5`}>
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'} mb-4 flex items-center gap-2`}>
          <UserCircleIcon className="h-4 w-4 text-amber-500" />
          Personal Information
        </h3>

        {isEditingProfile ? (
          <div className="space-y-3">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {[
                { label: 'First Name', key: 'first_name', type: 'text', placeholder: 'Enter first name' },
                { label: 'Last Name', key: 'last_name', type: 'text', placeholder: 'Enter last name' },
              ].map(field => (
                <div key={field.key}>
                  <label className={`block text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1.5 font-medium`}>{field.label}</label>
                  <input
                    type={field.type}
                    value={profileFormData[field.key]}
                    onChange={(e) => setProfileFormData(prev => ({ ...prev, [field.key]: e.target.value }))}
                    className={`w-full border px-3 py-2 rounded-lg text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-white border-gray-300 text-gray-900'}`}
                    placeholder={field.placeholder}
                  />
                </div>
              ))}
            </div>
            {[
              { label: 'Email', key: 'email', type: 'email', placeholder: 'Enter email address' },
              { label: 'Phone', key: 'phone', type: 'tel', placeholder: 'Enter phone number' },
            ].map(field => (
              <div key={field.key}>
                <label className={`block text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1.5 font-medium`}>{field.label}</label>
                <input
                  type={field.type}
                  value={profileFormData[field.key]}
                  onChange={(e) => setProfileFormData(prev => ({ ...prev, [field.key]: e.target.value }))}
                  className={`w-full border px-3 py-2 rounded-lg text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-white border-gray-300 text-gray-900'}`}
                  placeholder={field.placeholder}
                />
              </div>
            ))}
            <div className="pt-3 flex gap-2 justify-end">
              <button
                onClick={handleCancelEdit}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${isDarkMode ? 'bg-gray-700 text-gray-200 hover:bg-gray-600' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
              >
                Cancel
              </button>
              <button
                onClick={handleSaveProfile}
                disabled={profileSaving}
                className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg text-sm font-medium hover:from-amber-700 hover:to-amber-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              >
                {profileSaving ? (<><div className="animate-spin">⟳</div> Saving...</>) : 'Save Changes'}
              </button>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {[
              { label: 'First Name', value: user?.first_name },
              { label: 'Last Name', value: user?.last_name },
              { label: 'Email Address', value: user?.email, colSpan: true },
              { label: 'Phone Number', value: user?.phone },
            ].map((item, i) => (
              <div key={i} className={`${item.colSpan ? 'md:col-span-2' : ''} ${isDarkMode ? 'bg-gray-800/60 border-gray-700/50' : 'bg-gray-50 border-gray-200'} rounded-lg px-4 py-3 border`}>
                <p className={`text-[11px] ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mb-0.5 font-medium uppercase tracking-wide`}>{item.label}</p>
                <p className={`text-sm ${isDarkMode ? 'text-amber-50' : 'text-gray-900'} font-medium`}>{item.value || 'Not set'}</p>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Security Section - Password Change */}
      <div className={`${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'} border rounded-xl p-5`}>
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'} mb-4 flex items-center gap-2`}>
          <LockClosedIcon className="h-4 w-4 text-amber-500" />
          Change Password
        </h3>
        {passwordSuccess && (
          <div className="mb-3 p-2.5 bg-green-500/10 border border-green-500/30 rounded-lg text-green-400 text-xs">{passwordSuccess}</div>
        )}
        {passwordErrors.general && (
          <div className="mb-3 p-2.5 bg-red-500/10 border border-red-500/30 rounded-lg text-red-400 text-xs">{passwordErrors.general}</div>
        )}
        <div className="space-y-3 max-w-md">
          <div>
            <label className={`block text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1.5 font-medium`}>Current Password</label>
            <div className="relative">
              <input
                type={showPassword.current ? 'text' : 'password'}
                value={passwordData.current_password}
                onChange={(e) => setPasswordData(prev => ({ ...prev, current_password: e.target.value }))}
                className={`w-full border px-3 py-2 rounded-lg text-sm pr-10 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-white border-gray-300 text-gray-900'}`}
                placeholder="Enter current password"
              />
              <button type="button" onClick={() => setShowPassword(p => ({ ...p, current: !p.current }))} className={`absolute right-2 top-2 p-1 rounded ${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-500 hover:text-amber-600'}`}>
                <EyeSlashIcon className="h-4 w-4" />
              </button>
            </div>
          </div>
          <div>
            <label className={`block text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1.5 font-medium`}>New Password</label>
            <div className="relative">
              <input
                type={showPassword.new ? 'text' : 'password'}
                value={passwordData.new_password}
                onChange={(e) => setPasswordData(prev => ({ ...prev, new_password: e.target.value }))}
                className={`w-full border px-3 py-2 rounded-lg text-sm pr-10 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-white border-gray-300 text-gray-900'}`}
                placeholder="Min 8 characters"
              />
              <button type="button" onClick={() => setShowPassword(p => ({ ...p, new: !p.new }))} className={`absolute right-2 top-2 p-1 rounded ${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-500 hover:text-amber-600'}`}>
                <EyeSlashIcon className="h-4 w-4" />
              </button>
            </div>
          </div>
          <div>
            <label className={`block text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1.5 font-medium`}>Confirm New Password</label>
            <div className="relative">
              <input
                type={showPassword.confirm ? 'text' : 'password'}
                value={passwordData.new_password_confirmation}
                onChange={(e) => setPasswordData(prev => ({ ...prev, new_password_confirmation: e.target.value }))}
                className={`w-full border px-3 py-2 rounded-lg text-sm pr-10 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-white border-gray-300 text-gray-900'}`}
                placeholder="Confirm new password"
              />
              <button type="button" onClick={() => setShowPassword(p => ({ ...p, confirm: !p.confirm }))} className={`absolute right-2 top-2 p-1 rounded ${isDarkMode ? 'text-gray-400 hover:text-amber-400' : 'text-gray-500 hover:text-amber-600'}`}>
                <EyeSlashIcon className="h-4 w-4" />
              </button>
            </div>
          </div>
          <button
            onClick={handlePasswordSubmit}
            disabled={passwordSaving}
            className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg text-sm font-medium hover:from-amber-700 hover:to-amber-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            {passwordSaving ? (
              <><div className="animate-spin">⟳</div> Changing...</>
            ) : (
              'Change Password'
            )}
          </button>
        </div>
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
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {txLoading ? (
                <tr><td colSpan="8" className="px-4 py-8 text-center"><LoadingSpinner /></td></tr>
              ) : transactions.length === 0 ? (
                <tr><td colSpan="8" className={`px-4 py-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>No transactions found</td></tr>
              ) : (
                transactions.map(tx => (
                  <tr key={tx.id} className={`border-b ${isDarkMode ? 'border-gray-800 hover:bg-gray-800/50' : 'border-gray-100 hover:bg-gray-50'} transition-colors`}>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>{new Date(tx.payment_date || tx.created_at).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' })}</td>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>#{tx.id}</td>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>{`${tx.user?.first_name || ''} ${tx.user?.last_name || ''}`.trim()}</td>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>{tx.service?.name || '—'}</td>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>{formatPrice(tx.payment_amount || 0)}</td>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                      {tx.processedBy ? `${tx.processedBy.first_name || ''} ${tx.processedBy.last_name || ''}`.trim() : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <span className={`px-2 py-1 rounded font-medium ${isDarkMode ? 'bg-green-500/20 text-green-400' : 'bg-green-100 text-green-700'}`}>Paid</span>
                    </td>
                    <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
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
        <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Showing page {txPage} of {txTotalPages} — {txTotal} transactions</div>
        <div className="flex items-center gap-2">
          <button onClick={() => loadTransactions(Math.max(1, txPage - 1))} disabled={txPage === 1} className={`px-2 py-1 text-xs rounded disabled:opacity-50 transition-colors ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}>Prev</button>
          <div className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>Page {txPage} / {txTotalPages}</div>
          <button onClick={() => loadTransactions(Math.min(txTotalPages, txPage + 1))} disabled={txPage >= txTotalPages} className={`px-2 py-1 text-xs rounded disabled:opacity-50 transition-colors ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}>Next</button>
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
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Reports & Analytics</h2>
          <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-sm`}>View your cashier performance and shift reports</p>
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
            className={`border px-3 py-2 rounded text-sm focus:border-amber-500 focus:outline-none ${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-white border-gray-300 text-gray-900'}`}
          />
          <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>to</span>
          <input 
            type="date" 
            value={shiftRange.to} 
            onChange={(e) => setShiftRange(r => ({ ...r, to: e.target.value }))} 
            className={`border px-3 py-2 rounded text-sm focus:border-amber-500 focus:outline-none ${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-200' : 'bg-white border-gray-300 text-gray-900'}`}
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
          <>
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

          {/* Revenue by Service */}
          {shiftReportSummary.revenue_by_service && shiftReportSummary.revenue_by_service.length > 0 && (
            <div className="mt-4">
              <h4 className={`text-xs font-semibold ${isDarkMode ? 'text-amber-300' : 'text-amber-700'} mb-2`}>Revenue by Service</h4>
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className={isDarkMode ? 'border-gray-700' : 'border-gray-200'}>
                      <th className={`text-left py-1.5 px-2 border-b ${isDarkMode ? 'text-gray-400 border-gray-700' : 'text-gray-500 border-gray-200'}`}>Service</th>
                      <th className={`text-right py-1.5 px-2 border-b ${isDarkMode ? 'text-gray-400 border-gray-700' : 'text-gray-500 border-gray-200'}`}>Count</th>
                      <th className={`text-right py-1.5 px-2 border-b ${isDarkMode ? 'text-gray-400 border-gray-700' : 'text-gray-500 border-gray-200'}`}>Revenue</th>
                    </tr>
                  </thead>
                  <tbody>
                    {shiftReportSummary.revenue_by_service.map((s, i) => (
                      <tr key={i} className={isDarkMode ? 'border-gray-700/50' : 'border-gray-100'}>
                        <td className={`py-1.5 px-2 border-b ${isDarkMode ? 'text-gray-300 border-gray-700/50' : 'text-gray-700 border-gray-100'}`}>{s.service}</td>
                        <td className={`py-1.5 px-2 border-b text-right ${isDarkMode ? 'text-gray-300 border-gray-700/50' : 'text-gray-700 border-gray-100'}`}>{s.count}</td>
                        <td className={`py-1.5 px-2 border-b text-right font-medium ${isDarkMode ? 'text-amber-400 border-gray-700/50' : 'text-amber-600 border-gray-100'}`}>{formatPrice(s.revenue)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* Discount Usage */}
          {shiftReportSummary.discount_usage && shiftReportSummary.discount_usage.length > 0 && (
            <div className="mt-4">
              <h4 className={`text-xs font-semibold ${isDarkMode ? 'text-blue-300' : 'text-blue-700'} mb-2`}>Discount Usage</h4>
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr>
                      <th className={`text-left py-1.5 px-2 border-b ${isDarkMode ? 'text-gray-400 border-gray-700' : 'text-gray-500 border-gray-200'}`}>Type</th>
                      <th className={`text-right py-1.5 px-2 border-b ${isDarkMode ? 'text-gray-400 border-gray-700' : 'text-gray-500 border-gray-200'}`}>Count</th>
                      <th className={`text-right py-1.5 px-2 border-b ${isDarkMode ? 'text-gray-400 border-gray-700' : 'text-gray-500 border-gray-200'}`}>Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {shiftReportSummary.discount_usage.map((d, i) => (
                      <tr key={i}>
                        <td className={`py-1.5 px-2 border-b capitalize ${isDarkMode ? 'text-gray-300 border-gray-700/50' : 'text-gray-700 border-gray-100'}`}>{d.type}</td>
                        <td className={`py-1.5 px-2 border-b text-right ${isDarkMode ? 'text-gray-300 border-gray-700/50' : 'text-gray-700 border-gray-100'}`}>{d.count}</td>
                        <td className={`py-1.5 px-2 border-b text-right font-medium ${isDarkMode ? 'text-blue-400 border-gray-700/50' : 'text-blue-600 border-gray-100'}`}>{formatPrice(d.total)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* In-Kind Summary */}
          {shiftReportSummary.in_kind_summary && shiftReportSummary.in_kind_summary.count > 0 && (
            <div className="mt-4">
              <h4 className={`text-xs font-semibold ${isDarkMode ? 'text-purple-300' : 'text-purple-700'} mb-2`}>In-Kind Payments</h4>
              <div className="flex gap-4">
                <div className={`${isDarkMode ? 'bg-purple-500/10 border-purple-500/20' : 'bg-purple-50 border-purple-200'} border rounded p-3 flex-1`}>
                  <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1`}>In-Kind Count</p>
                  <p className={`text-lg font-bold ${isDarkMode ? 'text-purple-300' : 'text-purple-600'}`}>{shiftReportSummary.in_kind_summary.count}</p>
                </div>
                <div className={`${isDarkMode ? 'bg-purple-500/10 border-purple-500/20' : 'bg-purple-50 border-purple-200'} border rounded p-3 flex-1`}>
                  <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mb-1`}>Est. Value</p>
                  <p className={`text-lg font-bold ${isDarkMode ? 'text-purple-300' : 'text-purple-600'}`}>{formatPrice(shiftReportSummary.in_kind_summary.total_estimated_value)}</p>
                </div>
              </div>
            </div>
          )}

          {/* Hourly Distribution */}
          {shiftReportSummary.hourly_distribution && shiftReportSummary.hourly_distribution.length > 0 && (
            <div className="mt-4">
              <h4 className={`text-xs font-semibold ${isDarkMode ? 'text-green-300' : 'text-green-700'} mb-2`}>Peak Hours</h4>
              <div className="space-y-1.5">
                {shiftReportSummary.hourly_distribution.map((h, i) => {
                  const maxRev = Math.max(...shiftReportSummary.hourly_distribution.map(x => x.revenue));
                  const pct = maxRev > 0 ? (h.revenue / maxRev) * 100 : 0;
                  return (
                    <div key={i} className="flex items-center gap-2 text-xs">
                      <span className={`w-16 text-right ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                        {String(h.hour).padStart(2, '0')}:00
                      </span>
                      <div className="flex-1 h-4 bg-gray-200 dark:bg-gray-700 rounded overflow-hidden">
                        <div
                          className="h-full bg-gradient-to-r from-green-500 to-green-600 rounded transition-all"
                          style={{ width: `${pct}%` }}
                        />
                      </div>
                      <span className={`w-10 text-right ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>{h.count}</span>
                      <span className={`w-20 text-right font-medium ${isDarkMode ? 'text-green-400' : 'text-green-600'}`}>{formatPrice(h.revenue)}</span>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
          </>
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
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Total Sales (Period)</p>
              <p className="text-xl font-bold text-blue-400">{stats.totalSales}</p>
              <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-1`}>transactions</p>
            </div>
            <div className="bg-gradient-to-br from-purple-500/10 to-purple-600/10 border border-purple-500/20 rounded p-3 hover:border-purple-500/40 transition-all">
              <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-xs mb-1`}>Performance</p>
              <p className="text-xl font-bold text-purple-500">{stats.totalSales > 0 ? ((stats.todaySales / stats.totalSales) * 100).toFixed(1) : '0.0'}%</p>
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
            className={`px-4 py-2 rounded text-sm transition-all font-medium flex items-center justify-center gap-2 shadow ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'}`}
          >
            <ArrowPathIcon className="h-4 w-4" />
            Today's Report
          </button>
        </div>
      </div>

      {/* Report Information */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'} border rounded-lg p-4`}>
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3 flex items-center`}>
          <InformationCircleIcon className="h-4 w-4 mr-2" />
          Report Information
        </h3>
        <div className={`space-y-2 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
          <p>📊 <strong>Shift Reports:</strong> Detailed breakdown of your daily/custom period transactions including revenue, sales count, and payment methods.</p>
          <p>💾 <strong>CSV Export:</strong> Download raw data in CSV format for use in spreadsheet applications.</p>
          <p>🏢 <strong>Accounting Export:</strong> Send data directly to Xero or QuickBooks for accounting purposes.</p>
          <p>📈 <strong>Dashboard Stats:</strong> Real-time overview of monthly and daily performance metrics.</p>
        </div>
      </div>
    </div>
  );

  // Render Refunds Section
  const renderRefunds = () => (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Refund Management</h2>
          <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-sm`}>View refund requests across all statuses</p>
        </div>
      </div>

      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'} border rounded-lg overflow-hidden`}>
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border-b`}>
              <tr>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>ID</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Client</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Amount</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Reason</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Status</th>
                <th className={`px-4 py-3 text-left font-semibold ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>Date</th>
              </tr>
            </thead>
            <tbody>
              {refundsLoading ? (
                <tr><td colSpan="6" className="px-4 py-8 text-center"><LoadingSpinner /></td></tr>
              ) : !Array.isArray(refunds) || refunds.length === 0 ? (
                <tr><td colSpan="6" className={`px-4 py-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                  <ArrowUturnLeftIcon className="h-8 w-8 mx-auto mb-2 opacity-30" />
                  No refund requests found
                </td></tr>
              ) : (
                refunds.map((refund) => {
                  const reasonLabel = formatSentenceLabel(refund.reason || refund.refund_reason?.name);

                  return (
                    <tr key={refund.id} className={`border-b ${isDarkMode ? 'border-gray-800 hover:bg-gray-800/50' : 'border-gray-100 hover:bg-gray-50'} transition-colors`}>
                      <td className={`px-4 py-3 ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>#{refund.id}</td>
                      <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                        {refund.appointment?.user ? `${refund.appointment.user.first_name} ${refund.appointment.user.last_name}` : 'N/A'}
                      </td>
                      <td className={`px-4 py-3 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} font-medium`}>{formatPrice(refund.refund_amount || 0)}</td>
                      <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                        <div className="max-w-xs truncate" title={reasonLabel}>{reasonLabel}</div>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-1 rounded-full text-xs font-semibold ${
                          refund.status === 'approved' ? (isDarkMode ? 'bg-green-500/20 text-green-400' : 'bg-green-100 text-green-700') :
                          refund.status === 'completed' ? (isDarkMode ? 'bg-emerald-500/20 text-emerald-400' : 'bg-emerald-100 text-emerald-700') :
                          refund.status === 'pending' ? (isDarkMode ? 'bg-yellow-500/20 text-yellow-400' : 'bg-yellow-100 text-yellow-700') :
                          (isDarkMode ? 'bg-red-500/20 text-red-400' : 'bg-red-100 text-red-700')
                        }`}>{refund.status}</span>
                      </td>
                      <td className={`px-4 py-3 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>{formatDateDisplay(refund.created_at)}</td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );

  // Render Messages Section
  const renderMessages = () => (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Messages</h2>
          <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-sm`}>Communicate with administrators</p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
        {/* Conversation List */}
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'} border rounded-lg overflow-hidden ${selectedConversation ? 'hidden lg:block' : ''}`}>
          <div className={`px-4 py-3 border-b ${isDarkMode ? 'border-gray-800' : 'border-gray-200'}`}>
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Conversations</h3>
          </div>
          <div className="max-h-[68vh] overflow-y-auto">
            {messagesLoading ? (
              <div className="py-8 text-center"><LoadingSpinner /></div>
            ) : messages.length === 0 ? (
              <div className="py-8 text-center">
                <ChatBubbleLeftRightIcon className={`h-8 w-8 mx-auto mb-2 ${isDarkMode ? 'text-gray-600' : 'text-gray-300'}`} />
                <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>No admin contacts available</p>
              </div>
            ) : (
              messages.map((contact, idx) => {
                // Admin contacts returns user objects directly
                const contactId = contact.id || contact.sender_id;
                const contactUser = contact.sender_id ? (contact.sender_id === user?.id ? contact.receiver : contact.sender) : contact;
                const contactUserId = contactUser?.id || contactId;
                const displayName = contactUser ? `${contactUser.first_name || ''} ${contactUser.last_name || ''}`.trim() : 'Admin';
                return (
                  <button
                    key={idx}
                    onClick={() => {
                      setSelectedConversation({ id: contactUserId, ...contactUser });
                      loadConversation(contactUserId);
                    }}
                    className={`w-full text-left px-4 py-3 border-b transition-colors ${
                      selectedConversation?.id === contactUserId
                        ? (isDarkMode ? 'bg-amber-500/10 border-amber-500/20' : 'bg-amber-50 border-amber-200')
                        : (isDarkMode ? 'border-gray-800 hover:bg-gray-800/50' : 'border-gray-100 hover:bg-gray-50')
                    }`}
                  >
                    <div className="flex items-center gap-2">
                      <div className="w-8 h-8 bg-gradient-to-br from-amber-500 to-amber-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        {contactUser?.first_name?.[0] || '?'}{contactUser?.last_name?.[0] || ''}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className={`text-sm font-medium truncate ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                          {displayName}
                        </p>
                        <p className={`text-xs truncate ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                          {contactUser?.role || contact.role || 'Admin'}
                        </p>
                      </div>
                    </div>
                  </button>
                );
              })
            )}
          </div>
        </div>

        {/* Conversation Detail */}
        <div className={`lg:col-span-2 ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'} border rounded-lg overflow-hidden flex flex-col h-[68vh] min-h-[32rem]`}>
          {!selectedConversation ? (
            <div className="flex-1 flex items-center justify-center">
              <div className="text-center">
                <EnvelopeIcon className={`h-12 w-12 mx-auto mb-3 ${isDarkMode ? 'text-gray-600' : 'text-gray-300'}`} />
                <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Select a conversation to start messaging</p>
              </div>
            </div>
          ) : (
            <>
              {/* Conversation Header */}
              <div className={`px-4 py-3 border-b ${isDarkMode ? 'border-gray-800' : 'border-gray-200'} flex items-center gap-2`}>
                <button onClick={() => setSelectedConversation(null)} className="lg:hidden p-1 rounded hover:bg-gray-700">
                  <ChevronLeftIcon className="h-5 w-5" />
                </button>
                <div className="w-8 h-8 bg-gradient-to-br from-amber-500 to-amber-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                  {selectedConversation?.first_name?.[0] || '?'}
                </div>
                <div>
                  <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
                    {selectedConversation?.first_name || ''} {selectedConversation?.last_name || ''}
                  </p>
                  <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{selectedConversation?.role || ''}</p>
                </div>
              </div>

              {/* Messages */}
              <div className="flex-1 min-h-0 overflow-y-auto p-4 space-y-3">
                {conversationMessages.length === 0 ? (
                  <p className={`text-xs text-center ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>No messages yet. Start the conversation!</p>
                ) : (
                  conversationMessages.map((msg, i) => {
                    const isOwn = msg.sender_id === user?.id;
                    return (
                      <div key={i} className={`flex ${isOwn ? 'justify-end' : 'justify-start'}`}>
                        <div className={`max-w-[75%] rounded-lg px-3 py-2 text-sm ${
                          isOwn
                            ? 'bg-amber-600 text-white'
                            : (isDarkMode ? 'bg-gray-800 text-gray-200' : 'bg-gray-100 text-gray-800')
                        }`}>
                          <p className="whitespace-pre-wrap break-words">{msg.message}</p>
                          <p className={`text-[10px] mt-1 ${isOwn ? 'text-amber-200' : (isDarkMode ? 'text-gray-500' : 'text-gray-400')}`}>
                            {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          </p>
                        </div>
                      </div>
                    );
                  })
                )}
              </div>

              {/* Message Input */}
              <div className={`px-4 py-3 border-t ${isDarkMode ? 'border-gray-800' : 'border-gray-200'}`}>
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={newMessage}
                    onChange={(e) => setNewMessage(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && !e.shiftKey && handleSendMessage()}
                    placeholder="Type a message..."
                    className={`flex-1 border px-3 py-2 rounded text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white placeholder-gray-500' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'}`}
                  />
                  <button
                    onClick={handleSendMessage}
                    disabled={sendingMessage || !newMessage.trim()}
                    className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                  >
                    {sendingMessage ? (
                      <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    ) : (
                      <PaperAirplaneIcon className="h-4 w-4" />
                    )}
                  </button>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );

  // Render Notifications Section
  const renderNotifications = () => (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Notifications</h2>
          <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-500'} text-sm`}>Stay updated on appointments, payments, and system alerts</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={async () => {
              try {
                await axios.put('/api/notifications/mark-all-read');
                loadNotifications();
                if (window?.showToast) window.showToast('Notifications', 'All marked as read', 'success');
              } catch (err) { console.error(err); }
            }}
            className={`px-3 py-2 rounded text-xs font-medium transition-colors ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}`}
          >
            Mark All Read
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className={`flex gap-2 border-b ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
        {['unread', 'all'].map(tab => (
          <button
            key={tab}
            onClick={() => setNotificationsTab(tab)}
            className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors capitalize ${
              notificationsTab === tab
                ? `border-amber-500 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`
                : `border-transparent ${isDarkMode ? 'text-gray-400 hover:text-amber-300' : 'text-gray-500 hover:text-amber-500'}`
            }`}
          >{tab}</button>
        ))}
      </div>

      {/* Notifications List */}
      <div className="space-y-2">
        {notificationsLoading ? (
          <div className="py-8 text-center"><LoadingSpinner /></div>
        ) : notificationsList.length === 0 ? (
          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'} border rounded-lg p-8 text-center`}>
            <BellIcon className={`h-10 w-10 mx-auto mb-3 ${isDarkMode ? 'text-gray-600' : 'text-gray-300'}`} />
            <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>No {notificationsTab === 'unread' ? 'unread ' : ''}notifications</p>
          </div>
        ) : (
          notificationsList.map(notification => (
            <div key={notification.id} className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20 hover:border-amber-500/40' : 'bg-white border-gray-200 hover:border-amber-300'} border rounded-lg p-4 transition-all flex items-start gap-3`}>
              <div className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${isDarkMode ? 'bg-amber-500/10' : 'bg-amber-50'}`}>
                <BellIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
              </div>
              <div className="flex-1 min-w-0">
                <p className={`text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{notification.title}</p>
                <p className={`text-xs mt-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>{notification.message}</p>
                <p className={`text-[10px] mt-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>{new Date(notification.created_at).toLocaleString()}</p>
              </div>
              {!notification.read_at && (
                <button
                  onClick={async () => {
                    try {
                      await axios.put(`/api/notifications/${notification.id}/read`);
                      loadNotifications();
                    } catch (err) { console.error(err); }
                  }}
                  className={`flex-shrink-0 px-2 py-1 rounded text-xs transition-colors ${isDarkMode ? 'text-green-400 hover:bg-green-500/10' : 'text-green-600 hover:bg-green-50'}`}
                >
                  Mark read
                </button>
              )}
            </div>
          ))
        )}
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
      case 'refunds':
        return renderRefunds();
      case 'messages':
        return renderMessages();
      case 'notifications':
        return renderNotifications();
      default:
        return renderDashboard();
    }
  };

  return (
    <div className={`min-h-screen ${isDarkMode ? 'bg-gray-950' : 'bg-gray-50'} flex flex-col lg:h-screen transition-colors duration-300`}>
      {/* Mobile Header */}
      <div className={`lg:hidden fixed top-0 left-0 right-0 z-40 flex items-center justify-between px-4 py-3 ${isDarkMode ? 'bg-gray-950/95 backdrop-blur-sm border-gray-800' : 'bg-white/95 backdrop-blur-sm border-gray-200'} border-b transition-colors duration-300`}>
        <div className="w-10"></div>
        <div className="flex items-center gap-2">
          <div className="w-7 h-7 bg-gradient-to-br from-amber-500 to-amber-600 rounded-lg flex items-center justify-center">
            <BuildingLibraryIcon className="h-3.5 w-3.5 text-white" />
          </div>
          <span className={`${isDarkMode ? 'text-white' : 'text-gray-900'} font-bold text-base`}>Cashier Portal</span>
        </div>
        <div className="flex items-center gap-1">
          <ThemeToggle />
          <NotificationBell onViewAll={() => { setActiveSection('notifications'); setShowMobileSidebar(false); }} />
          <button
            onClick={() => setShowMobileSidebar(!showMobileSidebar)}
            className="text-amber-500 hover:text-amber-400 transition-colors p-2 rounded-lg hover:bg-amber-500/10"
          >
            <Bars3Icon className="h-6 w-6" />
          </button>
        </div>
      </div>

      {/* Mobile Sidebar Overlay */}
      {showMobileSidebar && (
        <div
          className="lg:hidden fixed inset-0 bg-black/50 z-30"
          onClick={() => setShowMobileSidebar(false)}
        ></div>
      )}

      {/* Sidebar */}
      <div className={`fixed inset-y-0 right-0 lg:right-auto lg:left-0 z-40 h-screen lg:h-screen ${isDarkMode ? 'bg-gray-950 border-gray-800' : 'bg-white border-gray-200'} border-l lg:border-l-0 lg:border-r shadow-xl flex-shrink-0 transition-all duration-300 lg:translate-x-0 ${
        showMobileSidebar ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'
      } ${isCollapsedDesktop ? 'lg:w-20' : 'w-64'}`}>
        <div className="flex flex-col h-full overflow-hidden">
          <div className={`flex items-center justify-between h-16 ${isDarkMode ? 'bg-gray-950 border-gray-800' : 'bg-white border-gray-200'} px-3 border-b transition-colors duration-300 flex-shrink-0`}>
            <div className="flex items-center space-x-2.5">
              <div className="w-9 h-9 bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl flex items-center justify-center shadow-lg shadow-amber-500/20">
                <BuildingLibraryIcon className="h-4.5 w-4.5 text-white" />
              </div>
              <span className={`text-sm lg:text-base font-bold ${isDarkMode ? 'text-white' : 'text-gray-900'} transition-colors duration-300 truncate hidden lg:inline ${isCollapsedDesktop ? 'lg:hidden' : ''}`}>CASHIER</span>
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
                                : isDarkMode
                                  ? 'text-gray-400 border-transparent hover:bg-amber-500/5 hover:text-amber-300 hover:border-amber-500/20'
                                  : 'text-gray-500 border-transparent hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200'
                            } ${isCollapsedDesktop ? 'lg:justify-center lg:px-2' : ''}`}
                            title={isCollapsedDesktop ? subItem.name : ''}
                          >
                            <div className="flex items-center min-w-0">
                              <subItem.icon className={`h-4 w-4 flex-shrink-0 transition-colors ${
                                activeSection === subItem.key ? 'text-amber-400' : isDarkMode ? 'text-gray-500 group-hover:text-amber-400' : 'text-gray-400 group-hover:text-amber-500'
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
                      : isDarkMode
                        ? 'text-gray-400 border-transparent hover:bg-amber-500/5 hover:text-amber-300 hover:border-amber-500/20'
                        : 'text-gray-500 border-transparent hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200'
                  } ${isCollapsedDesktop ? 'lg:justify-center lg:px-2' : ''}`}
                  title={isCollapsedDesktop ? item.name : ''}
                >
                  <div className="flex items-center min-w-0">
                    <item.icon className={`h-4 w-4 flex-shrink-0 transition-colors ${
                      activeSection === item.key ? 'text-amber-400' : isDarkMode ? 'text-gray-500 group-hover:text-amber-400' : 'text-gray-400 group-hover:text-amber-500'
                    } ${!isCollapsedDesktop ? 'mr-2' : ''}`} />
                    {!isCollapsedDesktop && <span className="truncate">{item.name}</span>}
                  </div>
                </button>
              );
            })}
            
            {/* Logout */}
            <button
              onClick={() => setShowLogoutModal(true)}
              className={`w-full flex items-center justify-center lg:justify-start px-2 lg:px-2.5 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 border group ${isDarkMode ? 'text-red-400 border-transparent hover:bg-red-500/10 hover:border-red-500/30' : 'text-white bg-red-600 border-red-700 hover:bg-red-700 hover:border-red-800'}`}
              title={isCollapsedDesktop ? 'Logout' : ''}
            >
              <div className="flex items-center min-w-0">
                <XCircleIcon className={`h-4 w-4 flex-shrink-0 ${!isCollapsedDesktop ? 'mr-2' : ''}`} />
                {!isCollapsedDesktop && <span className="truncate">Logout</span>}
              </div>
            </button>
          </nav>

          <div className={`p-2 lg:p-3 border-t ${isDarkMode ? 'border-gray-800' : 'border-gray-200'} flex-shrink-0 transition-all duration-300 ${isCollapsedDesktop ? 'lg:flex lg:items-center lg:justify-center' : ''}`}>
              {/* Theme toggle */}
              <button
                onClick={toggleTheme}
                className={`w-full flex items-center justify-center lg:justify-start px-2 lg:px-2.5 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 border group ${isDarkMode ? 'text-gray-400 border-transparent hover:bg-amber-500/5 hover:text-amber-300 hover:border-amber-500/20' : 'text-gray-500 border-transparent hover:bg-amber-50 hover:text-amber-600 hover:border-amber-200'} ${isCollapsedDesktop ? 'lg:justify-center lg:px-2' : ''}`}
                title={isCollapsedDesktop ? (isDarkMode ? 'Light Mode' : 'Dark Mode') : ''}
              >
                <div className="flex items-center min-w-0">
                  {isDarkMode ? (
                    <SunIcon className={`h-4 w-4 flex-shrink-0 ${!isCollapsedDesktop ? 'mr-2' : ''}`} />
                  ) : (
                    <MoonIcon className={`h-4 w-4 flex-shrink-0 ${!isCollapsedDesktop ? 'mr-2' : ''}`} />
                  )}
                  {!isCollapsedDesktop && <span className="truncate">{isDarkMode ? 'Light Mode' : 'Dark Mode'}</span>}
                </div>
              </button>
            </div>
        </div>
      </div>

      {/* Main Content */}
      <div className={`flex-1 flex flex-col min-w-0 mt-16 lg:mt-0 lg:h-screen lg:overflow-y-auto scrollbar-hide transition-all duration-300 ${isCollapsedDesktop ? 'lg:ml-20' : 'lg:ml-64'}`}>
        {/* Header */}
        <header className={`${isDarkMode ? 'bg-gray-900/95 backdrop-blur-sm border-gray-800' : 'bg-white/95 backdrop-blur-sm border-gray-200'} border-b flex-shrink-0 sticky top-0 z-30 transition-colors duration-300`}>
          <div className="flex justify-between items-center px-4 sm:px-5 lg:px-6 py-3 lg:py-3.5">
            <div className="flex items-center space-x-3 min-w-0">
              <div>
                <h1 className={`text-base lg:text-lg font-bold ${isDarkMode ? 'text-white' : 'text-gray-900'} transition-colors duration-300 capitalize truncate`}>
                  {activeSection.replace('-', ' ')}
                </h1>
                <p className={`${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-0.5 text-xs transition-colors duration-300 hidden sm:block`}>
                  {new Date().toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' })}
                </p>
              </div>
            </div>
            <div className="flex-shrink-0 flex items-center gap-2">
              <div className={`hidden md:inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold ${
                cashierSyncStatus === 'offline'
                  ? (isDarkMode ? 'border-red-500/30 bg-red-500/10 text-red-200' : 'border-red-200 bg-red-50 text-red-700')
                  : cashierSyncStatus === 'reconnecting' || cashierSyncStatus === 'syncing'
                    ? (isDarkMode ? 'border-amber-500/30 bg-amber-500/10 text-amber-100' : 'border-amber-200 bg-amber-50 text-amber-700')
                    : (isDarkMode ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-100' : 'border-emerald-200 bg-emerald-50 text-emerald-700')
              }`}>
                {cashierSyncStatus === 'offline' ? (
                  <XCircleIcon className="h-3.5 w-3.5" />
                ) : cashierSyncStatus === 'reconnecting' || cashierSyncStatus === 'syncing' ? (
                  <ArrowPathIcon className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <CheckCircleIcon className="h-3.5 w-3.5" />
                )}
                <span>
                  {cashierSyncStatus === 'offline'
                    ? 'Offline'
                    : cashierSyncStatus === 'reconnecting'
                      ? 'Reconnecting'
                      : cashierSyncStatus === 'syncing'
                        ? 'Syncing'
                        : `Live ${formatRelativeSync(lastCashierSectionSync || lastDashboardSync)}`}
                </span>
              </div>
              <ThemeToggle />
              <NotificationBell onViewAll={() => setActiveSection('notifications')} />
              <select
                value={timeframe}
                onChange={(e) => setTimeframe(e.target.value)}
                className={`${isDarkMode ? 'bg-gray-800 border-gray-700 text-gray-300' : 'bg-gray-50 border-gray-200 text-gray-700'} border text-xs px-3 py-1.5 rounded-lg transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500`}
              >
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
              </select>
            </div>
          </div>
        </header>

        {/* Main Content Area */}
        <main className="flex-1 p-4 lg:p-6 scrollbar-hide text-sm">
          {renderContent()}
        </main>
      </div>

      {/* Modals */}
      <LogoutModal
        isOpen={showLogoutModal}
        onClose={() => !isLoggingOut && setShowLogoutModal(false)}
        onConfirm={handleLogout}
        loading={isLoggingOut}
        isDarkMode={isDarkMode}
      />

      <ReceiptModal
        isOpen={showReceiptModal}
        onClose={() => setShowReceiptModal(false)}
        receiptData={currentReceipt}
        isDarkMode={isDarkMode}
      />

      <PaymentWaitingModal
        isOpen={showPaymentWaitingModal}
        onClose={() => {
          setShowPaymentWaitingModal(false);
          setShowReceiptModal(true);
        }}
        onCancel={handleCancelOnlinePayment}
        appointment={onlinePaymentAppointment}
        checkoutUrl={onlineCheckoutUrl}
        paymentStatus={onlinePaymentStatus}
        isDarkMode={isDarkMode}
      />

      <ActionLogModal
        isOpen={showActionLogModal}
        onClose={() => setShowActionLogModal(false)}
        logData={selectedActionLog}
        isDarkMode={isDarkMode}
      />
      
      <CompletionConfirmationModal
        isOpen={showCompletionConfirmation}
        onClose={() => {
          setShowCompletionConfirmation(false);
          setIsCompletionLoading(false);
        }}
        appointment={confirmAppointment}
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
        inKindEstimatedValue={inKindEstimatedValue}
        isDarkMode={isDarkMode}
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
        isDarkMode={isDarkMode}
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
        isDarkMode={isDarkMode}
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
              inKindEstimatedValue={inKindEstimatedValue}
              setInKindEstimatedValue={setInKindEstimatedValue}
              selectedDiscounts={selectedDiscounts}
              setSelectedDiscounts={setSelectedDiscounts}
              discountRates={discountRates}
              calculateDiscount={calculateDiscount}
              isDarkMode={isDarkMode}
              onRequestRefund={(apt) => {
                setViewModalAppointment(null);
                openCashierRefundModal(apt);
              }}
              onMarkNoShow={handleMarkNoShow}
              onComplete={() => {
                setConfirmAppointment(viewModalAppointment);
                setShowCompletionConfirmation(true);
                setViewModalAppointment(null);
              }}
            />
          )}

          {/* Cashier Refund Request Modal */}
          {showRefundRequestModal && refundTargetAppointment && (
            <div className="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4">
              <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'} border rounded-lg shadow-xl w-full max-w-md`}>
                <div className={`flex items-center justify-between p-4 border-b ${isDarkMode ? 'border-gray-800' : 'border-gray-200'}`}>
                  <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Request Refund</h3>
                  <button onClick={() => { setShowRefundRequestModal(false); setRefundTargetAppointment(null); }} className={`p-1 rounded ${isDarkMode ? 'text-gray-400 hover:text-amber-300' : 'text-gray-500 hover:text-amber-600'}`}>
                    <XMarkIcon className="h-5 w-5" />
                  </button>
                </div>
                <form onSubmit={handleCashierRefundRequest} className="p-4 space-y-4">
                  <div className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'} border rounded p-3 text-sm`}>
                    <div className={`flex justify-between ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                      <span>Client</span>
                      <span className={isDarkMode ? 'text-amber-50' : 'text-gray-900'}>{refundTargetAppointment.user?.first_name} {refundTargetAppointment.user?.last_name}</span>
                    </div>
                    <div className={`flex justify-between mt-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                      <span>Service</span>
                      <span className={isDarkMode ? 'text-amber-50' : 'text-gray-900'}>{refundTargetAppointment.service?.name || 'N/A'}</span>
                    </div>
                    <div className={`flex justify-between mt-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>
                      <span>Amount Paid</span>
                      <span className={`font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>₱{Number(refundTargetAppointment.payment_amount || 0).toFixed(2)}</span>
                    </div>
                  </div>

                  <div>
                    <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>Refund Amount</label>
                    <input
                      type="number"
                      step="0.01"
                      min="0.01"
                      max={refundTargetAppointment.payment_amount || 0}
                      value={refundFormData.refund_amount}
                      onChange={(e) => setRefundFormData(prev => ({ ...prev, refund_amount: e.target.value }))}
                      className={`w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                      required
                    />
                  </div>

                  <div>
                    <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>Reason</label>
                    <select
                      value={refundFormData.reason}
                      onChange={(e) => setRefundFormData(prev => ({ ...prev, reason: e.target.value }))}
                      className={`w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                    >
                      {refundReasons.length > 0 ? (
                        refundReasons.filter(r => r.type === 'request').map(r => (
                          <option key={r.key} value={r.key}>{r.label}</option>
                        ))
                      ) : (
                        <>
                          <option value="customer_request">Customer Request</option>
                          <option value="service_not_provided">Service Not Provided</option>
                          <option value="duplicate_payment">Duplicate Payment</option>
                          <option value="service_cancellation">Service Cancellation</option>
                          <option value="poor_service">Poor Service Quality</option>
                          <option value="other">Other Reason</option>
                        </>
                      )}
                    </select>
                  </div>

                  <div>
                    <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>Description (optional)</label>
                    <textarea
                      value={refundFormData.description}
                      onChange={(e) => setRefundFormData(prev => ({ ...prev, description: e.target.value }))}
                      rows={3}
                      placeholder="Additional details about the refund..."
                      className={`w-full px-3 py-2 border rounded text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white placeholder-gray-500' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-400'}`}
                    />
                  </div>

                  {refundRequestError && (
                    <div className="text-red-500 text-sm bg-red-500/10 border border-red-500/20 rounded p-2">{refundRequestError}</div>
                  )}

                  <div className="flex gap-2">
                    <button
                      type="submit"
                      disabled={refundRequestLoading}
                      className="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white rounded text-sm font-medium transition-colors"
                    >
                      {refundRequestLoading ? 'Submitting...' : 'Submit Refund Request'}
                    </button>
                    <button
                      type="button"
                      onClick={() => { setShowRefundRequestModal(false); setRefundTargetAppointment(null); }}
                      className={`px-4 py-2 ${isDarkMode ? 'bg-gray-700 hover:bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-800'} rounded text-sm transition-colors`}
                    >
                      Cancel
                    </button>
                  </div>
                </form>
              </div>
            </div>
          )}
          

    </div>
  );
};

export default CashierDashboard;
