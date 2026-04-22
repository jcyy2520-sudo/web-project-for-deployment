import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useApi, clearApiCache } from '../hooks/useApi';
import axios from 'axios';
import { 
  UserGroupIcon, 
  CalendarIcon, 
  DocumentTextIcon,
  ChartBarIcon,
  HomeIcon,
  CogIcon,
  Cog6ToothIcon,
  ArchiveBoxIcon,
  TrashIcon,
  PencilIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  ChevronUpDownIcon,
  XMarkIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  EyeIcon,
  EnvelopeIcon,
  PhoneIcon,
  MapPinIcon,
  ExclamationTriangleIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  FunnelIcon,
  ArrowPathIcon,
  ChartPieIcon,
  BuildingLibraryIcon,
  DocumentChartBarIcon,
  UsersIcon,
  CalendarDaysIcon,
  DocumentArrowDownIcon,
  UserCircleIcon,
  ShieldCheckIcon,
  ChatBubbleLeftRightIcon,
  ChatBubbleBottomCenterTextIcon,
  Bars3Icon,
  UserMinusIcon,
  StarIcon,
  MegaphoneIcon,
  NoSymbolIcon,
  GlobeAltIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  BoltIcon
} from '@heroicons/react/24/outline';
import AdminSettings from '../components/admin/AdminSettings';
import ThemeToggle from '../components/ui/ThemeToggle';
import UserStatusManagement from '../components/admin/UserStatusManagement';
import LoadingSpinner from '../components/LoadingSpinner';
import { formatServiceName, formatPrice } from '../utils/format';
import AdminMessages from '../components/admin/AdminMessages';
import AdminActionLogs from '../components/admin/AdminActionLogs';
import AdminServices from '../components/admin/AdminServices';
import AdminAnalyticsDashboard from '../components/admin/AdminAnalyticsDashboard';
import AdminFeedback from '../components/admin/AdminFeedback';
import DocumentManagement from '../components/admin/DocumentManagement';
import DeclineModal from '../components/modals/DeclineModal';
import CompletionModal from '../components/modals/CompletionModal';
import AffectedAppointmentsModal from '../components/admin/AffectedAppointmentsModal';
import CancelBulkAppointmentsModal from '../components/admin/CancelBulkAppointmentsModal';
import AppointmentCalendarModal from '../components/admin/AppointmentCalendarModal';
import AdminRefundManagement from '../components/admin/AdminRefundManagement';
import AdminAppeals from '../components/admin/AdminAppeals';
import AdminAnnouncements from '../components/admin/AdminAnnouncements';


// Animated counter hook
const useAnimatedCount = (target, duration = 1200) => {
  const [count, setCount] = useState(0);
  const prevTarget = useRef(0);
  useEffect(() => {
    const start = prevTarget.current;
    const end = typeof target === 'number' ? target : parseInt(String(target).replace(/[^0-9]/g, '')) || 0;
    if (start === end) { setCount(end); return; }
    prevTarget.current = end;
    const startTime = performance.now();
    const animate = (now) => {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // Ease out cubic
      const eased = 1 - Math.pow(1 - progress, 3);
      setCount(Math.round(start + (end - start) * eased));
      if (progress < 1) requestAnimationFrame(animate);
    };
    requestAnimationFrame(animate);
  }, [target, duration]);
  return count;
};

// Mini sparkline SVG (inline trend in stat cards)
const Sparkline = ({ data, color = '#f59e0b', height = 28, width = 80 }) => {
  const values = useMemo(() => data.map(d => Number(d.value) || 0), [data]);
  if (values.length < 2) return null;
  const max = Math.max(...values, 1);
  const min = Math.min(...values, 0);
  const range = max - min || 1;
  const points = values.map((v, i) => {
    const x = (i / (values.length - 1)) * width;
    const y = height - ((v - min) / range) * (height - 4) - 2;
    return `${x},${y}`;
  }).join(' ');
  const fillPoints = `0,${height} ${points} ${width},${height}`;
  return (
    <svg width={width} height={height} className="flex-shrink-0">
      <defs>
        <linearGradient id={`spark-${color.replace('#','')}`} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity="0.3" />
          <stop offset="100%" stopColor={color} stopOpacity="0" />
        </linearGradient>
      </defs>
      <polygon points={fillPoints} fill={`url(#spark-${color.replace('#','')})`} />
      <polyline points={points} fill="none" stroke={color} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
};

// Chart Components
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
          <div key={index} className="flex items-center justify-between group">
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
                  stroke={'var(--borders)'}
                  strokeWidth="2"
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
      <div className="mt-3 space-y-1">
        {safeData.map((item, index) => (
          <div key={index} className={`flex items-center text-xs group cursor-pointer ${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-100'} p-1 rounded transition-colors`}>
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
  );
};

const LineChart = ({ data, title, color = 'amber', isDarkMode = true }) => {
  const [hoverIdx, setHoverIdx] = useState(null);
  const chartRef = useRef(null);
  const safeData = useMemo(() => 
    data.map(item => ({ ...item, value: Number(item.value) || 0 })), 
    [data]
  );
  const maxValue = Math.max(...safeData.map(item => item.value), 1);
  const pad = { top: 16, right: 10, bottom: 24, left: 10 };
  const W = 440, H = 180;
  const iW = W - pad.left - pad.right, iH = H - pad.top - pad.bottom;
  const bottom = pad.top + iH;

  const pts = useMemo(() => safeData.map((item, i) => {
    const x = pad.left + (safeData.length <= 1 ? iW / 2 : (i / (safeData.length - 1)) * iW);
    const y = pad.top + iH - (item.value / maxValue) * iH;
    return { x, y };
  }), [safeData, maxValue, iW, iH]);

  // Catmull-Rom to cubic bezier smooth curve
  const smoothPath = useMemo(() => {
    if (pts.length < 2) return '';
    if (pts.length === 2) return `M${pts[0].x},${pts[0].y}L${pts[1].x},${pts[1].y}`;
    const t = 0.35;
    let d = `M${pts[0].x},${pts[0].y}`;
    for (let i = 0; i < pts.length - 1; i++) {
      const p0 = pts[Math.max(i - 1, 0)];
      const p1 = pts[i];
      const p2 = pts[i + 1];
      const p3 = pts[Math.min(i + 2, pts.length - 1)];
      const cp1x = p1.x + (p2.x - p0.x) * t / 3;
      const cp1y = p1.y + (p2.y - p0.y) * t / 3;
      const cp2x = p2.x - (p3.x - p1.x) * t / 3;
      const cp2y = p2.y - (p3.y - p1.y) * t / 3;
      d += ` C${cp1x},${cp1y} ${cp2x},${cp2y} ${p2.x},${p2.y}`;
    }
    return d;
  }, [pts]);

  const areaPath = smoothPath ? `${smoothPath} L${pts[pts.length - 1].x},${bottom} L${pts[0].x},${bottom} Z` : '';
  const barW = safeData.length > 0 ? Math.max(4, (iW / safeData.length) * 0.45) : 6;

  const handleMouseMove = (e) => {
    if (!chartRef.current || pts.length === 0) return;
    const rect = chartRef.current.getBoundingClientRect();
    const xPx = e.clientX - rect.left;
    const pct = xPx / rect.width;
    const idx = Math.round(pct * (safeData.length - 1));
    setHoverIdx(Math.max(0, Math.min(safeData.length - 1, idx)));
  };

  return (
    <div className={`${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'} border rounded-xl shadow p-4 transition-all duration-300`}>
      <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-white' : 'text-gray-900'} mb-3 flex items-center`}>
        <ChartBarIcon className="h-4 w-4 mr-2" />
        {title}
      </h3>
      <div className="relative" ref={chartRef} onMouseMove={handleMouseMove} onMouseLeave={() => setHoverIdx(null)}>
        <svg viewBox={`0 0 ${W} ${H}`} className="w-full" preserveAspectRatio="xMidYMid meet" style={{ height: '180px' }}>
          <defs>
            <linearGradient id="lc-area-grad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={isDarkMode ? '#818cf8' : '#6366f1'} stopOpacity="0.22" />
              <stop offset="100%" stopColor={isDarkMode ? '#818cf8' : '#6366f1'} stopOpacity="0.01" />
            </linearGradient>
          </defs>
          {/* Horizontal grid + Y labels */}
          {[0, 0.25, 0.5, 0.75, 1].map((frac, i) => {
            const y = pad.top + iH * (1 - frac);
            return (
              <g key={i}>
                <line x1={pad.left} y1={y} x2={pad.left + iW} y2={y}
                  stroke={isDarkMode ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)'} strokeWidth="0.5" />
                <text x={pad.left - 2} y={y + 1} textAnchor="end" className="select-none"
                  fill={isDarkMode ? 'rgba(255,255,255,0.2)' : 'rgba(0,0,0,0.25)'} fontSize="7">{Math.round(maxValue * frac)}</text>
              </g>
            );
          })}
          {/* Vertical bars */}
          {pts.map((p, i) => (
            <rect key={`b-${i}`} x={p.x - barW / 2} y={p.y} width={barW} height={bottom - p.y}
              rx="2" fill={hoverIdx === i ? (isDarkMode ? 'rgba(129,140,248,0.28)' : 'rgba(99,102,241,0.20)') : (isDarkMode ? 'rgba(129,140,248,0.10)' : 'rgba(99,102,241,0.08)')}
              className="transition-all duration-150" />
          ))}
          {/* Area */}
          {areaPath && <path d={areaPath} fill="url(#lc-area-grad)" />}
          {/* Smooth line */}
          {smoothPath && (
            <path d={smoothPath} fill="none" stroke={isDarkMode ? '#818cf8' : '#6366f1'} strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
          )}
          {/* Dots */}
          {pts.map((p, i) => (
            <circle key={i} cx={p.x} cy={p.y} r={hoverIdx === i ? 4.5 : 3}
              fill={isDarkMode ? '#818cf8' : '#6366f1'} stroke={isDarkMode ? '#111827' : '#fff'} strokeWidth="2"
              className="transition-all duration-150 cursor-pointer" />
          ))}
          {/* Hover vertical guide line */}
          {hoverIdx !== null && pts[hoverIdx] && (
            <line x1={pts[hoverIdx].x} y1={pad.top} x2={pts[hoverIdx].x} y2={bottom}
              stroke={isDarkMode ? 'rgba(129,140,248,0.3)' : 'rgba(99,102,241,0.25)'} strokeWidth="1" strokeDasharray="3,3" />
          )}
        </svg>
        {/* Tooltip */}
        {hoverIdx !== null && safeData[hoverIdx] && pts[hoverIdx] && (
          <div className={`absolute pointer-events-none z-10 px-2.5 py-1.5 rounded-lg text-xs shadow-lg border ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-white border-gray-200 text-gray-900'}`}
            style={{ left: `${(pts[hoverIdx].x / W) * 100}%`, top: `${(pts[hoverIdx].y / H) * 100 - 18}%`, transform: 'translateX(-50%)' }}>
            <div className="font-semibold">{safeData[hoverIdx].label}</div>
            <div className={`${isDarkMode ? 'text-indigo-300' : 'text-indigo-600'} font-bold`}>{safeData[hoverIdx].value.toLocaleString()}</div>
          </div>
        )}
        {/* X-axis labels */}
        <div className="flex justify-between mt-1 px-1">
          {safeData.map((item, i) => (
            <span key={i} className={`text-[10px] ${hoverIdx === i ? (isDarkMode ? 'text-indigo-300 font-semibold' : 'text-indigo-600 font-semibold') : (isDarkMode ? 'text-gray-600' : 'text-gray-400')}`}>{item.label}</span>
          ))}
        </div>
      </div>
    </div>
  );
};

// Message Modal Component
const MessageModal = ({ isOpen, onClose, user, onSend, loading }) => {
  const [messageData, setMessageData] = useState({
    subject: '',
    message: '',
    type: 'general'
  });

  useEffect(() => {
    if (isOpen && user) {
      setMessageData({
        subject: '',
        message: '',
        type: 'general'
      });
    }
  }, [isOpen, user]);

  const handleSubmit = (e) => {
    e.preventDefault();
    if (messageData.subject && messageData.message) {
      onSend({
        ...messageData,
        userId: user.id,
        userName: `${user.first_name} ${user.last_name}`
      });
    }
  };

  if (!isOpen || !user) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-md max-h-[85vh] overflow-hidden flex flex-col transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 bg-gray-900 flex-shrink-0">
          <div className="flex items-center">
            <ChatBubbleLeftRightIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">
              Message to {user.first_name} {user.last_name}
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-amber-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1"
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="flex-1 min-h-0 overflow-y-auto p-4 space-y-3 flex flex-col">
          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Subject *
            </label>
            <input
              type="text"
              value={messageData.subject}
              onChange={(e) => setMessageData(prev => ({ ...prev, subject: e.target.value }))}
              className="w-full px-2 py-1.5 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-xs text-white placeholder-gray-400"
              disabled={loading}
              placeholder="Subject"
              required
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Type
            </label>
            <select
              value={messageData.type}
              onChange={(e) => setMessageData(prev => ({ ...prev, type: e.target.value }))}
              className="w-full px-2 py-1.5 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-xs text-white"
              disabled={loading}
            >
              <option value="general">General</option>
              <option value="appointment">Appointment</option>
              <option value="notification">Notification</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>

          <div className="flex-1 flex flex-col">
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Message *
            </label>
            <textarea
              value={messageData.message}
              onChange={(e) => setMessageData(prev => ({ ...prev, message: e.target.value }))}
              rows="4"
              className="flex-1 px-2 py-1.5 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-xs text-white placeholder-gray-400 resize-none"
              disabled={loading}
              placeholder="Message..."
              required
            />
          </div>

          {messageData.type === 'appointment' && (
            <div className="bg-amber-500/10 border border-amber-500/30 rounded p-2 flex-shrink-0">
              <div className="flex items-center gap-1">
                <ExclamationTriangleIcon className="h-3.5 w-3.5 text-amber-400 flex-shrink-0" />
                <span className="text-amber-200 text-xs">
                  Email notification will be sent for appointment-related messages
                </span>
              </div>
            </div>
          )}

          <div className="flex justify-end space-x-2 pt-3 border-t border-gray-700 flex-shrink-0">
            <button
              type="button"
              onClick={onClose}
              className="px-3 py-1.5 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-all duration-200 font-medium text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 disabled:opacity-50"
              disabled={loading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-3 py-1.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 shadow disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
              disabled={loading}
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  Sending...
                </>
              ) : (
                <>
                  <EnvelopeIcon className="h-3 w-3 mr-1" />
                  Send Message
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

// Logout Confirmation Modal
const LogoutConfirmationModal = ({ isOpen, onClose, onConfirm, loading, isDarkMode = true }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/30' : 'bg-white border-amber-300/40'} border rounded-lg shadow-xl w-full max-w-md transform animate-scaleIn`}>
        <div className="p-4">
          <div className="flex items-center mb-3">
            <div className={`p-2 rounded-lg ${isDarkMode ? 'bg-amber-500/20' : 'bg-amber-100'}`}>
              <ExclamationTriangleIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            </div>
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} ml-2`}>Confirm Logout</h3>
          </div>
          <p className={`${isDarkMode ? 'text-gray-300' : 'text-gray-700'} text-sm mb-4`}>Are you sure you want to logout from the admin dashboard?</p>
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

// AdminLoginModal removed - login should use the main auth flows if needed
// Modal Components
const ConfirmationModal = ({ isOpen, onClose, onConfirm, title, message, confirmText = "Delete", type = "danger", loading = false, isDarkMode = true }) => {
  if (!isOpen) return null;

  const buttonColors = {
    dark: {
      danger: "bg-red-600 hover:bg-red-700 focus:ring-red-500",
      primary: "bg-amber-600 hover:bg-amber-700 focus:ring-amber-500",
      warning: "bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500",
      success: "bg-green-600 hover:bg-green-700 focus:ring-green-500"
    },
    light: {
      danger: "bg-red-600 hover:bg-red-700 focus:ring-red-500",
      primary: "bg-amber-600 hover:bg-amber-700 focus:ring-amber-500",
      warning: "bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500",
      success: "bg-green-600 hover:bg-green-700 focus:ring-green-500"
    }
  };

  const icons = {
    danger: ExclamationTriangleIcon,
    warning: ExclamationTriangleIcon,
    primary: CheckCircleIcon,
    success: CheckCircleIcon
  };

  const IconComponent = icons[type];

  const getIconBg = () => {
    switch(type) {
      case 'danger': return isDarkMode ? 'bg-red-500/20' : 'bg-red-100';
      case 'warning': return isDarkMode ? 'bg-yellow-500/20' : 'bg-yellow-100';
      case 'success': return isDarkMode ? 'bg-green-500/20' : 'bg-green-100';
      default: return isDarkMode ? 'bg-amber-500/20' : 'bg-amber-100';
    }
  };

  const getIconColor = () => {
    switch(type) {
      case 'danger': return isDarkMode ? 'text-red-400' : 'text-red-600';
      case 'warning': return isDarkMode ? 'text-yellow-400' : 'text-yellow-600';
      case 'success': return isDarkMode ? 'text-green-400' : 'text-green-600';
      default: return isDarkMode ? 'text-amber-400' : 'text-amber-600';
    }
  };

  return (
    <div className={`fixed inset-0 flex items-center justify-center z-50 p-4 animate-fadeIn ${isDarkMode ? 'bg-black bg-opacity-70' : 'bg-black bg-opacity-40'}`}>
      <div className={`rounded-lg shadow-xl w-full max-w-md transform animate-scaleIn border-2 ${
        isDarkMode 
          ? 'bg-gray-900 border-amber-500/30' 
          : 'bg-white border-amber-400'
      }`}>
        <div className="p-4">
          <div className="flex items-center mb-3">
            <div className={`p-2 rounded-lg ${getIconBg()}`}>
              <IconComponent className={`h-5 w-5 ${getIconColor()}`} />
            </div>
            <h3 className={`text-sm font-semibold ml-2 ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>{title}</h3>
          </div>
          <p className={`text-sm mb-4 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{message}</p>
          <div className="flex justify-end space-x-2">
            <button
              onClick={onClose}
              disabled={loading}
              className={`px-3 py-2 rounded-lg transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 disabled:opacity-50 ${
                isDarkMode
                  ? 'border border-gray-600 text-gray-300 hover:bg-gray-800 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900'
                  : 'border-2 border-gray-300 text-gray-900 hover:bg-gray-100 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-white'
              }`}
            >
              Cancel
            </button>
            <button
              onClick={onConfirm}
              disabled={loading}
              className={`px-3 py-2 text-white rounded-lg transition-colors duration-200 font-medium text-sm focus:outline-none focus:ring-2 disabled:opacity-50 ${buttonColors[isDarkMode ? 'dark' : 'light'][type]} ${
                isDarkMode 
                  ? 'focus:ring-offset-2 focus:ring-offset-gray-900'
                  : 'focus:ring-offset-2 focus:ring-offset-white'
              }`}
            >
              {loading ? (
                <div className="flex items-center">
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  Processing...
                </div>
              ) : (
                confirmText
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

const UserFormModal = ({ isOpen, onClose, user, onSave, loading }) => {
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    role: 'client',
    password: ''
  });

  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (user && isOpen) {
      setFormData({
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        email: user.email || '',
        phone: user.phone || '',
        address: user.address || '',
        role: user.role || 'client',
        password: ''
      });
    } else if (isOpen) {
      setFormData({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        address: '',
        role: 'client',
        password: ''
      });
    }
    setErrors({});
  }, [user, isOpen]);

  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.first_name.trim()) newErrors.first_name = 'First name is required';
    if (!formData.last_name.trim()) newErrors.last_name = 'Last name is required';
    if (!formData.email.trim()) newErrors.email = 'Email is required';
    else if (!/\S+@\S+\.\S+/.test(formData.email)) newErrors.email = 'Email is invalid';
    
    if (!user && !formData.password) newErrors.password = 'Password is required';
    else if (!user && formData.password.length < 6) newErrors.password = 'Password must be at least 6 characters';

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (validateForm()) {
      onSave(formData);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900">
          <div className="flex items-center">
            <UserGroupIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">
              {user ? 'Edit User' : 'Add New User'}
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-amber-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1"
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                First Name *
              </label>
              <input
                type="text"
                value={formData.first_name}
                onChange={(e) => setFormData(prev => ({ ...prev, first_name: e.target.value }))}
                className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400 ${
                  errors.first_name ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
                }`}
                disabled={loading}
                placeholder="Enter first name"
              />
              {errors.first_name && (
                <p className="text-red-400 text-xs mt-1 flex items-center">
                  <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                  {errors.first_name}
                </p>
              )}
            </div>
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Last Name *
              </label>
              <input
                type="text"
                value={formData.last_name}
                onChange={(e) => setFormData(prev => ({ ...prev, last_name: e.target.value }))}
                className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400 ${
                  errors.last_name ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
                }`}
                disabled={loading}
                placeholder="Enter last name"
              />
              {errors.last_name && (
                <p className="text-red-400 text-xs mt-1 flex items-center">
                  <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                  {errors.last_name}
                </p>
              )}
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Email Address *
            </label>
            <input
              type="email"
              value={formData.email}
              onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
              className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400 ${
                errors.email ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
              }`}
              disabled={loading}
              placeholder="Enter email address"
            />
            {errors.email && (
              <p className="text-red-400 text-xs mt-1 flex items-center">
                <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                {errors.email}
              </p>
            )}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Phone Number
              </label>
              <input
                type="tel"
                value={formData.phone}
                onChange={(e) => setFormData(prev => ({ ...prev, phone: e.target.value }))}
                className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400"
                disabled={loading}
                placeholder="Enter phone number"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Role *
              </label>
              <select
                value={formData.role}
                onChange={(e) => setFormData(prev => ({ ...prev, role: e.target.value }))}
                className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white"
                disabled={loading}
              >
                <option value="client">Client</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Address
            </label>
            <textarea
              value={formData.address}
              onChange={(e) => setFormData(prev => ({ ...prev, address: e.target.value }))}
              rows="2"
              className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400 resize-none"
              disabled={loading}
              placeholder="Enter full address"
            />
          </div>

          {!user && (
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Password *
              </label>
              <input
                type="password"
                value={formData.password}
                onChange={(e) => setFormData(prev => ({ ...prev, password: e.target.value }))}
                className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400 ${
                  errors.password ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
                }`}
                disabled={loading}
                placeholder="Enter password"
              />
              {errors.password && (
                <p className="text-red-400 text-xs mt-1 flex items-center">
                  <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                  {errors.password}
                </p>
              )}
            </div>
          )}

          <div className="flex justify-end space-x-3 pt-4 border-t border-gray-700">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 hover:scale-105 disabled:opacity-50"
              disabled={loading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 shadow hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
              disabled={loading}
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  {user ? 'Updating...' : 'Creating...'}
                </>
              ) : (
                <>
                  {user ? 'Update User' : 'Create User'}
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

const AdminFormModal = ({ isOpen, onClose, admin, onSave, loading }) => {
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    role: 'admin'
  });

  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (admin && isOpen) {
      setFormData({
        first_name: admin.first_name || '',
        last_name: admin.last_name || '',
        email: admin.email || '',
        phone: admin.phone || '',
        password: '',
        role: 'admin'
      });
    } else if (isOpen) {
      setFormData({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        password: '',
        role: 'admin'
      });
    }
    setErrors({});
  }, [admin, isOpen]);

  const validateForm = () => {
    const newErrors = {};
    
    if (!formData.first_name.trim()) newErrors.first_name = 'First name is required';
    if (!formData.last_name.trim()) newErrors.last_name = 'Last name is required';
    if (!formData.email.trim()) newErrors.email = 'Email is required';
    else if (!/\S+@\S+\.\S+/.test(formData.email)) newErrors.email = 'Email is invalid';
    
    if (!admin && !formData.password) newErrors.password = 'Password is required';
    else if (!admin && formData.password.length < 6) newErrors.password = 'Password must be at least 6 characters';

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (validateForm()) {
      onSave(formData);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900">
          <div className="flex items-center">
            <ShieldCheckIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">
              {admin ? 'Edit Admin' : 'Add New Admin'}
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-amber-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1"
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                First Name *
              </label>
              <input
                type="text"
                value={formData.first_name}
                onChange={(e) => setFormData(prev => ({ ...prev, first_name: e.target.value }))}
                className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400 ${
                  errors.first_name ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
                }`}
                disabled={loading}
                placeholder="Enter first name"
              />
              {errors.first_name && (
                <p className="text-red-400 text-xs mt-1 flex items-center">
                  <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                  {errors.first_name}
                </p>
              )}
            </div>
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Last Name *
              </label>
              <input
                type="text"
                value={formData.last_name}
                onChange={(e) => setFormData(prev => ({ ...prev, last_name: e.target.value }))}
                className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400 ${
                  errors.last_name ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
                }`}
                disabled={loading}
                placeholder="Enter last name"
              />
              {errors.last_name && (
                <p className="text-red-400 text-xs mt-1 flex items-center">
                  <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                  {errors.last_name}
                </p>
              )}
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Email Address *
            </label>
            <input
              type="email"
              value={formData.email}
              onChange={(e) => setFormData(prev => ({ ...prev, email: e.target.value }))}
              className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400 ${
                errors.email ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
              }`}
              disabled={loading}
              placeholder="Enter email address"
            />
            {errors.email && (
              <p className="text-red-400 text-xs mt-1 flex items-center">
                <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                {errors.email}
              </p>
            )}
          </div>

          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Phone Number
            </label>
            <input
              type="tel"
              value={formData.phone}
              onChange={(e) => setFormData(prev => ({ ...prev, phone: e.target.value }))}
              className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400"
              disabled={loading}
              placeholder="Enter phone number"
            />
          </div>

          {!admin && (
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Password *
              </label>
              <input
                type="password"
                value={formData.password}
                onChange={(e) => setFormData(prev => ({ ...prev, password: e.target.value }))}
                className={`w-full px-3 py-2 bg-gray-800 border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400 ${
                  errors.password ? 'border-red-500' : 'border-gray-600 focus:border-amber-500'
                }`}
                disabled={loading}
                placeholder="Enter password"
              />
              {errors.password && (
                <p className="text-red-400 text-xs mt-1 flex items-center">
                  <ExclamationTriangleIcon className="h-3 w-3 mr-1" />
                  {errors.password}
                </p>
              )}
            </div>
          )}

          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Role *
            </label>
            <select
              value={formData.role}
              onChange={(e) => setFormData(prev => ({ ...prev, role: e.target.value }))}
              className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white"
              disabled={loading}
            >
              <option value="admin">Admin</option>
              <option value="cashier">Cashier</option>
            </select>
            <p className="text-xs mt-1 text-gray-400">
              {formData.role === 'admin' ? 'Full access to admin dashboard' : 'Access to cashier functions (payments, shift reports)'}
            </p>
          </div>

          <div className="flex justify-end space-x-3 pt-4 border-t border-gray-700">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 hover:scale-105 disabled:opacity-50"
              disabled={loading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 shadow hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
              disabled={loading}
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  {admin ? 'Updating...' : 'Creating...'}
                </>
              ) : (
                <>
                  {admin ? 'Update Admin' : 'Create Admin'}
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

const UnavailableDateModal = ({ isOpen, onClose, onSave, loading }) => {
  const [formData, setFormData] = useState({
    date: '',
    reason: '',
    all_day: true,
    start_time: '09:00',
    end_time: '17:00'
  });

  useEffect(() => {
    if (isOpen) {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      setFormData({
        date: tomorrow.toISOString().split('T')[0],
        reason: '',
        all_day: true,
        start_time: '09:00',
        end_time: '17:00'
      });
    }
  }, [isOpen]);

  const handleSubmit = (e) => {
    e.preventDefault();
    onSave(formData);
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900">
          <div className="flex items-center">
            <CalendarDaysIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">
              Add Unavailable Date
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-amber-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1"
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Date *
            </label>
            <input
              type="date"
              value={formData.date}
              onChange={(e) => setFormData(prev => ({ ...prev, date: e.target.value }))}
              className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white"
              disabled={loading}
              min={new Date().toISOString().split('T')[0]}
              required
            />
          </div>

          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Reason
            </label>
            <input
              type="text"
              value={formData.reason}
              onChange={(e) => setFormData(prev => ({ ...prev, reason: e.target.value }))}
              placeholder="e.g., Public Holiday, Maintenance, Vacation"
              className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400"
              disabled={loading}
            />
          </div>

          <div className="flex items-center p-3 bg-gray-800/50 rounded-lg border border-gray-600">
            <input
              type="checkbox"
              id="all_day"
              checked={formData.all_day}
              onChange={(e) => setFormData(prev => ({ ...prev, all_day: e.target.checked }))}
              className="w-3 h-3 text-amber-600 bg-gray-700 border-gray-600 rounded focus:ring-amber-500 focus:ring-2"
              disabled={loading}
            />
            <label htmlFor="all_day" className="ml-2 text-xs font-medium text-amber-50">
              All day
            </label>
          </div>

          {!formData.all_day && (
            <div className="grid grid-cols-2 gap-3 p-3 bg-gray-800/30 rounded-lg border border-gray-600">
              <div>
                <label className="block text-xs font-medium text-amber-50 mb-1">
                  Start Time
                </label>
                <input
                  type="time"
                  value={formData.start_time}
                  onChange={(e) => setFormData(prev => ({ ...prev, start_time: e.target.value }))}
                  className="w-full px-2 py-1 bg-gray-800 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-amber-500 transition-colors duration-200 text-sm text-white"
                  disabled={loading}
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-amber-50 mb-1">
                  End Time
                </label>
                <input
                  type="time"
                  value={formData.end_time}
                  onChange={(e) => setFormData(prev => ({ ...prev, end_time: e.target.value }))}
                  className="w-full px-2 py-1 bg-gray-800 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-amber-500 transition-colors duration-200 text-sm text-white"
                  disabled={loading}
                />
              </div>
            </div>
          )}

          <div className="flex justify-end space-x-3 pt-4 border-t border-gray-700">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 hover:scale-105 disabled:opacity-50"
              disabled={loading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 shadow hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
              disabled={loading}
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  Adding...
                </>
              ) : (
                'Add Unavailable Date'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

const StatusBadge = ({ status }) => {
  const statusConfig = {
    pending: {
      color: 'bg-amber-100 text-amber-800 border border-amber-200 dark:bg-amber-500/20 dark:text-amber-300 dark:border-amber-500/30',
      icon: ClockIcon,
      glow: 'shadow-amber-100'
    },
    approved: {
      color: 'bg-blue-100 text-blue-800 border border-blue-200 dark:bg-blue-500/20 dark:text-blue-300 dark:border-blue-500/30',
      icon: CheckCircleIcon,
      glow: 'shadow-blue-100'
    },
    completed: {
      color: 'bg-green-100 text-green-800 border border-green-200 dark:bg-green-500/20 dark:text-green-300 dark:border-green-500/30',
      icon: CheckCircleIcon,
      glow: 'shadow-green-100'
    },
    cancelled: {
      color: 'bg-red-100 text-red-800 border border-red-200 dark:bg-red-500/20 dark:text-red-300 dark:border-red-500/30',
      icon: XCircleIcon,
      glow: 'shadow-red-100'
    },
    declined: {
      color: 'bg-red-100 text-red-800 border border-red-200 dark:bg-red-500/20 dark:text-red-300 dark:border-red-500/30',
      icon: XCircleIcon,
      glow: 'shadow-red-100'
    },
    active: {
      color: 'bg-green-100 text-green-800 border border-green-200 dark:bg-green-500/20 dark:text-green-300 dark:border-green-500/30',
      icon: CheckCircleIcon,
      glow: 'shadow-green-100'
    },
    inactive: {
      color: 'bg-red-100 text-red-800 border border-red-200 dark:bg-red-500/20 dark:text-red-300 dark:border-red-500/30',
      icon: XCircleIcon,
      glow: 'shadow-red-100'
    }
  };
  
  const config = statusConfig[status] || statusConfig.pending;
  const IconComponent = config.icon;
  
  return (
    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${config.color} ${config.glow} shadow hover:scale-105 transition-transform duration-200`}>
      <IconComponent className="w-3 h-3 mr-1" />
      {status.charAt(0).toUpperCase() + status.slice(1)}
    </span>
  );
};

const UserDetailModal = ({ isOpen, onClose, user, onDeactivate, loading }) => {
  if (!isOpen || !user) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900">
          <div className="flex items-center">
            <UserGroupIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">
              User Details
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-amber-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1"
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <div className="p-4 space-y-4">
          <div className="flex items-center space-x-3 p-3 bg-gray-800/50 rounded-lg border border-gray-600">
            <div className="w-12 h-12 bg-amber-500 rounded-full flex items-center justify-center text-gray-900 text-sm font-bold shadow">
              {user.first_name?.charAt(0)}{user.last_name?.charAt(0)}
            </div>
            <div>
              <h4 className="text-sm font-bold text-amber-50">
                {user.first_name} {user.last_name}
              </h4>
              <p className="text-amber-400/70 text-xs capitalize">{user.role}</p>
              <StatusBadge status={user.is_active ? 'active' : 'inactive'} />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-3">
              <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                <label className="text-xs font-medium text-gray-400 mb-1 block">Contact Information</label>
                <div className="space-y-2">
                  <div className="flex items-center text-amber-50 text-sm">
                    <EnvelopeIcon className="h-3 w-3 mr-2 text-amber-400" />
                    <span>{user.email}</span>
                  </div>
                  {user.phone && (
                    <div className="flex items-center text-amber-50 text-sm">
                      <PhoneIcon className="h-3 w-3 mr-2 text-amber-400" />
                      <span>{user.phone}</span>
                    </div>
                  )}
                </div>
              </div>

              <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                <label className="text-xs font-medium text-gray-400 mb-1 block">Account Status</label>
                <div className="space-y-1">
                  <div className="flex justify-between">
                    <span className="text-gray-300 text-sm">Role</span>
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                      user.role === 'admin' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' :
                      'bg-green-500/20 text-green-300 border border-green-500/30'
                    }`}>
                      {user.role}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-300 text-sm">Status</span>
                    <StatusBadge status={user.is_active ? 'active' : 'inactive'} />
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-300 text-sm">Member Since</span>
                    <span className="text-amber-50 text-sm">
                      {user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div className="space-y-3">
              {user.address && (
                <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                  <label className="text-xs font-medium text-gray-400 mb-1 block">Address</label>
                  <div className="flex items-start text-amber-50">
                    <MapPinIcon className="h-3 w-3 mr-2 text-amber-400 mt-0.5 flex-shrink-0" />
                    <span className="text-xs">{user.address}</span>
                  </div>
                </div>
              )}

              <div className="p-3 bg-gray-800/30 rounded-lg border border-gray-600">
                <label className="text-xs font-medium text-gray-400 mb-1 block">Quick Actions</label>
                <div className="space-y-1">
                  <button className="w-full text-left p-1.5 rounded hover:bg-amber-500/10 transition-colors duration-200 text-amber-50 text-sm">
                    Send Email
                  </button>
                  <button className="w-full text-left p-1.5 rounded hover:bg-amber-500/10 transition-colors duration-200 text-amber-50 text-sm">
                    View Appointments
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

const QuickStats = ({ stats, onStatClick, isDarkMode = true, appointmentsByPeriod = [], revenueByPeriod = [] }) => {
  const totalUsers = useAnimatedCount(stats.totalUsers || 0);
  const totalAppointments = useAnimatedCount(stats.totalAppointments || 0);
  const pendingAppointments = useAnimatedCount(stats.pendingAppointments || 0);
  const revenue = useAnimatedCount(stats.revenue || 0);

  const statCards = [
    {
      name: 'Total Users',
      value: totalUsers.toLocaleString(),
      raw: stats.totalUsers || 0,
      icon: UsersIcon,
      gradient: 'from-purple-500 to-purple-600',
      glowColor: 'group-hover:shadow-purple-500/20',
      sparkColor: '#a855f7',
      sparkData: [], // No time series for users
      key: 'users'
    },
    {
      name: 'Total Appointments',
      value: totalAppointments.toLocaleString(),
      raw: stats.totalAppointments || 0,
      icon: CalendarDaysIcon,
      gradient: 'from-blue-500 to-blue-600',
      glowColor: 'group-hover:shadow-blue-500/20',
      sparkColor: '#3b82f6',
      sparkData: appointmentsByPeriod,
      key: 'appointments'
    },
    {
      name: 'Pending Actions',
      value: pendingAppointments.toLocaleString(),
      raw: stats.pendingAppointments || 0,
      icon: ClockIcon,
      gradient: 'from-amber-500 to-amber-600',
      glowColor: 'group-hover:shadow-amber-500/20',
      sparkColor: '#f59e0b',
      sparkData: [],
      key: 'appointments',
      pulse: (stats.pendingAppointments || 0) > 0
    },
    {
      name: 'Revenue',
      value: `₱${revenue.toLocaleString()}`,
      raw: stats.revenue || 0,
      icon: BuildingLibraryIcon,
      gradient: 'from-emerald-500 to-emerald-600',
      glowColor: 'group-hover:shadow-emerald-500/20',
      sparkColor: '#10b981',
      sparkData: revenueByPeriod,
      key: 'revenue'
    }
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      {statCards.map((card, index) => (
        <div
          key={index}
          onClick={() => onStatClick(card.key)}
          className={`relative overflow-hidden rounded-xl border p-4 cursor-pointer group transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg ${card.glowColor} ${
            isDarkMode ? 'bg-gray-900/80 border-gray-800 hover:border-gray-700' : 'bg-white border-gray-200 hover:border-gray-300'
          }`}
          style={{ animationDelay: `${index * 100}ms` }}
        >
          {/* Subtle gradient overlay on hover */}
          <div className={`absolute inset-0 bg-gradient-to-br ${card.gradient} opacity-0 group-hover:opacity-[0.04] transition-opacity duration-300`} />
          
          <div className="relative flex items-start justify-between">
            <div className="flex-1 min-w-0">
              <p className={`text-[11px] font-medium uppercase tracking-wider ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} group-hover:text-gray-400 transition-colors`}>
                {card.name}
              </p>
              <p className={`text-2xl font-bold mt-1 tracking-tight ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>
                {card.value}
              </p>
              {/* Sparkline */}
              {card.sparkData.length >= 2 && (
                <div className="mt-2">
                  <Sparkline data={card.sparkData} color={card.sparkColor} />
                </div>
              )}
            </div>
            <div className={`flex-shrink-0 p-2.5 rounded-xl bg-gradient-to-br ${card.gradient} shadow-lg transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3`}>
              <card.icon className="h-5 w-5 text-white" />
            </div>
          </div>

          {/* Pulse indicator for pending */}
          {card.pulse && (
            <div className="absolute top-3 right-3">
              <span className="relative flex h-2.5 w-2.5">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
              </span>
            </div>
          )}
        </div>
      ))}
    </div>
  );
};

const ReportModal = ({ isOpen, onClose, onGenerate, loading }) => {
  const [reportType, setReportType] = useState('appointments');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const [format, setFormat] = useState('pdf');

  useEffect(() => {
    if (isOpen) {
      const today = new Date();
      const lastMonth = new Date();
      lastMonth.setMonth(today.getMonth() - 1);
      
      setStartDate(lastMonth.toISOString().split('T')[0]);
      setEndDate(today.toISOString().split('T')[0]);
    }
  }, [isOpen]);

  const handleSubmit = (e) => {
    e.preventDefault();
    onGenerate({
      reportType,
      startDate,
      endDate,
      format
    });
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 p-4 animate-fadeIn">
      <div className="bg-gray-900 border border-amber-500/30 rounded-lg shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto transform animate-scaleIn">
        <div className="flex justify-between items-center p-4 border-b border-gray-700 sticky top-0 bg-gray-900">
          <div className="flex items-center">
            <DocumentArrowDownIcon className="h-5 w-5 text-amber-400 mr-2" />
            <h3 className="text-sm font-semibold text-amber-50">
              Generate Report
            </h3>
          </div>
          <button 
            onClick={onClose} 
            className="text-gray-400 hover:text-amber-400 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 rounded p-1"
            disabled={loading}
          >
            <XMarkIcon className="h-4 w-4" />
          </button>
        </div>
        
        <form onSubmit={handleSubmit} className="p-4 space-y-4">
          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Report Type *
            </label>
            <select
              value={reportType}
              onChange={(e) => setReportType(e.target.value)}
              className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white"
              disabled={loading}
            >
              <option value="appointments">Appointments Report</option>
              <option value="users">Users Report</option>
              <option value="revenue">Revenue Report</option>
              <option value="system">System Usage Report</option>
            </select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                Start Date *
              </label>
              <input
                type="date"
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="w-full px-2 py-1 bg-gray-800 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-amber-500 transition-colors duration-200 text-sm text-white"
                disabled={loading}
                required
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-amber-50 mb-1">
                End Date *
              </label>
              <input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="w-full px-2 py-1 bg-gray-800 border border-gray-600 rounded focus:outline-none focus:ring-2 focus:ring-amber-500 transition-colors duration-200 text-sm text-white"
                disabled={loading}
                required
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-amber-50 mb-1">
              Format *
            </label>
            <select
              value={format}
              onChange={(e) => setFormat(e.target.value)}
              className="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white"
              disabled={loading}
            >
              <option value="pdf">PDF</option>
              <option value="excel">Excel</option>
              <option value="csv">CSV</option>
            </select>
          </div>

          <div className="bg-amber-500/10 border border-amber-500/30 rounded p-3">
            <div className="flex items-center">
              <ExclamationTriangleIcon className="h-4 w-4 text-amber-400 mr-2" />
              <span className="text-amber-200 text-xs">
                The report will include all data within the selected date range.
              </span>
            </div>
          </div>

          <div className="flex justify-end space-x-3 pt-4 border-t border-gray-700">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 border border-gray-600 text-gray-300 rounded-lg hover:bg-gray-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 hover:scale-105 disabled:opacity-50"
              disabled={loading}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-gray-900 shadow hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed flex items-center"
              disabled={loading}
            >
              {loading ? (
                <>
                  <div className="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></div>
                  Generating...
                </>
              ) : (
                <>
                  <DocumentArrowDownIcon className="h-3 w-3 mr-1" />
                  Generate Report
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

// Main Admin Dashboard Component
const AdminDashboard = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const { callApi, loading: apiLoading, error, clearError } = useApi();
  
  // Ref for AdminMessages component to trigger refresh
  const adminMessagesRef = useRef(null);
  const timeframeRef = useRef('monthly');
  const [activeTab, setActiveTab] = useState('dashboard');
  const [snapshotCollapsed, setSnapshotCollapsed] = useState(false);
  const [showMobileSidebar, setShowMobileSidebar] = useState(false);
  const [isCollapsedDesktop, setIsCollapsedDesktop] = useState(false);
  const [openDropdowns, setOpenDropdowns] = useState({});
  const [stats, setStats] = useState({});
  const [timeframe, setTimeframe] = useState('monthly');
  const [users, setUsers] = useState([]);
  const [appointments, setAppointments] = useState([]);
  const [sales, setSales] = useState([]);
  const [unavailableDates, setUnavailableDates] = useState([]);
  const [admins, setAdmins] = useState([]);
  const [services, setServices] = useState([]);
  const [archivedUsers, setArchivedUsers] = useState([]);
  const [archivedAppointments, setArchivedAppointments] = useState([]);
  const [deactivatedUsers, setDeactivatedUsers] = useState([]);
  const [deactivatedAdmins, setDeactivatedAdmins] = useState([]);
  const [archiveTab, setArchiveTab] = useState('users');
  const [deactivatedTab, setDeactivatedTab] = useState('users');
  const [deactSearch, setDeactSearch] = useState('');
  const [deactRoleFilter, setDeactRoleFilter] = useState('all');
  const [restoreLoading, setRestoreLoading] = useState(null);
  const [unreadMessageCount, setUnreadMessageCount] = useState(0);
  const [archiveSearch, setArchiveSearch] = useState('');
  const [archiveRoleFilter, setArchiveRoleFilter] = useState('all');
  const [archiveSort, setArchiveSort] = useState('newest');
  const [archiveStatusFilter, setArchiveStatusFilter] = useState('all');
  const [archiveDateFrom, setArchiveDateFrom] = useState('');
  const [archiveDateTo, setArchiveDateTo] = useState('');
  
  // Modal states
  const [showUserModal, setShowUserModal] = useState(false);
  const [showAdminModal, setShowAdminModal] = useState(false);
  const [showUnavailableModal, setShowUnavailableModal] = useState(false);
  const [showAffectedModal, setShowAffectedModal] = useState(false);
  const [affectedAppointments, setAffectedAppointments] = useState([]);
  const [pendingUnavailableDate, setPendingUnavailableDate] = useState(null);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [showUserDetailModal, setShowUserDetailModal] = useState(false);
  const [showReportModal, setShowReportModal] = useState(false);
  const [reportStats, setReportStats] = useState(null);
  const [showMessageModal, setShowMessageModal] = useState(false);
  const [showLogoutModal, setShowLogoutModal] = useState(false);
  const [showDeclineModal, setShowDeclineModal] = useState(false);
  const [showCompletionModal, setShowCompletionModal] = useState(false);
  const [showCancelBulkModal, setShowCancelBulkModal] = useState(false);
  const [bulkCancelData, setBulkCancelData] = useState(null);
  const [selectedUser, setSelectedUser] = useState(null);
  const [selectedAdmin, setSelectedAdmin] = useState(null);
  const [itemToDelete, setItemToDelete] = useState(null);
  const [appointmentToDecline, setAppointmentToDecline] = useState(null);
  const [appointmentToComplete, setAppointmentToComplete] = useState(null);
  
  // Account action modal state (delete/block/deactivate with reason)
  const [showAccountActionModal, setShowAccountActionModal] = useState(false);
  const [accountActionUser, setAccountActionUser] = useState(null);
  const [accountActionType, setAccountActionType] = useState('deactivated');
  const [accountActionReason, setAccountActionReason] = useState('');
  const [accountActionLoading, setAccountActionLoading] = useState(false);
  const [accountActionError, setAccountActionError] = useState('');
  
  // Search and filter states
  const [searchTerm, setSearchTerm] = useState('');
  const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [appointmentPage, setAppointmentPage] = useState(1);
  const [messagePage, setMessagePage] = useState(1);
  const [archivedPage, setArchivedPage] = useState(1);
  const [salesPage, setSalesPage] = useState(1);
  const [itemsPerPage] = useState(8);
  const [appointmentsPerPage, setAppointmentsPerPage] = useState(10);
  const [salesPerPage] = useState(5);
  const [sortConfig, setSortConfig] = useState({ key: null, direction: 'asc' });
  const [appointmentSort, setAppointmentSort] = useState({ key: 'created_at', direction: 'desc' });
  const [statusFilter, setStatusFilter] = useState('all');
  const [roleFilter, setRoleFilter] = useState('all');
  const [appointmentTab, setAppointmentTab] = useState('all'); // 'all', 'pending', 'approved', 'declined'
  const [servicePerformancePage, setServicePerformancePage] = useState(1);
  const [servicePerformancePageSize] = useState(10);

  // Calendar date filter state (admin appointments section)
  const [showCalendarModal, setShowCalendarModal] = useState(false);
  const [dateFilter, setDateFilter] = useState(null);
  const [dateFilterLabel, setDateFilterLabel] = useState('All Appointments');

  // Data loaded tracking
  const [dataLoaded, setDataLoaded] = useState({
    dashboard: false,
    users: false,
    appointments: false,
    calendar: false,
    adminProfile: false,
    services: false,
    archive: false,
    deactivated: false,
    messages: true
  });

  const [dashboardLoading, setDashboardLoading] = useState(false);

  // Prevent any native form submit causing full page reloads
  useEffect(() => {
    const preventSubmit = (e) => {
      if (e && e.preventDefault) e.preventDefault();
    };
    document.addEventListener('submit', preventSubmit, true);
    return () => document.removeEventListener('submit', preventSubmit, true);
  }, []);

  // Ensure buttons without an explicit type won't act as submit (defensive fix)
  useEffect(() => {
    try {
      const buttons = Array.from(document.querySelectorAll('button')).filter(b => !b.hasAttribute('type'));
      buttons.forEach(b => b.setAttribute('type', 'button'));
    } catch (e) {
      // ignore in non-DOM environments
    }
  }, []);

  // Theme and settings state
  const [isDarkMode, setIsDarkMode] = useState(true);
  const [systemSettings, setSystemSettings] = useState({
    appName: 'Legal Ease',
    theme: 'dark',
    showLogo: true,
  });

  // Apply theme to document root and copy user theme when switching to light
  useEffect(() => {
    const root = document.documentElement;
    if (!isDarkMode) {
      // Attempt to copy user theme variables if available
      const savedUserSettings = localStorage.getItem('userSettings');
      if (savedUserSettings) {
        try {
          const parsed = JSON.parse(savedUserSettings);
          if (parsed.theme === 'light') {
            root.classList.remove('dark');
            root.classList.add('user-light');
            // Use same variables as user light mode defaults (fallbacks included)
            root.style.setProperty('--primary', parsed.primary || '#1D3557');
            root.style.setProperty('--secondary', parsed.secondary || '#3F6FA6');
            root.style.setProperty('--accent', parsed.accent || '#C96D02');
            root.style.setProperty('--background', parsed.background || '#ECF1F6');
            root.style.setProperty('--sidebar-bg', parsed.sidebarBg || '#F4F7FB');
            root.style.setProperty('--surface', parsed.surface || '#F8FAFD');
            root.style.setProperty('--text-primary', parsed.textPrimary || '#0F172A');
            root.style.setProperty('--text-secondary', parsed.textSecondary || '#475569');
            root.style.setProperty('--borders', parsed.borders || '#CFDAE6');
            root.style.setProperty('--success', parsed.success || '#16A34A');
            root.style.setProperty('--error', parsed.error || '#DC2626');
            root.style.backgroundColor = parsed.background || '#ECF1F6';
            root.style.color = parsed.textPrimary || '#0F172A';
          } else {
            // If user preference isn't light, apply sensible light defaults
            root.classList.remove('dark');
            root.classList.add('user-light');
            root.style.setProperty('--primary', '#1D3557');
            root.style.setProperty('--secondary', '#3F6FA6');
            root.style.setProperty('--accent', '#C96D02');
            root.style.setProperty('--background', '#ECF1F6');
            root.style.setProperty('--sidebar-bg', '#F4F7FB');
            root.style.setProperty('--surface', '#F8FAFD');
            root.style.setProperty('--text-primary', '#0F172A');
            root.style.setProperty('--text-secondary', '#475569');
            root.style.setProperty('--borders', '#CFDAE6');
            root.style.setProperty('--success', '#16A34A');
            root.style.setProperty('--error', '#DC2626');
            root.style.backgroundColor = '#ECF1F6';
            root.style.color = '#0F172A';
          }
        } catch (e) {
          // fallback light palette
          root.classList.remove('dark');
          root.classList.add('user-light');
          root.style.setProperty('--primary', '#1D3557');
          root.style.setProperty('--secondary', '#3F6FA6');
          root.style.setProperty('--accent', '#C96D02');
          root.style.setProperty('--background', '#ECF1F6');
          root.style.setProperty('--sidebar-bg', '#F4F7FB');
          root.style.setProperty('--surface', '#F8FAFD');
          root.style.setProperty('--text-primary', '#0F172A');
          root.style.setProperty('--text-secondary', '#475569');
          root.style.setProperty('--borders', '#CFDAE6');
          root.style.setProperty('--success', '#16A34A');
          root.style.setProperty('--error', '#DC2626');
          root.style.backgroundColor = '#ECF1F6';
          root.style.color = '#0F172A';
        }
      } else {
        // No user settings; apply defaults for light mode
        root.classList.remove('dark');
        root.classList.add('user-light');
        root.style.setProperty('--primary', '#1D3557');
        root.style.setProperty('--secondary', '#3F6FA6');
        root.style.setProperty('--accent', '#C96D02');
        root.style.setProperty('--background', '#ECF1F6');
        root.style.setProperty('--sidebar-bg', '#F4F7FB');
        root.style.setProperty('--surface', '#F8FAFD');
        root.style.setProperty('--text-primary', '#0F172A');
        root.style.setProperty('--text-secondary', '#475569');
        root.style.setProperty('--borders', '#CFDAE6');
        root.style.setProperty('--success', '#16A34A');
        root.style.setProperty('--error', '#DC2626');
        root.style.backgroundColor = '#ECF1F6';
        root.style.color = '#0F172A';
      }
    } else {
      // Dark mode: clear user-light and apply dark root class
      root.classList.add('dark');
      root.classList.remove('user-light');
      root.style.backgroundColor = 'rgb(11, 11, 11)';
      root.style.color = 'rgb(250, 245, 235)';
    }

    localStorage.setItem('adminTheme', isDarkMode ? 'dark' : 'light');
  }, [isDarkMode]);

  // Navigation
  const navigation = useMemo(() => [
    { 
      name: 'Dashboard', 
      icon: HomeIcon, 
      key: 'dashboard'
    },
    { 
      section: 'Appointments',
      items: [
        { 
          name: `All Appointments (${stats.totalAppointments || 0})`, 
          icon: CalendarIcon, 
          key: 'appointments'
        },
        { 
          name: 'Operation', 
          icon: CogIcon, 
          key: 'system-config'
        },
        { 
          name: `Services (${services.length || 0})`, 
          icon: DocumentTextIcon, 
          key: 'services'
        },
        { 
          name: 'Refund Management', 
          icon: DocumentArrowDownIcon, 
          key: 'refunds'
        }
      ]
    },
    { 
      section: 'User Management',
      items: [
        { 
          name: `All Users (${users.filter(u => u.role === 'client').length || 0})`, 
          icon: UserGroupIcon, 
          key: 'users'
        },
        { 
          name: `Admin Accounts (${admins.length || 0})`, 
          icon: ShieldCheckIcon, 
          key: 'adminProfile'
        },
        { 
          name: `Deactivated & Blocked (${deactivatedUsers.length + deactivatedAdmins.length || 0})`, 
          icon: UserMinusIcon, 
          key: 'user-status'
        },
        { 
          name: 'Appeals', 
          icon: ExclamationTriangleIcon, 
          key: 'appeals'
        }
      ]
    },
    { 
      section: 'Communication',
      items: [
        { 
          name: unreadMessageCount > 0 ? `Messages (${unreadMessageCount})` : 'Messages', 
          icon: ChatBubbleBottomCenterTextIcon, 
          key: 'messages',
          badge: unreadMessageCount > 0 ? unreadMessageCount : null
        },
        { 
          name: 'Action Logs', 
          icon: ClockIcon, 
          key: 'action-logs'
        },
        { 
          name: 'Feedback', 
          icon: StarIcon, 
          key: 'feedback'
        },
        {
          name: 'Announcements',
          icon: MegaphoneIcon,
          key: 'announcements'
        }
      ]
    },
    { 
      section: 'Reports & Analytics',
      items: [
        { 
          name: 'Smart Analytics', 
          icon: ChartBarIcon, 
          key: 'analytics'
        },
        { 
          name: 'Reports', 
          icon: DocumentChartBarIcon, 
          key: 'reports'
        },
        { 
          name: 'Archive', 
          icon: ArchiveBoxIcon, 
          key: 'archive'
        }
      ]
    },
    { 
      name: 'Settings', 
      icon: CogIcon, 
      key: 'settings'
    }
  ], [stats.totalAppointments, unavailableDates.length, users, admins.length, deactivatedUsers.length, deactivatedAdmins.length, services.length, unreadMessageCount]);

  // Debounced search optimization
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearchTerm(searchTerm);
      setCurrentPage(1);
    }, 200);

    return () => clearTimeout(timer);
  }, [searchTerm]);

  // Clear error when changing tabs
  useEffect(() => {
    clearError();
  }, [activeTab, clearError]);

  // Keep timeframeRef in sync with timeframe state
  useEffect(() => {
    timeframeRef.current = timeframe;
  }, [timeframe]);

  // Theme management - apply theme to document
  useEffect(() => {
    const root = document.documentElement;
    if (isDarkMode) {
      // Dark mode - default theme
      root.classList.add('dark');
      root.style.backgroundColor = 'rgb(11, 11, 11)'; // black
      root.style.color = 'rgb(250, 245, 235)'; // amber-50
    } else {
      // Light mode - using gray instead of stone for better appearance
      root.classList.remove('dark');
      root.style.backgroundColor = 'rgb(243, 244, 246)'; // gray-100
      root.style.color = 'rgb(31, 41, 55)'; // gray-800
    }
    
    // Save preference to localStorage
    localStorage.setItem('theme', isDarkMode ? 'dark' : 'light');
  }, [isDarkMode]);

  // Load saved theme preference on component mount
  useEffect(() => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'light') {
      setIsDarkMode(false);
    } else {
      setIsDarkMode(true);
    }
  }, []);

  // Load report statistics from real data
  const loadReportStats = useCallback(async () => {
    try {
      const [statsRes, analyticsRes] = await Promise.allSettled([
        axios.get('/api/admin/stats', { params: { timeframe: 'all' }, timeout: 10000 }),
        axios.get('/api/admin/analytics/dashboard', { params: { realtime: 'true' }, timeout: 15000 })
      ]);

      const statsData = statsRes.status === 'fulfilled' ? (statsRes.value?.data?.data || statsRes.value?.data || {}) : {};
      const analyticsData = analyticsRes.status === 'fulfilled' ? (analyticsRes.value?.data?.data || {}) : {};

      setReportStats({
        totalAppointments: statsData.totalAppointments || 0,
        completedAppointments: statsData.completedAppointments || 0,
        cancelledAppointments: statsData.cancelledAppointments || 0,
        pendingAppointments: statsData.pendingAppointments || 0,
        approvedAppointments: statsData.approvedAppointments || 0,
        totalUsers: statsData.totalUsers || 0,
        totalStaff: statsData.totalStaff || 0,
        revenue: statsData.revenue || 0,
        appointmentsByStatus: statsData.appointmentsByStatus || [],
        completionRate: statsData.totalAppointments > 0 ? ((statsData.completedAppointments || 0) / statsData.totalAppointments * 100).toFixed(1) : '0.0',
        cancellationRate: statsData.totalAppointments > 0 ? ((statsData.cancelledAppointments || 0) / statsData.totalAppointments * 100).toFixed(1) : '0.0',
        qualityReport: analyticsData.quality_report || {},
        slotUtilization: analyticsData.slot_utilization || {},
        lastUpdated: new Date(),
      });
    } catch (error) {
      console.error('Failed to load report stats:', error);
    }
  }, []);

  // Fixed API calls with proper error handling
  const loadDashboardData = useCallback(async (tf, realtime = false) => {
    try {
      setDashboardLoading(true);
      const startTime = performance.now();
      const effectiveTimeframe = tf || timeframe || 'monthly';
      const result = await callApi(async () => {
        const params = { timeframe: effectiveTimeframe };
        if (realtime) params.realtime = true;
        const response = await axios.get('/api/admin/stats', { 
          timeout: 10000,
          params
        });

        const payload = response.data?.data || response.data || {};
        return { data: { stats: payload } };
      }, { abortPrevious: false });

      const loadTime = performance.now() - startTime;
      if (loadTime > 500) {
        console.warn(`⚠️ Stats API took ${loadTime.toFixed(2)}ms`);
      }

      if (result.success) {
        setStats(result.data.stats || {});
      } else {
        console.error('Failed to load dashboard data:', result.error);
      }
      
      setDataLoaded(prev => ({ ...prev, dashboard: true }));
    } catch (error) {
      console.error('Dashboard data load failed:', error);
      setDataLoaded(prev => ({ ...prev, dashboard: true }));
    } finally {
      setDashboardLoading(false);
    }
  }, [callApi]);

  const loadUsers = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/users?role=client&limit=1000&include_self=true', { 
          timeout: 10000
        });
        
        let usersData = [];
        const payload = response.data?.data || response.data || response.data?.users || response.data;
        
        if (Array.isArray(payload)) {
          usersData = payload;
        } else if (payload && typeof payload === 'object') {
          usersData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        // Sort by created_at in descending order (newest first)
        usersData.sort((a, b) => {
          const dateA = new Date(a.created_at || 0);
          const dateB = new Date(b.created_at || 0);
          return dateB - dateA;
        });
        
        return { 
          data: usersData.map(user => ({ 
            ...user, 
            status: user.is_active ? 'active' : 'inactive'
          })) 
        };
      }, { abortPrevious: false });

      if (result.success) {
        setUsers(result.data || []);
      }
      setDataLoaded(prev => ({ ...prev, users: true }));
    } catch (error) {
      console.error('Users data load failed:', error);
      setUsers([]);
      setDataLoaded(prev => ({ ...prev, users: true }));
    }
  }, [callApi]);

  const loadAdmins = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/users?role=admin&include_self=true&limit=1000', { 
          timeout: 10000
        });
        
        let adminsData = [];
        const payload = response.data?.data || response.data || response.data?.users || response.data;
        
        if (Array.isArray(payload)) {
          adminsData = payload;
        } else if (payload && typeof payload === 'object') {
          adminsData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        // Sort by created_at in descending order (newest first)
        adminsData.sort((a, b) => {
          const dateA = new Date(a.created_at || 0);
          const dateB = new Date(b.created_at || 0);
          return dateB - dateA;
        });
        
        return { 
          data: adminsData.map(admin => ({ 
            ...admin, 
            status: admin.is_active ? 'active' : 'inactive'
          })) 
        };
      }, { abortPrevious: false });

      if (result.success) {
        setAdmins(result.data || []);
      }
      setDataLoaded(prev => ({ ...prev, adminProfile: true }));
    } catch (error) {
      console.error('Admins data load failed:', error);
      setAdmins([]);
      setDataLoaded(prev => ({ ...prev, adminProfile: true }));
    }
  }, [callApi]);

  const loadServices = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/services', { 
          timeout: 10000
        });
        
        let servicesData = [];
        const payload = response.data?.data || response.data || response.data?.services || response.data;
        
        if (Array.isArray(payload)) {
          servicesData = payload;
        } else if (payload && typeof payload === 'object') {
          servicesData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        // Filter only active services (not deleted)
        const activeServices = servicesData.filter(s => !s.deleted_at);
        return { data: activeServices };
      }, { abortPrevious: false });

      if (result.success) {
        setServices(result.data || []);
      }
    } catch (error) {
      console.error('Services data load failed:', error);
      setServices([]);
    }
  }, [callApi]);

  const loadAppointments = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/appointments?limit=1000', { 
          timeout: 10000,
          params: { timeframe: 'all' }
        });
        
        let appointmentsData = [];
        
        // Backend always returns { data: [...], success: true } format now
        if (response.data?.data && Array.isArray(response.data.data)) {
          appointmentsData = response.data.data;
        } else if (Array.isArray(response.data)) {
          appointmentsData = response.data;
        }
        
        // Sort by created_at in descending order (newest first)
        appointmentsData.sort((a, b) => {
          const dateA = new Date(a.created_at || 0);
          const dateB = new Date(b.created_at || 0);
          return dateB - dateA;
        });
        
        return { data: appointmentsData };
      }, { abortPrevious: false });

      if (result.success) {
        setAppointments(result.data || []);
      }
      setDataLoaded(prev => ({ ...prev, appointments: true }));
    } catch (error) {
      console.error('Appointments data load failed:', error);
      setAppointments([]);
      setDataLoaded(prev => ({ ...prev, appointments: true }));
    }
  }, [callApi, timeframe]);

  const loadSales = useCallback(async (tf) => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/sales', { 
          timeout: 10000,
          params: { timeframe: tf || timeframe }
        });
        
        let salesData = [];
        
        // Backend always returns { data: [...], success: true } format
        if (response.data?.data && Array.isArray(response.data.data)) {
          salesData = response.data.data;
        } else if (Array.isArray(response.data)) {
          salesData = response.data;
        }
        
        // Sort by created_at in descending order (newest first)
        salesData.sort((a, b) => {
          const dateA = new Date(a.created_at || 0);
          const dateB = new Date(b.created_at || 0);
          return dateB - dateA;
        });
        
        return { data: salesData };
      }, { abortPrevious: false });

      if (result.success) {
        setSales(result.data || []);
      }
      setDataLoaded(prev => ({ ...prev, sales: true }));
    } catch (error) {
      console.error('Sales data load failed:', error);
      setSales([]);
      setDataLoaded(prev => ({ ...prev, sales: true }));
    }
  }, [callApi, timeframe]);

  const loadUnavailableDates = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/admin/unavailable-dates', { 
          timeout: 10000
        });
        
        let datesData = [];
        const payload = response.data?.data || response.data || response.data?.dates || response.data;
        
        if (Array.isArray(payload)) {
          datesData = payload;
        } else if (payload && typeof payload === 'object') {
          datesData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        return { data: datesData };
      }, { abortPrevious: false });

      if (result.success) {
        setUnavailableDates(result.data || []);
      }
      setDataLoaded(prev => ({ ...prev, calendar: true }));
    } catch (error) {
      console.error('Unavailable dates load failed:', error);
      setUnavailableDates([]);
      setDataLoaded(prev => ({ ...prev, calendar: true }));
    }
  }, [callApi]);

  const loadArchivedUsers = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        const response = await axios.get('/api/users/archived/list', { 
          timeout: 10000
        });
        
        let userData = [];
        const payload = response.data?.data || response.data || response.data?.users || response.data;
        
        if (Array.isArray(payload)) {
          userData = payload;
        } else if (payload && typeof payload === 'object') {
          userData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        return { data: userData };
      }, { abortPrevious: false });

      if (result.success) {
        setArchivedUsers(result.data || []);
      }
    } catch (error) {
      console.error('Archived users load failed:', error);
      setArchivedUsers([]);
    }
  }, [callApi]);

  const loadArchivedAppointments = useCallback(async (filters = {}) => {
    try {
      const params = { per_page: 100 };
      if (filters.status && filters.status !== 'all') params.status = filters.status;
      if (filters.date_from) params.date_from = filters.date_from;
      if (filters.date_to) params.date_to = filters.date_to;
      if (filters.search) params.search = filters.search;

      const result = await callApi(async () => {
        const response = await axios.get('/api/appointments/archived/list', { 
          params,
          timeout: 10000
        });
        
        let appointmentData = [];
        const payload = response.data?.data || response.data?.data?.data || response.data || response.data?.appointments || response.data;
        
        if (Array.isArray(payload)) {
          appointmentData = payload;
        } else if (payload && typeof payload === 'object') {
          appointmentData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        return { data: appointmentData };
      }, { abortPrevious: false });

      if (result.success) {
        setArchivedAppointments(result.data || []);
      }
    } catch (error) {
      console.error('Archived appointments load failed:', error);
      setArchivedAppointments([]);
    }
  }, [callApi]);

  const loadDeactivatedAccounts = useCallback(async () => {
    try {
      const result = await callApi(async () => {
        // Fetch all users including self to find deactivated accounts
        const response = await axios.get('/api/admin/users?limit=1000&include_self=true', { 
          timeout: 10000
        });
        
        let userData = [];
        const payload = response.data?.data || response.data || response.data?.users || response.data;
        
        if (Array.isArray(payload)) {
          userData = payload;
        } else if (payload && typeof payload === 'object') {
          userData = Object.values(payload).filter(item => item && typeof item === 'object');
        }
        
        // Filter deactivated users (is_active === false AND account_status is 'deactivated' or not 'blocked')
        const deactivatedUsers = userData.filter(user => 
          user.is_active === false && 
          user.account_status !== 'blocked' && 
          user.account_status !== 'deleted' && 
          user.role === 'client'
        );
        const deactivatedAdmins = userData.filter(user => 
          user.is_active === false && 
          user.account_status !== 'blocked' && 
          user.account_status !== 'deleted' && 
          user.role === 'admin'
        );
        
        return { 
          deactivatedUsers,
          deactivatedAdmins
        };
      }, { abortPrevious: false });

      if (result.success) {
        const data = result.data || {};
        setDeactivatedUsers(data.deactivatedUsers || []);
        setDeactivatedAdmins(data.deactivatedAdmins || []);
      }
      setDataLoaded(prev => ({ ...prev, deactivated: true }));
    } catch (error) {
      console.error('Deactivated accounts load failed:', error);
      setDeactivatedUsers([]);
      setDeactivatedAdmins([]);
      setDataLoaded(prev => ({ ...prev, deactivated: true }));
    }
  }, [callApi]);

  // Load data when component mounts - only load essential dashboard data initially
  // Other tab data loads lazily when the tab is visited for the first time
  useEffect(() => {
    const loadInitialData = async () => {
      // Only load dashboard stats and services (needed for sidebar counts) initially
      await Promise.all([
        loadDashboardData(),
        loadServices(),
      ]);
    };
    
    loadInitialData();
  }, [loadDashboardData, loadServices]);

  // Listen for services updates from AdminServices component
  useEffect(() => {
    const handleServicesUpdate = () => {
      loadServices();
      loadReportStats(); // Also refresh report stats when services change
    };

    window.addEventListener('servicesUpdated', handleServicesUpdate);
    return () => window.removeEventListener('servicesUpdated', handleServicesUpdate);
  }, [loadServices, loadReportStats]);

  // Load data based on active tab - only load if not already loaded (data was preloaded on mount)
  useEffect(() => {
    const loadTabData = async () => {
      switch (activeTab) {
        case 'dashboard':
          if (!dataLoaded.dashboard) {
            await loadDashboardData();
          }
          break;
        case 'users':
          if (!dataLoaded.users) {
            await loadUsers();
          }
          break;
        case 'adminProfile':
          if (!dataLoaded.adminProfile) {
            await loadAdmins();
          }
          break;
        case 'appointments':
          if (!dataLoaded.appointments) {
            await loadAppointments();
          }
          break;
        case 'calendar':
          if (!dataLoaded.calendar) {
            await loadUnavailableDates();
          }
          break;
        case 'services':
          if (!dataLoaded.services) {
            await loadServices();
            setDataLoaded(prev => ({ ...prev, services: true }));
          }
          break;
        case 'archive':
          if (!dataLoaded.archive) {
            // Load archive data in parallel
            await Promise.all([
              loadArchivedUsers(),
              loadArchivedAppointments()
            ]);
            setDataLoaded(prev => ({ ...prev, archive: true }));
          }
          break;
        case 'deactivated':
          if (!dataLoaded.deactivated) {
            await loadDeactivatedAccounts();
          }
          break;
        default:
          break;
      }
    };

    loadTabData();
  }, [
    activeTab, 
    dataLoaded,
    loadDashboardData, 
    loadUsers, 
    loadAdmins,
    loadAppointments, 
    loadUnavailableDates,
    loadArchivedUsers,
    loadArchivedAppointments,
    loadDeactivatedAccounts
  ]);

  // Real-time polling: auto-refresh users/admins every 60 seconds when on those tabs
  useEffect(() => {
    if (activeTab !== 'users' && activeTab !== 'adminProfile') return;

    const interval = setInterval(() => {
      if (activeTab === 'users') {
        loadUsers();
      } else if (activeTab === 'adminProfile') {
        loadAdmins();
      }
    }, 60000); // 60 seconds

    const handleLogout = () => clearInterval(interval);
    window.addEventListener('auth:logout', handleLogout);
    return () => { clearInterval(interval); window.removeEventListener('auth:logout', handleLogout); };
  }, [activeTab, loadUsers, loadAdmins]);

  // Poll for unread message count every 30 seconds for sidebar badge
  useEffect(() => {
    const fetchUnreadCount = async () => {
      try {
        const result = await callApi(() =>
          axios.get('/api/messages', { timeout: 10000 }),
          { skipCache: true, showLoading: false }
        );
        if (result.success) {
          const convs = Array.isArray(result.data?.data) ? result.data.data : (Array.isArray(result.data) ? result.data : []);
          const total = convs.reduce((sum, conv) => sum + (conv.unread_count || 0), 0);
          setUnreadMessageCount(total);
        }
      } catch (e) {
        // Silently fail - badge just won't update
      }
    };
    fetchUnreadCount();
    const interval = setInterval(fetchUnreadCount, 30000);
    const handleLogout = () => clearInterval(interval);
    window.addEventListener('auth:logout', handleLogout);
    return () => { clearInterval(interval); window.removeEventListener('auth:logout', handleLogout); };
  }, [callApi]);

  // Reset unread count when viewing messages tab
  useEffect(() => {
    if (activeTab === 'messages') {
      setUnreadMessageCount(0);
    }
  }, [activeTab]);

  // Helper: check if a date is within the selected timeframe
  const isWithinTimeframe = useCallback((dateStr, tf) => {
    if (!dateStr) return false;
    const d = new Date(dateStr);
    if (isNaN(d)) return false;
    const now = new Date();
    const diff = now.getTime() - d.getTime();
    const day = 24 * 60 * 60 * 1000;

    switch (tf) {
      case 'daily':
        return d.toDateString() === now.toDateString();
      case 'weekly':
        return diff <= 7 * day;
      case 'monthly':
        return diff <= 30 * day;
      case 'yearly':
        return diff <= 365 * day;
      default:
        return true;
    }
  }, []);

  // Fixed: "All Users" now shows only clients

  const filteredUsers = useMemo(() => {
    let filtered = users || [];
    
    // Fixed: Only show clients in "All Users" tab
    filtered = filtered.filter(user => user.role === 'client');
    
    // Exclude deactivated/blocked/deleted users - they appear in their own sections
    filtered = filtered.filter(user => user.is_active !== false && user.account_status !== 'blocked' && user.account_status !== 'deactivated' && user.account_status !== 'deleted');
    
    if (debouncedSearchTerm) {
      const searchLower = debouncedSearchTerm.toLowerCase();
      filtered = filtered.filter(user => 
        (user.email?.toLowerCase() || '').includes(searchLower) ||
        (user.first_name?.toLowerCase() || '').includes(searchLower) ||
        (user.last_name?.toLowerCase() || '').includes(searchLower) ||
        (user.phone?.toLowerCase() || '').includes(searchLower)
      );
    }
    
    if (roleFilter !== 'all') {
      filtered = filtered.filter(user => user.role === roleFilter);
    }

    // NOTE: Timeframe filter removed from user listings - shows ALL users regardless of timeframe
    // Timeframe filter only applies to dashboard stats/charts

    return filtered;
  }, [users, debouncedSearchTerm, roleFilter]);

  

  const sortedUsers = useMemo(() => {
    if (!sortConfig.key) return filteredUsers;
    
    return [...filteredUsers].sort((a, b) => {
      const aVal = a[sortConfig.key] || '';
      const bVal = b[sortConfig.key] || '';
      
      if (aVal < bVal) return sortConfig.direction === 'asc' ? -1 : 1;
      if (aVal > bVal) return sortConfig.direction === 'asc' ? 1 : -1;
      return 0;
    });
  }, [filteredUsers, sortConfig]);

  const paginatedUsers = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage;
    return sortedUsers.slice(startIndex, startIndex + itemsPerPage);
  }, [sortedUsers, currentPage, itemsPerPage]);

  // Archived and deactivated filtered lists respect the global timeframe
  const filteredArchivedUsers = useMemo(() => {
    let list = archivedUsers || [];
    if (timeframe) {
      list = list.filter(u => isWithinTimeframe(u.deleted_at || u.deletedAt || u.created_at, timeframe));
    }
    if (archiveSearch) {
      const s = archiveSearch.toLowerCase();
      list = list.filter(u =>
        (u.first_name?.toLowerCase() || '').includes(s) ||
        (u.last_name?.toLowerCase() || '').includes(s) ||
        (u.email?.toLowerCase() || '').includes(s)
      );
    }
    if (archiveRoleFilter !== 'all') {
      list = list.filter(u => u.role === archiveRoleFilter);
    }
    list = [...list].sort((a, b) => {
      if (archiveSort === 'newest') return new Date(b.deleted_at || 0) - new Date(a.deleted_at || 0);
      if (archiveSort === 'oldest') return new Date(a.deleted_at || 0) - new Date(b.deleted_at || 0);
      if (archiveSort === 'name') return (a.first_name || '').localeCompare(b.first_name || '');
      return 0;
    });
    return list;
  }, [archivedUsers, timeframe, isWithinTimeframe, archiveSearch, archiveRoleFilter, archiveSort]);

  const filteredArchivedAppointments = useMemo(() => {
    let list = archivedAppointments || [];
    if (timeframe) {
      list = list.filter(a => isWithinTimeframe(a.archived_at || a.deleted_at || a.deletedAt || a.appointment_date || a.appointmentDate, timeframe));
    }
    if (archiveSearch) {
      const s = archiveSearch.toLowerCase();
      list = list.filter(a =>
        (a.user?.first_name?.toLowerCase() || '').includes(s) ||
        (a.user?.last_name?.toLowerCase() || '').includes(s) ||
        (a.appointment_date || '').includes(s) ||
        (formatServiceName(a) || '').toLowerCase().includes(s)
      );
    }
    if (archiveStatusFilter !== 'all') {
      list = list.filter(a => a.status === archiveStatusFilter);
    }
    if (archiveDateFrom) {
      list = list.filter(a => a.appointment_date >= archiveDateFrom);
    }
    if (archiveDateTo) {
      list = list.filter(a => a.appointment_date <= archiveDateTo);
    }
    list = [...list].sort((a, b) => {
      if (archiveSort === 'newest') return new Date(b.archived_at || b.deleted_at || 0) - new Date(a.archived_at || a.deleted_at || 0);
      if (archiveSort === 'oldest') return new Date(a.archived_at || a.deleted_at || 0) - new Date(b.archived_at || b.deleted_at || 0);
      if (archiveSort === 'name') return (a.user?.first_name || '').localeCompare(b.user?.first_name || '');
      return 0;
    });
    return list;
  }, [archivedAppointments, timeframe, isWithinTimeframe, archiveSearch, archiveSort, archiveStatusFilter, archiveDateFrom, archiveDateTo]);

  const filteredDeactivatedUsers = useMemo(() => {
    let list = deactivatedUsers || [];
    if (timeframe) {
      list = list.filter(u => isWithinTimeframe(u.updated_at || u.updatedAt || u.created_at, timeframe));
    }
    return list;
  }, [deactivatedUsers, timeframe, isWithinTimeframe]);

  const filteredDeactivatedAdmins = useMemo(() => {
    let list = deactivatedAdmins || [];
    if (timeframe) {
      list = list.filter(u => isWithinTimeframe(u.updated_at || u.updatedAt || u.created_at, timeframe));
    }
    return list;
  }, [deactivatedAdmins, timeframe, isWithinTimeframe]);

  // Fixed: Admin Accounts shows admin + staff accounts
  const filteredAdmins = useMemo(() => {
    let filtered = admins || [];
    
    if (debouncedSearchTerm) {
      const searchLower = debouncedSearchTerm.toLowerCase();
      filtered = filtered.filter(admin => 
        (admin.email?.toLowerCase() || '').includes(searchLower) ||
        (admin.first_name?.toLowerCase() || '').includes(searchLower) ||
        (admin.last_name?.toLowerCase() || '').includes(searchLower) ||
        (admin.phone?.toLowerCase() || '').includes(searchLower)
      );
    }

    // NOTE: Timeframe filter removed from admin listings - shows ALL admins regardless of timeframe
    // Timeframe filter only applies to dashboard stats/charts

    return filtered;
  }, [admins, debouncedSearchTerm]);

  const sortedAdmins = useMemo(() => {
    if (!sortConfig.key) return filteredAdmins;
    
    return [...filteredAdmins].sort((a, b) => {
      const aVal = a[sortConfig.key] || '';
      const bVal = b[sortConfig.key] || '';
      
      if (aVal < bVal) return sortConfig.direction === 'asc' ? -1 : 1;
      if (aVal > bVal) return sortConfig.direction === 'asc' ? 1 : -1;
      return 0;
    });
  }, [filteredAdmins, sortConfig]);

  const paginatedAdmins = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage;
    return sortedAdmins.slice(startIndex, startIndex + itemsPerPage);
  }, [sortedAdmins, currentPage, itemsPerPage]);

  const filteredAppointments = useMemo(() => {
    let filtered = appointments || [];
    
    if (debouncedSearchTerm) {
      const searchLower = debouncedSearchTerm.toLowerCase();
      filtered = filtered.filter(apt => 
        (apt.user?.first_name?.toLowerCase() || '').includes(searchLower) ||
        (apt.user?.last_name?.toLowerCase() || '').includes(searchLower) ||
        (apt.type?.toLowerCase() || '').includes(searchLower) ||
        (apt.user?.email?.toLowerCase() || '').includes(searchLower)
      );
    }
    
    if (statusFilter !== 'all') {
      filtered = filtered.filter(apt => apt.status === statusFilter);
    }
    // Apply global timeframe filter to appointments (based on appointment date)
    if (timeframe) {
      filtered = filtered.filter(apt => isWithinTimeframe(apt.appointment_date || apt.appointmentDate || apt.created_at, timeframe));
    }

    return filtered;
  }, [appointments, debouncedSearchTerm, statusFilter, timeframe, isWithinTimeframe]);

  const sortedAppointments = useMemo(() => {
    if (!sortConfig.key) return filteredAppointments;
    
    return [...filteredAppointments].sort((a, b) => {
      const aVal = a[sortConfig.key] || '';
      const bVal = b[sortConfig.key] || '';
      
      if (aVal < bVal) return sortConfig.direction === 'asc' ? -1 : 1;
      if (aVal > bVal) return sortConfig.direction === 'asc' ? 1 : -1;
      return 0;
    });
  }, [filteredAppointments, sortConfig]);

  const paginatedAppointments = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage;
    return sortedAppointments.slice(startIndex, startIndex + itemsPerPage);
  }, [sortedAppointments, currentPage, itemsPerPage]);

  // Fixed CRUD Operations
  const handleSaveUser = useCallback(async (userData) => {
    try {
      const url = selectedUser ? `/api/admin/users/${selectedUser.id}` : '/api/admin/users';
      const method = selectedUser ? 'PUT' : 'POST';

      const requestData = {
        first_name: userData.first_name,
        last_name: userData.last_name,
        email: userData.email,
        phone: userData.phone,
        address: userData.address,
        role: userData.role,
        ...(userData.password && { password: userData.password })
      };

      const result = await callApi(() => axios({
        method,
        url,
        data: requestData,
        timeout: 15000
      }));

      if (result.success) {
        const newUser = result.data?.data || result.data;
        
        if (method === 'POST' && newUser) {
          setUsers(prev => [...prev, { ...newUser, status: 'active' }]);
        } else if (method === 'PUT' && newUser) {
          setUsers(prev => prev.map(user => 
            user.id === selectedUser.id 
              ? { ...newUser, status: user.is_active ? 'active' : 'inactive' }
              : user
          ));
        }

        setShowUserModal(false);
        setSelectedUser(null);
        
        // Clear cache to ensure fresh data
        clearApiCache();
        
        setDataLoaded(prev => ({ 
          ...prev, 
          users: false, 
          dashboard: false,
          adminProfile: false 
        }));
        
        if (activeTab === 'users') {
          await loadUsers();
        } else if (activeTab === 'adminProfile') {
          await loadAdmins();
        }
        await loadDashboardData();
      }
    } catch (error) {
      console.error('Error saving user:', error);
    }
  }, [selectedUser, callApi, activeTab, loadUsers, loadAdmins, loadDashboardData]);

  const handleSaveAdmin = useCallback(async (adminData) => {
    try {
      const url = selectedAdmin ? `/api/admin/users/${selectedAdmin.id}` : '/api/admin/users';
      const method = selectedAdmin ? 'PUT' : 'POST';

      const requestData = {
        first_name: adminData.first_name,
        last_name: adminData.last_name,
        email: adminData.email,
        phone: adminData.phone,
        address: adminData.address || '',
        role: adminData.role || 'admin',
        ...(adminData.password && { password: adminData.password })
      };

      const result = await callApi(() => axios({
        method,
        url,
        data: requestData,
        timeout: 15000
      }));

      if (result.success) {
        const newAdmin = result.data?.data || result.data;
        
        if (method === 'POST' && newAdmin) {
          setAdmins(prev => [...prev, { ...newAdmin, status: 'active' }]);
        } else if (method === 'PUT' && newAdmin) {
          setAdmins(prev => prev.map(admin => 
            admin.id === selectedAdmin.id 
              ? { ...newAdmin, status: admin.is_active ? 'active' : 'inactive' }
              : admin
          ));
        }

        setShowAdminModal(false);
        setSelectedAdmin(null);
        
        // Clear cache to ensure fresh data
        clearApiCache();
        
        setDataLoaded(prev => ({ ...prev, adminProfile: false }));
        await loadAdmins();
        await loadDashboardData();
      }
    } catch (error) {
      console.error('Error saving admin:', error);
    }
  }, [selectedAdmin, callApi, loadAdmins, loadDashboardData]);

  const handleAddUnavailableDate = useCallback(async (dateData) => {
    try {
      // First ask the server which appointments would be affected by this blackout
      const affectedRes = await callApi(() => axios.post('/api/admin/unavailable-dates/affected', dateData, { timeout: 15000 }));

      if (affectedRes.success && Array.isArray(affectedRes.data?.data) && affectedRes.data.data.length > 0) {
        // Let admin preview affected appointments before creating the blackout
        setAffectedAppointments(affectedRes.data.data);
        setPendingUnavailableDate(dateData);
        setShowAffectedModal(true);
        return;
      }

      // No affected appointments or endpoint not available — proceed to create the blackout
      const result = await callApi(() => axios({
        method: 'POST',
        url: '/api/admin/unavailable-dates',
        data: dateData,
        timeout: 15000
      }));

      if (result.success) {
        const newDate = result.data?.data || result.data;
        if (newDate) setUnavailableDates(prev => [...prev, newDate]);
        setShowUnavailableModal(false);
        setDataLoaded(prev => ({ ...prev, calendar: false }));
        await loadUnavailableDates();
      }
    } catch (error) {
      console.error('Error adding unavailable date:', error);
    }
  }, [callApi, loadUnavailableDates]);

  // Called when admin confirms in the AffectedAppointmentsModal
  const handleConfirmAddUnavailable = useCallback(async ({ dateData, affectedIds = [] }) => {
    try {
      // include affectedIds when present so server can optionally reschedule or mark
      const payload = { ...dateData, affected_appointment_ids: affectedIds };
      const result = await callApi(() => axios({
        method: 'POST',
        url: '/api/admin/unavailable-dates',
        data: payload,
        timeout: 20000
      }));

      if (result.success) {
        const newDate = result.data?.data || result.data;
        if (newDate) setUnavailableDates(prev => [...prev, newDate]);
        setShowAffectedModal(false);
        setPendingUnavailableDate(null);
        setAffectedAppointments([]);
        setShowUnavailableModal(false);
        setDataLoaded(prev => ({ ...prev, calendar: false }));
        await loadUnavailableDates();
      }
    } catch (error) {
      console.error('Error applying unavailable date with affected appointments:', error);
    }
  }, [callApi, loadUnavailableDates]);

  // Called when admin wants to cancel selected appointments from affected list
  const handleCancelSelectedAppointments = useCallback(({ affected, selectedIds, dateData, cancellationReason }) => {
    // Transition from affected modal to cancel bulk modal
    setShowAffectedModal(false);
    setBulkCancelData({
      affected: affected.filter(apt => selectedIds.includes(apt.id)),
      dateData,
      cancellationReason
    });
    setShowCancelBulkModal(true);
  }, []);

  // Called when admin wants to send message to affected users (without cancelling)
  const handleSendMessageToAffected = useCallback(async ({ appointments, message, sendOption, dateData }) => {
    try {
      const userIds = [...new Set(appointments.map(apt => apt.user_id || apt.user?.id))].filter(Boolean);
      
      if (userIds.length === 0) {
        window.showToast?.('Warning', 'No users to send message to', 'warning');
        return;
      }

      const payload = {
        user_ids: userIds,
        message: message,
        subject: 'Important Notice About Your Appointment',
        appointment_ids: appointments.map(apt => apt.id),
        date: dateData?.date
      };

      const result = await callApi(() => axios({
        method: 'POST',
        url: '/api/admin/send-bulk-message',
        data: payload,
        timeout: 30000
      }));

      if (result.success) {
        window.showToast?.('Success', 'Message sent successfully!', 'success');
      } else {
        window.showToast?.('Error', 'Failed to send message. Please try again.', 'error');
      }
    } catch (error) {
      console.error('Error sending message to affected users:', error);
      window.showToast?.('Error', 'Failed to send message. Please try again.', 'error');
    }
  }, [callApi]);

  // Called from CancelBulkAppointmentsModal to execute bulk cancellation with messaging
  const handleConfirmBulkCancel = useCallback(async (cancelData) => {
    try {
      const {
        appointmentIds,
        cancellationReason,
        messageOption,
        includeReason,
        unavailableDate
      } = cancelData;

      const payload = {
        appointment_ids: appointmentIds,
        cancellation_reason: cancellationReason,
        message_type: messageOption, // 'individual' or 'group'
        include_reason_in_message: includeReason,
        unavailable_date: unavailableDate
      };

      const result = await callApi(() => axios({
        method: 'POST',
        url: '/api/admin/cancel-bulk-appointments',
        data: payload,
        timeout: 30000
      }));

      if (result.success) {
        // Close modals and refresh data
        setShowCancelBulkModal(false);
        setShowAffectedModal(false);
        setPendingUnavailableDate(null);
        setBulkCancelData(null);
        setAffectedAppointments([]);
        setShowUnavailableModal(false);
        
        // Refresh appointments and calendar
        setDataLoaded(prev => ({ ...prev, appointments: false, calendar: false }));
        await loadAppointments();
        await loadUnavailableDates();

        window.showToast?.('Success', 'Appointments cancelled successfully!', 'success');
      }
    } catch (error) {
      console.error('Error cancelling bulk appointments:', error);
      window.showToast?.('Error', 'Failed to cancel appointments. Please try again.', 'error');
    }
  }, [callApi, loadAppointments, loadUnavailableDates]);

  const handleDeleteUser = useCallback(async () => {
    if (!itemToDelete) return;

    try {
      setUsers(prev => prev.filter(user => user.id !== itemToDelete.id));
      setAdmins(prev => prev.filter(admin => admin.id !== itemToDelete.id));

      const result = await callApi(() => 
        axios.delete(`/api/admin/users/${itemToDelete.id}`, { 
          timeout: 15000 
        })
      );

      if (result.success) {
        setShowDeleteModal(false);
        setItemToDelete(null);
        
        // Clear cache to ensure fresh data
        clearApiCache();
        
        setDataLoaded(prev => ({ 
          ...prev, 
          users: false, 
          dashboard: false,
          adminProfile: false,
          archive: false
        }));
        
        if (activeTab === 'users') {
          await loadUsers();
        } else if (activeTab === 'adminProfile') {
          await loadAdmins();
        }
        
        // Reload archived users so they appear in Archive tab
        await loadArchivedUsers();
        await loadDashboardData();
      } else {
        if (activeTab === 'users') {
          await loadUsers();
        } else if (activeTab === 'adminProfile') {
          await loadAdmins();
        }
      }
    } catch (error) {
      console.error('Error deleting user:', error);
      if (activeTab === 'users') {
        await loadUsers();
      } else if (activeTab === 'adminProfile') {
        await loadAdmins();
      }
    }
  }, [itemToDelete, callApi, activeTab, loadUsers, loadAdmins, loadDashboardData, loadArchivedUsers]);

  const handleDeleteAdmin = useCallback(async () => {
    if (!itemToDelete) return;

    try {
      setAdmins(prev => prev.filter(admin => admin.id !== itemToDelete.id));

      const result = await callApi(() => 
        axios.delete(`/api/admin/users/${itemToDelete.id}`, { 
          timeout: 15000 
        })
      );

      if (result.success) {
        setShowDeleteModal(false);
        setItemToDelete(null);
        
        // Clear cache to ensure fresh data
        clearApiCache();
        
        setDataLoaded(prev => ({ ...prev, adminProfile: false, archive: false }));
        await loadAdmins();
        // Reload archived users so they appear in Archive tab
        await loadArchivedUsers();
        await loadDashboardData();
      } else {
        await loadAdmins();
      }
    } catch (error) {
      console.error('Error deleting admin:', error);
      await loadAdmins();
    }
  }, [itemToDelete, callApi, loadAdmins, loadDashboardData, loadArchivedUsers]);

  const handleToggleUserStatus = useCallback(async (userItem) => {
    try {
      const result = await callApi(() => 
        axios.put(`/api/users/${userItem.id}/toggle-status`, {}, { 
          timeout: 15000 
        })
      );

      if (result.success) {
        // Clear cache to ensure fresh data
        clearApiCache();
        
        setDataLoaded(prev => ({ ...prev, users: false, adminProfile: false, deactivated: false }));
        
        // Always reload both users and admins to ensure proper display regardless of current tab
        await loadUsers();
        await loadAdmins();
        
        // Reload deactivated accounts to reflect the change
        await loadDeactivatedAccounts();
        
        if (showUserDetailModal) {
          setShowUserDetailModal(false);
          setSelectedUser(null);
        }
      } else {
        // Reload on failure too
        await loadUsers();
        await loadAdmins();
        await loadDeactivatedAccounts();
      }
    } catch (error) {
      console.error('Error toggling user status:', error);
      // Reload on error too
      await loadUsers();
      await loadAdmins();
      await loadDeactivatedAccounts();
    }
  }, [callApi, showUserDetailModal, loadUsers, loadAdmins, loadDeactivatedAccounts]);

  const handleToggleAdminStatus = useCallback(async (adminItem) => {
    try {
      const result = await callApi(() => 
        axios.put(`/api/users/${adminItem.id}/toggle-status`, {}, { 
          timeout: 15000 
        })
      );

      if (result.success) {
        setDataLoaded(prev => ({ ...prev, users: false, adminProfile: false, deactivated: false }));
        // Always reload both users and admins to ensure proper display regardless of current tab
        await loadUsers();
        await loadAdmins();
        // Reload deactivated accounts to reflect the change
        await loadDeactivatedAccounts();
      } else {
        // Reload on failure too
        await loadUsers();
        await loadAdmins();
        await loadDeactivatedAccounts();
      }
    } catch (error) {
      console.error('Error toggling admin status:', error);
      // Reload on error too
      await loadUsers();
      await loadAdmins();
      await loadDeactivatedAccounts();
    }
  }, [callApi, loadUsers, loadAdmins, loadDeactivatedAccounts]);

  // Handle admin account action (delete/block/deactivate with reason)
  const handleAccountAction = useCallback(async () => {
    if (!accountActionUser || !accountActionReason || accountActionReason.length < 10) {
      setAccountActionError('Please provide a reason (at least 10 characters).');
      return;
    }

    try {
      setAccountActionLoading(true);
      setAccountActionError('');

      const result = await callApi(() =>
        axios.post(`/api/admin/users/${accountActionUser.id}/account-action`, {
          action: accountActionType,
          reason: accountActionReason,
        }, { timeout: 15000 })
      );

      if (result.success) {
        clearApiCache();
        setShowAccountActionModal(false);
        setAccountActionUser(null);
        setAccountActionType('deactivated');
        setAccountActionReason('');
        setDataLoaded(prev => ({ ...prev, users: false, adminProfile: false, deactivated: false }));
        await Promise.all([
          loadUsers(),
          loadAdmins(),
          loadDeactivatedAccounts(),
          loadArchivedUsers(),
        ]);
      } else {
        setAccountActionError(result.error || 'Failed to perform action.');
      }
    } catch (error) {
      setAccountActionError(error.response?.data?.message || 'Failed to perform action.');
    } finally {
      setAccountActionLoading(false);
    }
  }, [accountActionUser, accountActionType, accountActionReason, callApi, loadUsers, loadAdmins, loadDeactivatedAccounts, loadArchivedUsers]);

  const handleDeleteUnavailableDate = useCallback(async (dateId) => {
    try {
      setUnavailableDates(prev => prev.filter(date => date.id !== dateId));

      const result = await callApi(() => 
        axios.delete(`/api/admin/unavailable-dates/${dateId}`, { 
          timeout: 15000 
        })
      );

      if (result.success) {
        setDataLoaded(prev => ({ ...prev, calendar: false }));
        await loadUnavailableDates();
      } else {
        await loadUnavailableDates();
      }
    } catch (error) {
      console.error('Error deleting unavailable date:', error);
      await loadUnavailableDates();
    }
  }, [callApi, loadUnavailableDates]);

  // FIXED: Enhanced appointment approval/decline with proper API calls and immediate UI update
  const handleAppointmentAction = useCallback(async (appointmentId, action, data = null) => {
    try {
      // For complete action, open modal instead of executing directly
      if (action === 'complete') {
        const appointmentToComplete = appointments.find(apt => apt.id === appointmentId);
        if (appointmentToComplete) {
          setAppointmentToComplete(appointmentToComplete);
          setShowCompletionModal(true);
        }
        return;
      }

      const statusMap = {
        'approve': 'approved',
        'decline': 'declined',
        'cancel': 'cancelled',
        'complete': 'completed',
        'no-show': 'no_show'
      };
      const finalStatus = statusMap[action] || action;

      // Optimistically update the UI immediately
      setAppointments(prev => prev.map(apt => 
        apt.id === appointmentId ? { ...apt, status: finalStatus } : apt
      ));

      const payload = (action === 'decline' && data) ? { decline_reason: data } : {};

      // 'pending' (Reset) uses the /status endpoint since there's no dedicated /pending route
      const url = (action === 'pending')
        ? `/api/appointments/${appointmentId}/status`
        : `/api/appointments/${appointmentId}/${action}`;
      const body = (action === 'pending') ? { status: 'pending' } : payload;

      const result = await callApi(() => 
        axios.put(url, body, { 
          timeout: 15000 
        })
      );

      if (result.success) {
        console.log(`Appointment ${action}ed successfully:`, result.data);
        // Clear all frontend API cache to ensure fresh data after status change
        clearApiCache();
        // Success - refresh data to ensure consistency
        setDataLoaded(prev => ({ ...prev, appointments: false, dashboard: false }));
        await loadAppointments();
        await loadDashboardData();
      } else {
        console.error(`Failed to ${action} appointment:`, result);
        // If API call fails, revert the optimistic update
        await loadAppointments();
      }
    } catch (error) {
      console.error(`Error ${action}ing appointment:`, error);
      // Revert the optimistic update on error
      await loadAppointments();
    }
  }, [callApi, loadAppointments, loadDashboardData, appointments]);

  // Message sending functionality - Save to database and send email
  const handleSendMessage = useCallback(async (messageData) => {
    try {
      // First send the email and save to database via admin endpoint
      const result = await callApi(() => 
        axios.post('/api/admin/send-message', {
          userId: messageData.userId,
          subject: messageData.subject,
          message: messageData.message,
          type: messageData.type
        }, { 
          timeout: 15000 
        })
      );

      if (result.success) {
        // Close modal and clear selection
        setShowMessageModal(false);
        setSelectedUser(null);
        
        // Wait a brief moment for database to be fully written
        await new Promise(resolve => setTimeout(resolve, 500));
        
        // Trigger AdminMessages component to refresh conversations
        if (adminMessagesRef.current) {
          await adminMessagesRef.current.refreshConversations();
        }
        
        console.log('Message sent successfully to user email and saved to database');
      } else {
        console.error('Error sending message:', result);
      }
    } catch (error) {
      console.error('Error sending message:', error);
    }
  }, [callApi]);

  // Handle Appointment Completion
  const handleAppointmentCompletion = useCallback(async (completionNotes) => {
    if (!appointmentToComplete) return;

    try {
      const result = await callApi(() => 
        axios.put(`/api/appointments/${appointmentToComplete.id}/complete`, {
          completion_notes: completionNotes
        }, { 
          timeout: 15000 
        })
      );

      if (result.success) {
        console.log('Appointment completed successfully:', result.data);
        // Close modal and reset
        setShowCompletionModal(false);
        setAppointmentToComplete(null);
        
        // Refresh appointments and dashboard data
        setDataLoaded(prev => ({ ...prev, appointments: false, dashboard: false }));
        await loadAppointments();
        await loadDashboardData();
      } else {
        console.error('Failed to complete appointment:', result);
      }
    } catch (error) {
      console.error('Error completing appointment:', error);
    }
  }, [appointmentToComplete, callApi, loadAppointments, loadDashboardData]);

  // Report Generation Handler
  const handleGenerateReport = useCallback(async (reportData) => {
    try {
      const result = await callApi(() => 
        axios({
          method: 'POST',
          url: '/api/admin/reports/generate',
          data: reportData,
          responseType: 'blob',
          timeout: 30000
        })
      );

      if (result.success && result.data) {
        try {
          const blob = new Blob([result.data]);
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `report-${reportData.reportType}-${new Date().toISOString().split('T')[0]}.${reportData.format}`;
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);
          
          setShowReportModal(false);
        } catch (error) {
          console.error('Error downloading report:', error);
        }
      }
    } catch (error) {
      console.error('Error generating report:', error);
      setShowReportModal(false);
    }
  }, [callApi]);

  const handleSort = useCallback((key) => {
    setSortConfig(prev => ({
      key,
      direction: prev.key === key && prev.direction === 'asc' ? 'desc' : 'asc'
    }));
  }, []);

  const handleAppointmentSort = useCallback((key) => {
    setAppointmentSort(prev => ({
      key,
      direction: prev.key === key && prev.direction === 'asc' ? 'desc' : 'asc'
    }));
    setAppointmentPage(1);
  }, []);

  // Refresh function
  const handleRefresh = useCallback(async () => {
    // Clear all API cache to ensure truly fresh data
    clearApiCache();
    
    setDataLoaded({
      dashboard: false,
      users: false,
      appointments: false,
      calendar: false,
      adminProfile: false,
      archive: false,
      deactivated: false
    });
    
    switch (activeTab) {
      case 'dashboard':
        await loadDashboardData();
        break;
      case 'users':
        await loadUsers();
        break;
      case 'adminProfile':
        await loadAdmins();
        break;
      case 'appointments':
        await loadAppointments();
        break;
      case 'calendar':
        await loadUnavailableDates();
        break;
      default:
        break;
    }
  }, [activeTab, loadDashboardData, loadUsers, loadAdmins, loadAppointments, loadUnavailableDates]);

  // Load dashboard stats once when admin opens the dashboard.
  // Removed automatic polling to prevent perceived "refreshing" behavior.
  useEffect(() => {
    if (activeTab === 'dashboard') {
      loadDashboardData(timeframeRef.current);
      loadSales(timeframeRef.current);
    }
    if (activeTab === 'reports') {
      loadReportStats();
    }
  }, [activeTab, loadDashboardData, loadSales, loadReportStats]);

  // When timeframe changes, reload dashboard stats with new timeframe
  // This ensures charts and data reflect the selected period
  useEffect(() => {
    if (activeTab === 'dashboard') {
      // Immediately reload stats when timeframe changes
      loadDashboardData(timeframe);
      loadSales(timeframe);
      // Reset pagination for new data
      setCurrentPage(1);
      setAppointmentPage(1);
    }
  }, [timeframe, activeTab, loadDashboardData, loadSales]);

  // Chart data
  const appointmentStatusData = useMemo(() => [
    { label: 'Pending', value: (appointments || []).filter(a => a.status === 'pending').length, color: 'var(--accent)' },
    { label: 'Approved', value: (appointments || []).filter(a => a.status === 'approved').length, color: 'var(--secondary)' },
    { label: 'Completed', value: (appointments || []).filter(a => a.status === 'completed').length, color: 'var(--success)' },
    { label: 'Cancelled', value: (appointments || []).filter(a => a.status === 'cancelled').length, color: 'var(--error)' },
    { label: 'Declined', value: (appointments || []).filter(a => a.status === 'declined').length, color: 'var(--error)' }
  ], [appointments]);

  const userRoleData = useMemo(() => [
    { label: 'Clients', value: (users || []).filter(u => u.role === 'client').length, color: '#10b981' },
    { label: 'Admins', value: (users || []).filter(u => u.role === 'admin').length, color: '#8b5cf6' }
  ], [users]);

  // Use server-provided series when available (appointmentsByPeriod, revenueByPeriod)
  const appointmentsByPeriod = useMemo(() => {
    return stats.appointmentsByPeriod || stats.appointmentsByMonth || [];
  }, [stats]);

  const revenueByPeriod = useMemo(() => {
    return stats.revenueByPeriod || [];
  }, [stats]);

  // Theme helper - returns conditional classes based on isDarkMode
  const themeClass = useCallback((darkClasses, lightClasses = '') => {
    return isDarkMode ? darkClasses : (lightClasses || darkClasses);
  }, [isDarkMode]);

  // Table helper function for light mode
  const getTableClasses = () => ({
    container: isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40',
    header: isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-300',
    headerText: isDarkMode ? 'text-amber-50' : 'text-amber-900',
    headerBorder: isDarkMode ? 'border-gray-700' : 'border-gray-300',
    row: isDarkMode ? 'border-gray-700/50 hover:bg-gray-800/30' : 'border-gray-200 hover:bg-gray-50',
    text: isDarkMode ? 'text-gray-300' : 'text-gray-800',
    badge: isDarkMode ? 'bg-gray-700 text-amber-50' : 'bg-gray-100 text-gray-900'
  });

  // Archive Handler Functions
  const handleRestoreItem = async (itemId, itemType) => {
    setRestoreLoading(itemId);
    try {
      const response = await axios.post('/api/archive/restore', {
        item_id: itemId,
        item_type: itemType
      });
      if (response.data?.success) {
        window.showToast?.('Success', 'Item restored successfully!', 'success');
        setDataLoaded(prev => ({ 
          ...prev, 
          archive: false, 
          users: false, 
          adminProfile: false,
          deactivated: false
        }));
        await loadArchivedUsers();
        await loadArchivedAppointments();
        await loadUsers();
        await loadAdmins();
        await loadDeactivatedAccounts();
      }
    } catch (error) {
      window.showToast?.('Error', 'Failed to restore item: ' + (config?.debug ? error.message : ''), 'error');
    } finally {
      setRestoreLoading(null);
    }
  };

  const handleDeletePermanently = async (itemId, itemType) => {
    if (!window.confirm('Are you sure? This will permanently delete this item and cannot be undone.')) {
      return;
    }
    
    try {
      const response = await axios.delete(`/api/${itemType === 'user' ? 'users' : 'appointments'}/permanent/${itemId}`);
      if (response.data?.success) {
        window.showToast?.('Success', 'Item permanently deleted', 'success');
        loadArchivedUsers();
        loadArchivedAppointments();
      }
    } catch (error) {
      window.showToast?.('Error', 'Failed to delete item: ' + (config?.debug ? error.message : ''), 'error');
    }
  };

  const renderArchive = () => {
    const statusTabs = [
      { key: 'all', label: 'All', color: 'amber' },
      { key: 'completed', label: 'Completed', color: 'green' },
      { key: 'cancelled', label: 'Cancelled', color: 'red' },
      { key: 'declined', label: 'Declined', color: 'red' },
      { key: 'no_show', label: 'No Show', color: 'orange' },
    ];

    const statusColorMap = {
      completed: 'bg-green-900/50 text-green-300',
      cancelled: 'bg-red-900/50 text-red-300',
      declined: 'bg-red-900/50 text-red-300',
      no_show: 'bg-orange-900/50 text-orange-300',
      approved: 'bg-blue-900/50 text-blue-300',
    };

    return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-lg font-bold text-amber-50">Archive Management</h2>
          <p className="text-gray-400 text-sm">Restore or permanently delete archived items</p>
        </div>
        <button
          type="button"
          onClick={() => {
            loadArchivedUsers();
            loadArchivedAppointments({ status: archiveStatusFilter, date_from: archiveDateFrom, date_to: archiveDateTo, search: archiveSearch });
          }}
          className="px-3 py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-sm flex items-center"
          title="Refresh archive"
        >
          <ArrowPathIcon className="h-3 w-3 mr-1" />
          Refresh
        </button>
      </div>

      {/* Statistics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
        <button
          onClick={() => setArchiveTab('users')}
          className={`p-4 rounded-lg border-2 transition-all duration-200 text-left ${
            archiveTab === 'users'
              ? 'bg-blue-500/20 border-blue-500 shadow-lg shadow-blue-500/20'
              : 'bg-gray-800/50 border-gray-700 hover:border-blue-500/50'
          }`}
        >
          <div className="flex items-center justify-between">
            <div>
              <p className="text-gray-400 text-xs">Archived Accounts</p>
              <p className="text-2xl font-bold text-blue-400 mt-1">{filteredArchivedUsers.length}</p>
            </div>
            <UserGroupIcon className={`h-8 w-8 ${archiveTab === 'users' ? 'text-blue-400' : 'text-gray-500'}`} />
          </div>
        </button>

        <button
          onClick={() => setArchiveTab('appointments')}
          className={`p-4 rounded-lg border-2 transition-all duration-200 text-left ${
            archiveTab === 'appointments'
              ? 'bg-green-500/20 border-green-500 shadow-lg shadow-green-500/20'
              : 'bg-gray-800/50 border-gray-700 hover:border-green-500/50'
          }`}
        >
          <div className="flex items-center justify-between">
            <div>
              <p className="text-gray-400 text-xs">Archived Appointments</p>
              <p className="text-2xl font-bold text-green-400 mt-1">{filteredArchivedAppointments.length}</p>
            </div>
            <CalendarIcon className={`h-8 w-8 ${archiveTab === 'appointments' ? 'text-green-400' : 'text-gray-500'}`} />
          </div>
        </button>
      </div>

      {/* Search, Filter & Sort Controls */}
      <div className="flex flex-col gap-2">
        <div className="flex flex-col sm:flex-row gap-2">
          <input
            type="text"
            placeholder={archiveTab === 'users' ? 'Search by name or email...' : 'Search by name, date, or service...'}
            value={archiveSearch}
            onChange={e => setArchiveSearch(e.target.value)}
            className="flex-1 px-3 py-2 rounded-lg text-sm border bg-gray-800/50 border-gray-700 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
          />
          {archiveTab === 'users' && (
            <select
              value={archiveRoleFilter}
              onChange={e => setArchiveRoleFilter(e.target.value)}
              className="px-3 py-2 rounded-lg text-sm border bg-gray-800/50 border-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50"
            >
              <option value="all">All Roles</option>
              <option value="client">Client</option>
              <option value="admin">Admin</option>
              <option value="staff">Staff</option>
            </select>
          )}
          <select
            value={archiveSort}
            onChange={e => setArchiveSort(e.target.value)}
            className="px-3 py-2 rounded-lg text-sm border bg-gray-800/50 border-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50"
          >
            <option value="newest">Newest First</option>
            <option value="oldest">Oldest First</option>
            <option value="name">Name A-Z</option>
          </select>
        </div>

        {/* Appointment-specific: Status tabs + Date range */}
        {archiveTab === 'appointments' && (
          <>
            {/* Status Tabs */}
            <div className="flex flex-wrap gap-1.5">
              {statusTabs.map(tab => {
                const isActive = archiveStatusFilter === tab.key;
                const colorClasses = {
                  amber: isActive ? 'bg-amber-500/20 border-amber-500 text-amber-300' : 'border-gray-700 text-gray-400 hover:border-amber-500/40',
                  green: isActive ? 'bg-green-500/20 border-green-500 text-green-300' : 'border-gray-700 text-gray-400 hover:border-green-500/40',
                  red: isActive ? 'bg-red-500/20 border-red-500 text-red-300' : 'border-gray-700 text-gray-400 hover:border-red-500/40',
                  orange: isActive ? 'bg-orange-500/20 border-orange-500 text-orange-300' : 'border-gray-700 text-gray-400 hover:border-orange-500/40',
                };
                return (
                  <button
                    key={tab.key}
                    onClick={() => setArchiveStatusFilter(tab.key)}
                    className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition-all duration-200 ${colorClasses[tab.color]}`}
                  >
                    {tab.label}
                  </button>
                );
              })}
            </div>

            {/* Date Range */}
            <div className="flex flex-col sm:flex-row gap-2 items-start sm:items-center">
              <span className="text-xs text-gray-400 whitespace-nowrap">Date Range:</span>
              <input
                type="date"
                value={archiveDateFrom}
                onChange={e => setArchiveDateFrom(e.target.value)}
                className="px-3 py-1.5 rounded-lg text-sm border bg-gray-800/50 border-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 [color-scheme:dark]"
              />
              <span className="text-xs text-gray-500">to</span>
              <input
                type="date"
                value={archiveDateTo}
                onChange={e => setArchiveDateTo(e.target.value)}
                className="px-3 py-1.5 rounded-lg text-sm border bg-gray-800/50 border-gray-700 text-white focus:outline-none focus:ring-2 focus:ring-amber-500/50 [color-scheme:dark]"
              />
              {(archiveDateFrom || archiveDateTo) && (
                <button
                  onClick={() => { setArchiveDateFrom(''); setArchiveDateTo(''); }}
                  className="text-xs text-amber-400 hover:text-amber-300 underline"
                >
                  Clear dates
                </button>
              )}
            </div>
          </>
        )}
      </div>

      {/* Empty State */}
      {archiveTab === 'users' && filteredArchivedUsers.length === 0 && (
        <div className="bg-gray-800/30 border border-gray-700 rounded-lg p-8 text-center">
          <UserGroupIcon className="h-12 w-12 text-gray-500 mx-auto mb-2 opacity-50" />
          <p className="text-gray-400">No archived accounts</p>
        </div>
      )}

      {archiveTab === 'appointments' && filteredArchivedAppointments.length === 0 && (
        <div className="bg-gray-800/30 border border-gray-700 rounded-lg p-8 text-center">
          <CalendarIcon className="h-12 w-12 text-gray-500 mx-auto mb-2 opacity-50" />
          <p className="text-gray-400">No archived appointments{archiveStatusFilter !== 'all' ? ` with status "${archiveStatusFilter.replace('_', ' ')}"` : ''}</p>
        </div>
      )}

      {/* Archived Accounts View */}
      {archiveTab === 'users' && filteredArchivedUsers.length > 0 && (
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold text-amber-50">Archived Accounts ({filteredArchivedUsers.length})</h3>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[500px] overflow-y-auto pr-2">
            {filteredArchivedUsers.map((user) => (
              <div key={user.id} className="bg-gray-800/50 border border-gray-700 rounded-lg p-4 hover:border-blue-500/30 transition-all">
                <div className="mb-3">
                  <p className="text-sm font-semibold text-amber-50">{user.first_name} {user.last_name}</p>
                  <p className="text-xs text-gray-400 truncate">{user.email}</p>
                  <div className="flex items-center justify-between mt-2">
                    <span className="text-xs px-2 py-0.5 rounded bg-blue-900/50 text-blue-300">{user.role}</span>
                    <span className="text-xs text-gray-500">{new Date(user.deleted_at).toLocaleDateString()}</span>
                  </div>
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => handleRestoreItem(user.id, 'user')}
                    disabled={restoreLoading === user.id}
                    className="flex-1 px-2 py-1.5 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white text-xs rounded font-medium transition-all"
                    title="Restore this user"
                  >
                    {restoreLoading === user.id ? '...' : '↻ Restore'}
                  </button>
                  <button
                    onClick={() => handleDeletePermanently(user.id, 'user')}
                    className="px-2 py-1.5 bg-red-900/30 hover:bg-red-900/60 text-red-300 text-xs rounded font-medium transition-all"
                    title="Permanently delete"
                  >
                    × Delete
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Archived Appointments View */}
      {archiveTab === 'appointments' && filteredArchivedAppointments.length > 0 && (
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold text-amber-50">Archived Appointments ({filteredArchivedAppointments.length})</h3>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[500px] overflow-y-auto pr-2">
            {filteredArchivedAppointments.map((apt) => (
              <div key={apt.id} className="bg-gray-800/50 border border-gray-700 rounded-lg p-4 hover:border-green-500/30 transition-all">
                <div className="mb-3">
                  <p className="text-sm font-semibold text-amber-50">{apt.user?.first_name} {apt.user?.last_name}</p>
                  <p className="text-xs text-gray-400">{apt.appointment_date} {apt.appointment_time ? `at ${apt.appointment_time}` : ''}</p>
                  <div className="flex items-center gap-2 mt-2">
                    <span className="text-xs px-2 py-0.5 rounded bg-green-900/50 text-green-300">{formatServiceName(apt)}</span>
                    <span className={`text-xs px-2 py-0.5 rounded ${statusColorMap[apt.status] || 'bg-gray-700/50 text-gray-300'}`}>
                      {apt.status === 'no_show' ? 'No Show' : apt.status}
                    </span>
                  </div>
                  <span className="text-xs text-gray-500 block mt-1">
                    Archived {apt.archived_at ? new Date(apt.archived_at).toLocaleDateString() : (apt.deleted_at ? new Date(apt.deleted_at).toLocaleDateString() : '')}
                  </span>
                </div>
                <div className="flex gap-2">
                  <button
                    onClick={() => handleRestoreItem(apt.id, 'appointment')}
                    disabled={restoreLoading === apt.id}
                    className="flex-1 px-2 py-1.5 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white text-xs rounded font-medium transition-all"
                    title="Restore this appointment"
                  >
                    {restoreLoading === apt.id ? '...' : '↻ Restore'}
                  </button>
                  <button
                    onClick={() => handleDeletePermanently(apt.id, 'appointment')}
                    className="px-2 py-1.5 bg-red-900/30 hover:bg-red-900/60 text-red-300 text-xs rounded font-medium transition-all"
                    title="Permanently delete"
                  >
                    × Delete
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Info Banner */}
      <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4">
        <div className="flex gap-3">
          <ExclamationTriangleIcon className="h-5 w-5 text-amber-400 flex-shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-medium text-amber-200 mb-1">How Archive Works</p>
            <ul className="text-xs text-amber-100/80 space-y-1">
              <li>• Completed, cancelled, declined, and no-show appointments are auto-archived after 24 hours</li>
              <li>• Click "Restore" to bring items back to active status</li>
              <li>• Click "Delete" to permanently remove items (cannot be undone)</li>
              <li>• Use the status tabs and date range filter to find specific archived appointments</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    );
  };

  // Settings render function
  const renderSettings = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300`}>System Settings</h2>
          <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-sm transition-colors duration-300`}>Customize your system and appearance</p>
        </div>
      </div>

      {/* Theme Settings */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-6 hover:border-amber-500/40 transition-all duration-300`}>
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} flex items-center transition-colors duration-300`}>
              <Cog6ToothIcon className="h-4 w-4 mr-2" />
              Theme & Appearance
            </h3>
            <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs mt-1 transition-colors duration-300`}>Choose your preferred color theme</p>
          </div>

          {/* Toggle switch to quickly flip between dark and light for admin */}
          <div className="flex items-center gap-3">
            <span className={`text-xs ${isDarkMode ? 'text-amber-300' : 'text-gray-700'}`}>{isDarkMode ? 'Dark' : 'Light'}</span>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={!isDarkMode}
                onChange={() => setIsDarkMode(prev => !prev)}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-gray-600 rounded-full peer-focus:ring-2 peer-focus:ring-amber-500 peer-checked:bg-amber-600 relative after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:border-gray-300 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
            </label>
          </div>
        </div>

        <div className="space-y-3">
          <div className={`flex items-center justify-between p-3 ${isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-300'} rounded-lg border transition-colors duration-300`}>
            <div className="flex items-center space-x-3">
              <div className={`w-8 h-8 ${isDarkMode ? 'bg-gray-900 border-gray-600' : 'bg-gray-100 border-gray-400'} rounded border flex items-center justify-center transition-colors duration-300`}>
                <div className="w-6 h-6 bg-amber-500 rounded" />
              </div>
              <div>
                <span className={`text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-gray-900'} transition-colors duration-300`}>Dark Mode</span>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} transition-colors duration-300`}>Current theme - Optimized for night viewing</p>
              </div>
            </div>
            <input
              type="radio"
              name="theme"
              value="dark"
              checked={isDarkMode === true}
              onChange={() => setIsDarkMode(true)}
              className="w-4 h-4 cursor-pointer"
            />
          </div>

          <div className={`flex items-center justify-between p-3 ${isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-300'} rounded-lg border transition-colors duration-300`}>
            <div className="flex items-center space-x-3">
              <div className={`w-8 h-8 ${isDarkMode ? 'bg-gray-900 border-gray-600' : 'bg-gray-100 border-gray-400'} rounded border flex items-center justify-center transition-colors duration-300`}>
                <div className="w-6 h-6 bg-blue-500 rounded" />
              </div>
              <div>
                <span className={`text-sm font-medium ${isDarkMode ? 'text-gray-50' : 'text-gray-900'} transition-colors duration-300`}>Light Mode</span>
                <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} transition-colors duration-300`}>Alternative theme - Better for day viewing</p>
              </div>
            </div>
            <input
              type="radio"
              name="theme"
              value="light"
              checked={isDarkMode === false}
              onChange={() => setIsDarkMode(false)}
              className="w-4 h-4 cursor-pointer"
            />
          </div>
        </div>
      </div>

      {/* System Customization */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-6 hover:border-amber-500/40 transition-all duration-300`}>
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} flex items-center transition-colors duration-300`}>
              <BuildingLibraryIcon className="h-4 w-4 mr-2" />
              System Customization
            </h3>
            <p className={`${isDarkMode ? 'text-gray-400' : 'text-gray-600'} text-xs mt-1 transition-colors duration-300`}>Customize system branding and settings</p>
          </div>
        </div>

        <div className="space-y-4">
          <div>
            <label className={`block text-xs font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-2 transition-colors duration-300`}>Application Name</label>
            <input
              type="text"
              value={systemSettings.appName}
              onChange={(e) => setSystemSettings({...systemSettings, appName: e.target.value})}
              className={`w-full px-3 py-2 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-gray-50 border-gray-300 text-gray-900'} border rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm transition-colors duration-300`}
              placeholder="e.g., Legal Ease"
            />
          </div>

          <div className={`flex items-center space-x-3 p-3 ${isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-300'} rounded-lg border transition-colors duration-300`}>
            <input
              type="checkbox"
              id="showLogo"
              checked={systemSettings.showLogo}
              onChange={(e) => setSystemSettings({...systemSettings, showLogo: e.target.checked})}
              className="w-4 h-4 cursor-pointer"
            />
            <label htmlFor="showLogo" className="flex-1 cursor-pointer">
              <span className={`text-sm font-medium ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300`}>Show Application Logo</span>
              <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} mt-0.5 transition-colors duration-300`}>Display logo in header and sidebar</p>
            </label>
          </div>
        </div>

        <div className={`mt-4 pt-4 border-t ${isDarkMode ? 'border-gray-700' : 'border-gray-300'} transition-colors duration-300`}>
          <button
            type="button"
            onClick={() => {
              localStorage.setItem('systemSettings', JSON.stringify(systemSettings));
              window.showToast?.('Success', 'System settings saved successfully!', 'success');
            }}
            className="w-full px-4 py-2 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded-lg hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm shadow border border-amber-500/30"
          >
            Save Settings
          </button>
        </div>
      </div>

      {/* Information */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-6 hover:border-amber-500/40 transition-all duration-300`}>
        <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} flex items-center mb-3 transition-colors duration-300`}>
          <ExclamationTriangleIcon className="h-4 w-4 mr-2" />
          System Information
        </h3>
        <div className={`space-y-2 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'} transition-colors duration-300`}>
          <p><strong>Application:</strong> {systemSettings.appName}</p>
          <p><strong>Version:</strong> 1.0.0</p>
          <p><strong>Theme:</strong> {isDarkMode ? 'Dark Mode' : 'Light Mode'}</p>
          <p><strong>Last Updated:</strong> {new Date().toLocaleDateString()}</p>
        </div>
      </div>
    </div>
  );

  // Admin Profile Management
  const renderAdminProfile = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-lg font-bold text-amber-50">Admin Profile Management</h2>
          <p className="text-gray-400 text-sm">Manage administrator accounts and permissions</p>
        </div>
        <div className="flex space-x-2">
          <button
            type="button"
            onClick={handleRefresh}
            className="px-3 py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-sm flex items-center"
            title="Refresh data"
          >
            <ArrowPathIcon className="h-3 w-3 mr-1" />
            Refresh
          </button>
          <button
            onClick={() => {
              setSelectedAdmin(null);
              setShowAdminModal(true);
            }}
            className="px-3 py-1.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm flex items-center shadow transform hover:-translate-y-0.5 border border-amber-500/30"
          >
            <PlusIcon className="h-3 w-3 mr-1" />
            Add Admin
          </button>
        </div>
      </div>

      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-3 space-y-3`}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div className="relative">
            <MagnifyingGlassIcon className={`absolute left-2 top-1/2 transform -translate-y-1/2 h-3 w-3 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <input
              type="text"
              placeholder="Search admins..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className={`w-full pl-7 pr-3 py-1.5 border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'}`}
            />
          </div>
          <div className={`flex items-center space-x-1 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
            <FunnelIcon className="h-3 w-3" />
            <span>{filteredAdmins.length} admins found</span>
          </div>
        </div>
      </div>

      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-hidden hover:border-amber-500/40 transition-all duration-300`}>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className={`${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'} transition-colors duration-300`}>
              <tr>
                <th className={`px-3 py-2 text-left text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} uppercase tracking-wider transition-colors duration-300`}>
                  Admin
                </th>
                <th className={`px-3 py-2 text-left text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} uppercase tracking-wider transition-colors duration-300`}>
                  Contact
                </th>
                <th className={`px-3 py-2 text-left text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} uppercase tracking-wider transition-colors duration-300`}>
                  Role
                </th>
                <th className={`px-3 py-2 text-left text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} uppercase tracking-wider transition-colors duration-300`}>
                  Status
                </th>
                <th className={`px-3 py-2 text-left text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} uppercase tracking-wider transition-colors duration-300`}>
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'} transition-colors duration-300`}>
              {paginatedAdmins.map((admin) => (
                <tr key={admin.id} className={`${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'} transition-colors duration-200 group`}>
                  <td className="px-3 py-2">
                    <div className="flex items-center space-x-2">
                      <div className={`w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shadow group-hover:scale-110 transition-transform ${isDarkMode ? 'bg-purple-500' : 'bg-purple-600'}`}>
                        {admin.first_name?.charAt(0)}{admin.last_name?.charAt(0)}
                      </div>
                      <div>
                        <div className={`text-xs font-medium ${isDarkMode ? 'text-amber-50 group-hover:text-amber-300' : 'text-amber-900 group-hover:text-amber-700'}`}>
                          {admin.first_name} {admin.last_name}
                        </div>
                        <div className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>ID: {admin.id}</div>
                      </div>
                    </div>
                  </td>
                  <td className="px-3 py-2">
                    <div className={`text-xs ${isDarkMode ? 'text-gray-300' : 'text-gray-900'}`}>{admin.email}</div>
                    {admin.phone && (
                      <div className={`text-xs flex items-center mt-0.5 ${isDarkMode ? 'text-gray-500' : 'text-gray-600'}`}>
                        <PhoneIcon className="h-2.5 w-2.5 mr-0.5" />
                        {admin.phone}
                      </div>
                    )}
                  </td>
                  <td className="px-3 py-2">
                    <span className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-full border transition-all duration-200 ${
                      admin.role === 'admin' ? isDarkMode ? 'bg-purple-500/20 text-purple-300 border-purple-500/30 shadow-purple-500/20 group-hover:shadow-purple-500/40' : 'bg-purple-100 text-purple-800 border-purple-300' :
                      isDarkMode ? 'bg-blue-500/20 text-blue-300 border-blue-500/30 shadow-blue-500/20 group-hover:shadow-blue-500/40' : 'bg-blue-100 text-blue-800 border-blue-300'
                    }`}>
                      {admin.role}
                    </span>
                  </td>
                  <td className="px-3 py-2">
                    <StatusBadge status={admin.is_active ? 'active' : 'inactive'} />
                  </td>
                  <td className="px-3 py-2 text-xs font-medium space-x-1">
                    <button
                      onClick={() => {
                        setSelectedAdmin(admin);
                        setShowAdminModal(true);
                      }}
                      className={`transition-colors duration-200 p-1 rounded border ${isDarkMode ? 'text-blue-400 hover:text-blue-300 hover:bg-blue-500/10 border-blue-500/30' : 'text-blue-600 hover:text-blue-700 hover:bg-blue-100 border-blue-300'}`}
                      title="View admin"
                    >
                      <EyeIcon className="h-3 w-3" />
                    </button>
                    <button
                      onClick={() => {
                        setSelectedAdmin(admin);
                        setShowAdminModal(true);
                      }}
                      className={`transition-colors duration-200 p-1 rounded border ${isDarkMode ? 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 border-amber-500/30' : 'text-amber-600 hover:text-amber-700 hover:bg-amber-100 border-amber-300'}`}
                      title="Edit admin"
                    >
                      <PencilIcon className="h-3 w-3" />
                    </button>
                    <button
                      onClick={() => handleToggleAdminStatus(admin)}
                      className={`transition-colors duration-200 p-1 rounded border ${
                        admin.is_active 
                          ? isDarkMode ? 'text-orange-400 hover:text-orange-300 hover:bg-orange-500/10 border-orange-500/30' : 'text-orange-600 hover:text-orange-700 hover:bg-orange-100 border-orange-300'
                          : isDarkMode ? 'text-green-400 hover:text-green-300 hover:bg-green-500/10 border-green-500/30' : 'text-green-600 hover:text-green-700 hover:bg-green-100 border-green-300'
                      }`}
                      title={admin.is_active ? 'Deactivate admin' : 'Activate admin'}
                    >
                      {admin.is_active ? 'Deactivate' : 'Activate'}
                    </button>
                    <button
                      onClick={() => {
                        setItemToDelete(admin);
                        setShowDeleteModal(true);
                      }}
                      className={`transition-colors duration-200 p-1 rounded border ${isDarkMode ? 'text-red-400 hover:text-red-300 hover:bg-red-500/10 border-red-500/30' : 'text-red-600 hover:text-red-700 hover:bg-red-100 border-red-300'}`}
                      title="Archive admin"
                    >
                      <ArchiveBoxIcon className="h-3 w-3" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {paginatedAdmins.length === 0 && (
          <div className="text-center py-6">
            <ShieldCheckIcon className="mx-auto h-12 w-12 text-gray-600" />
            <h3 className="mt-1 text-xs font-medium text-amber-50">No admins found</h3>
            <p className="mt-0.5 text-xs text-gray-400">
              {debouncedSearchTerm ? 'Try adjusting your search terms' : 'Get started by creating a new admin account'}
            </p>
            {debouncedSearchTerm && (
              <button
                onClick={() => setSearchTerm('')}
                className="mt-2 px-2 py-1 text-amber-400 hover:text-amber-300 text-xs font-medium"
              >
                Clear search
              </button>
            )}
          </div>
        )}

        {sortedAdmins.length > 0 && (
          <div className="px-3 py-2 border-t border-gray-700 bg-gray-800/50">
            <div className="flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
              <p className="text-xs text-gray-400">
                Showing <span className="font-medium text-amber-50">{((currentPage - 1) * itemsPerPage) + 1}</span> to{' '}
                <span className="font-medium text-amber-50">{Math.min(currentPage * itemsPerPage, sortedAdmins.length)}</span> of{' '}
                <span className="font-medium text-amber-50">{sortedAdmins.length}</span> results
              </p>
              <div className="flex items-center space-x-1">
                <button
                  onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                  disabled={currentPage === 1}
                  className="px-2 py-1 border border-gray-600 rounded hover:bg-gray-700 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-1 focus:ring-amber-500 text-gray-300 text-xs flex items-center"
                >
                  <ChevronLeftIcon className="h-3 w-3 mr-0.5" />
                  Prev
                </button>
                <div className="flex space-x-0.5">
                  {[...Array(Math.ceil(sortedAdmins.length / itemsPerPage))].slice(0, 5).map((_, index) => (
                    <button
                      key={index + 1}
                      onClick={() => setCurrentPage(index + 1)}
                      className={`px-2 py-1 rounded transition-colors duration-200 focus:outline-none focus:ring-1 focus:ring-amber-500 text-xs ${
                        currentPage === index + 1
                          ? 'bg-amber-500 text-white'
                          : 'border border-gray-600 text-gray-300 hover:bg-gray-700'
                      }`}
                    >
                      {index + 1}
                    </button>
                  ))}
                </div>
                <button
                  onClick={() => setCurrentPage(prev => prev + 1)}
                  disabled={currentPage * itemsPerPage >= sortedAdmins.length}
                  className="px-2 py-1 border border-gray-600 rounded hover:bg-gray-700 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-1 focus:ring-amber-500 text-gray-300 text-xs flex items-center"
                >
                  Next
                  <ChevronRightIcon className="h-3 w-3 ml-0.5" />
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );

  // Dashboard render function
  const renderDashboard = () => {
    // Today's appointments
    const todayStr = new Date().toISOString().split('T')[0];
    const todayAppointments = (appointments || []).filter(a => {
      const d = (a.appointment_date || '').split('T')[0];
      return d === todayStr;
    });
    const todayPending = todayAppointments.filter(a => a.status === 'pending').length;
    const todayApproved = todayAppointments.filter(a => a.status === 'approved').length;
    const todayCompleted = todayAppointments.filter(a => a.status === 'completed').length;
    const todayRevenue = todayAppointments
      .filter(a => a.status === 'completed')
      .reduce((sum, a) => sum + parseFloat(a.payment_amount || a.original_price || 0), 0);

    return (
    <div className="space-y-5">
      {/* Stat Cards */}
      <QuickStats
        stats={stats}
        onStatClick={setActiveTab}
        isDarkMode={isDarkMode}
        appointmentsByPeriod={appointmentsByPeriod}
        revenueByPeriod={revenueByPeriod}
      />

      {/* Middle Row: Today's Overview + Appointment Status Donut */}
      <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">
        {/* Today's Overview — collapsible, takes 1 col */}
        <div className={`rounded-xl border overflow-hidden transition-all duration-300 ${isDarkMode ? 'bg-gray-900/80 border-gray-800' : 'bg-white border-gray-200'}`}>
          <button
            onClick={() => setSnapshotCollapsed(prev => !prev)}
            className={`w-full flex items-center justify-between px-5 py-3.5 transition-colors ${isDarkMode ? 'hover:bg-gray-800/40' : 'hover:bg-gray-50'}`}
          >
            <div className="flex items-center gap-2">
              <div className="p-1.5 rounded-lg bg-gradient-to-br from-amber-500 to-amber-600">
                <BoltIcon className="h-4 w-4 text-white" />
              </div>
              <h3 className={`text-sm font-bold ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>Today's Snapshot</h3>
              {snapshotCollapsed && (
                <span className={`text-xs font-medium ml-1 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                  — {todayAppointments.length} appt{todayAppointments.length !== 1 ? 's' : ''}
                </span>
              )}
            </div>
            <ChevronDownIcon className={`h-4 w-4 transition-transform duration-200 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} ${snapshotCollapsed ? '-rotate-90' : ''}`} />
          </button>
          {!snapshotCollapsed && (
            <div className="px-5 pb-4 space-y-2">
              {[
                { label: 'Appointments', val: todayAppointments.length, color: 'text-blue-400', bg: isDarkMode ? 'bg-blue-500/10' : 'bg-blue-50' },
                { label: 'Pending', val: todayPending, color: 'text-amber-400', bg: isDarkMode ? 'bg-amber-500/10' : 'bg-amber-50' },
                { label: 'Approved', val: todayApproved, color: 'text-sky-400', bg: isDarkMode ? 'bg-sky-500/10' : 'bg-sky-50' },
                { label: 'Completed', val: todayCompleted, color: 'text-emerald-400', bg: isDarkMode ? 'bg-emerald-500/10' : 'bg-emerald-50' },
                { label: 'Revenue', val: `₱${todayRevenue.toLocaleString(undefined, { minimumFractionDigits: 0 })}`, color: 'text-emerald-400', bg: isDarkMode ? 'bg-emerald-500/10' : 'bg-emerald-50' },
              ].map((row, i) => (
                <div key={i} className={`flex items-center justify-between rounded-lg px-3 py-2 ${row.bg}`}>
                  <span className={`text-xs font-medium ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{row.label}</span>
                  <span className={`text-sm font-bold ${row.color}`}>{row.val}</span>
                </div>
              ))}
              {todayAppointments.length === 0 && (
                <p className={`text-xs text-center pt-1 ${isDarkMode ? 'text-gray-600' : 'text-gray-400'}`}>No appointments today</p>
              )}
            </div>
          )}
        </div>

        {/* Appointment Status Donut — takes 2 cols */}
        <div className="xl:col-span-2">
          <PieChart
            data={appointmentStatusData}
            title="Appointment Status"
            isDarkMode={isDarkMode}
          />
        </div>
      </div>

      {/* Bottom Row: Trends side by side */}
      <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <LineChart
          data={appointmentsByPeriod}
          title={`${timeframe.charAt(0).toUpperCase() + timeframe.slice(1)} Appointments`}
          color="blue"
          isDarkMode={isDarkMode}
        />
        <BarChart
          data={revenueByPeriod}
          title="Revenue"
          color="green"
          height={160}
          isDarkMode={isDarkMode}
        />
      </div>

    </div>
    );
  };

  // Users render function
  const renderUsers = () => (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-lg font-bold text-amber-50">User Management</h2>
          <p className="text-gray-400 text-sm">Manage system users and permissions</p>
        </div>
        <div className="flex space-x-2">
          <button
            onClick={handleRefresh}
            className="px-3 py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-sm flex items-center"
            title="Refresh data"
          >
            <ArrowPathIcon className="h-3 w-3 mr-1" />
            Refresh
          </button>
          <button
            onClick={() => {
              setSelectedUser(null);
              setShowUserModal(true);
            }}
            className="px-3 py-1.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm flex items-center shadow transform hover:-translate-y-0.5 border border-amber-500/30"
          >
            <PlusIcon className="h-3 w-3 mr-1" />
            Add User
          </button>
        </div>
      </div>

      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-3 space-y-3 transition-colors duration-300`}>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div className="relative">
            <MagnifyingGlassIcon className={`absolute left-2 top-1/2 transform -translate-y-1/2 h-3 w-3 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <input
              type="text"
              placeholder="Search users..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className={`w-full pl-7 pr-3 py-1.5 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white placeholder-gray-400' : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-500'} border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm`}
            />
          </div>
          <div>
            <select
              value={roleFilter}
              onChange={(e) => setRoleFilter(e.target.value)}
              className={`w-full px-3 py-1.5 ${isDarkMode ? 'bg-gray-800 border-gray-600 text-white' : 'bg-gray-50 border-gray-300 text-gray-900'} border rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm`}
            >
              <option value="all">All Roles</option>
              <option value="client">Client</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div className={`flex items-center space-x-1 text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>
            <FunnelIcon className="h-3 w-3" />
            <span>{filteredUsers.length} users found</span>
          </div>
        </div>
      </div>

      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-hidden hover:border-amber-500/40 transition-all duration-300`}>
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className={`${isDarkMode ? 'bg-gray-800' : 'bg-gray-50'} transition-colors duration-300`}>
              <tr>
                <th 
                  className={`px-3 py-2 text-left text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} uppercase tracking-wider cursor-pointer ${isDarkMode ? 'hover:bg-gray-750' : 'hover:bg-gray-100'} transition-colors duration-200 group`}
                  onClick={() => handleSort('first_name')}
                >
                  <div className="flex items-center">
                    User
                    <ChevronUpDownIcon className={`h-3 w-3 ml-1 ${isDarkMode ? 'group-hover:text-amber-300' : 'group-hover:text-amber-700'}`} />
                  </div>
                </th>
                <th className={`px-3 py-2 text-left text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} uppercase tracking-wider transition-colors duration-300`}>
                  Contact
                </th>
                <th 
                  className={`px-3 py-2 text-left text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} uppercase tracking-wider cursor-pointer ${isDarkMode ? 'hover:bg-gray-750' : 'hover:bg-gray-100'} transition-colors duration-200 group`}
                  onClick={() => handleSort('role')}
                >
                  <div className="flex items-center">
                    Role
                    <ChevronUpDownIcon className={`h-3 w-3 ml-1 ${isDarkMode ? 'group-hover:text-amber-300' : 'group-hover:text-amber-700'}`} />
                  </div>
                </th>
                <th className={`px-3 py-2 text-left text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} uppercase tracking-wider transition-colors duration-300`}>
                  Status
                </th>
                <th className={`px-3 py-2 text-left text-xs font-medium ${isDarkMode ? 'text-amber-400' : 'text-amber-600'} uppercase tracking-wider transition-colors duration-300`}>
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className={`divide-y ${isDarkMode ? 'divide-gray-700' : 'divide-gray-200'} transition-colors duration-300`}>
              {paginatedUsers.map((userItem) => (
                <tr key={userItem.id} className={`${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'} transition-colors duration-200 group`}>
                  <td className="px-3 py-2">
                    <div className="flex items-center space-x-2">
                      <div className="w-8 h-8 bg-amber-500 rounded-full flex items-center justify-center text-gray-900 text-xs font-bold shadow group-hover:scale-110 transition-transform">
                        {userItem.first_name?.charAt(0)}{userItem.last_name?.charAt(0)}
                      </div>
                      <div>
                        <div className="text-xs font-medium text-amber-50 group-hover:text-amber-300">
                          {userItem.first_name} {userItem.last_name}
                        </div>
                        <div className="text-xs text-gray-400">ID: {userItem.id}</div>
                      </div>
                    </div>
                  </td>
                  <td className="px-3 py-2">
                    <div className="text-xs text-gray-300">{userItem.email}</div>
                    {userItem.phone && (
                      <div className="text-xs text-gray-500 flex items-center mt-0.5">
                        <PhoneIcon className="h-2.5 w-2.5 mr-0.5" />
                        {userItem.phone}
                      </div>
                    )}
                  </td>
                  <td className="px-3 py-2">
                    <span className={`inline-flex px-2 py-0.5 text-xs font-semibold rounded-full border transition-all duration-200 ${
                      userItem.role === 'admin' ? (isDarkMode ? 'bg-purple-500/20 text-purple-300 border-purple-500/30 shadow-purple-500/20 group-hover:shadow-purple-500/40' : 'bg-purple-100 text-purple-800 border-purple-300') :
                      (isDarkMode ? 'bg-green-500/20 text-green-300 border-green-500/30 shadow-green-500/20 group-hover:shadow-green-500/40' : 'bg-green-100 text-green-800 border-green-300')
                    }`}>{userItem.role}
                    </span>
                  </td>
                  <td className="px-3 py-2">
                    <StatusBadge status={userItem.is_active ? 'active' : 'inactive'} />
                  </td>
                  <td className="px-3 py-2 text-xs font-medium space-x-1">
                    <button
                      onClick={() => {
                        setSelectedUser(userItem);
                        setShowUserDetailModal(true);
                      }}
                      className="text-blue-400 hover:text-blue-300 transition-colors duration-200 p-1 rounded hover:bg-blue-500/10 border border-blue-500/30"
                      title="View details"
                    >
                      <EyeIcon className="h-3 w-3" />
                    </button>
                    <button
                      onClick={() => {
                        setSelectedUser(userItem);
                        setShowMessageModal(true);
                      }}
                      className="text-green-400 hover:text-green-300 transition-colors duration-200 p-1 rounded hover:bg-green-500/10 border border-green-500/30"
                      title="Send message"
                    >
                      <ChatBubbleLeftRightIcon className="h-3 w-3" />
                    </button>
                    <button
                      onClick={() => {
                        setAccountActionUser(userItem);
                        setAccountActionType('deactivated');
                        setAccountActionReason('');
                        setAccountActionError('');
                        setShowAccountActionModal(true);
                      }}
                      className="text-orange-400 hover:text-orange-300 transition-colors duration-200 p-1 rounded hover:bg-orange-500/10 border border-orange-500/30"
                      title="Account action (delete/block/deactivate)"
                    >
                      <ExclamationTriangleIcon className="h-3 w-3" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {paginatedUsers.length === 0 && (
          <div className="text-center py-6">
            <UserGroupIcon className="mx-auto h-12 w-12 text-gray-600" />
            <h3 className="mt-1 text-xs font-medium text-amber-50">No users found</h3>
            <p className="mt-0.5 text-xs text-gray-400">
              {debouncedSearchTerm || roleFilter !== 'all' ? 'Try adjusting your search terms or filters' : 'Get started by creating a new user'}
            </p>
            {(debouncedSearchTerm || roleFilter !== 'all') && (
              <button
                onClick={() => {
                  setSearchTerm('');
                  setRoleFilter('all');
                }}
                className="mt-2 px-2 py-1 text-amber-400 hover:text-amber-300 text-xs font-medium"
              >
                Clear filters
              </button>
            )}
          </div>
        )}

        {sortedUsers.length > 0 && (
          <div className="px-3 py-2 border-t border-gray-700 bg-gray-800/50">
            <div className="flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
              <p className="text-xs text-gray-400">
                Showing <span className="font-medium text-amber-50">{((currentPage - 1) * itemsPerPage) + 1}</span> to{' '}
                <span className="font-medium text-amber-50">{Math.min(currentPage * itemsPerPage, sortedUsers.length)}</span> of{' '}
                <span className="font-medium text-amber-50">{sortedUsers.length}</span> results
              </p>
              <div className="flex items-center space-x-1">
                <button
                  onClick={() => setCurrentPage(prev => Math.max(prev - 1, 1))}
                  disabled={currentPage === 1}
                  className="px-2 py-1 border border-gray-600 rounded hover:bg-gray-700 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-1 focus:ring-amber-500 text-gray-300 text-xs flex items-center"
                >
                  <ChevronLeftIcon className="h-3 w-3 mr-0.5" />
                  Prev
                </button>
                <div className="flex space-x-0.5">
                  {[...Array(Math.ceil(sortedUsers.length / itemsPerPage))].slice(0, 5).map((_, index) => (
                    <button
                      key={index + 1}
                      onClick={() => setCurrentPage(index + 1)}
                      className={`px-2 py-1 rounded transition-colors duration-200 focus:outline-none focus:ring-1 focus:ring-amber-500 text-xs ${
                        currentPage === index + 1
                          ? 'bg-amber-500 text-white'
                          : 'border border-gray-600 text-gray-300 hover:bg-gray-700'
                      }`}
                    >
                      {index + 1}
                    </button>
                  ))}
                </div>
                <button
                  onClick={() => setCurrentPage(prev => prev + 1)}
                  disabled={currentPage * itemsPerPage >= sortedUsers.length}
                  className="px-2 py-1 border border-gray-600 rounded hover:bg-gray-700 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-1 focus:ring-amber-500 text-gray-300 text-xs flex items-center"
                >
                  Next
                  <ChevronRightIcon className="h-3 w-3 ml-0.5" />
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );

  // Appointments render function
  const renderAppointments = () => {
    // Today's date string for comparisons
    const _t = new Date();
    const todayDateStr = `${_t.getFullYear()}-${String(_t.getMonth()+1).padStart(2,'0')}-${String(_t.getDate()).padStart(2,'0')}`;

    // Helper to normalize any date value to YYYY-MM-DD string
    const normalizeDate = (dateVal) => {
      if (!dateVal) return '';
      if (typeof dateVal === 'string') {
        const match = dateVal.match(/^(\d{4}-\d{2}-\d{2})/);
        return match ? match[1] : '';
      }
      return '';
    };

    // Helper to display a date string without timezone shift issues
    // Uses local Date constructor (year, month, day) to avoid UTC→local conversion
    const formatDateDisplay = (dateVal) => {
      const d = normalizeDate(dateVal);
      if (!d) return '';
      const [y, m, day] = d.split('-');
      return new Date(+y, +m - 1, +day).toLocaleDateString();
    };

    // Filter appointments by tab
    const tabFilteredAppointments = appointmentTab === 'all' 
      ? appointments 
      : appointments.filter(apt => {
          if (appointmentTab === 'pending') return apt.status === 'pending';
          if (appointmentTab === 'approved') return apt.status === 'approved';
          if (appointmentTab === 'declined') return apt.status === 'declined';
          return true;
        });

    // Count by tab
    const pendingCount = appointments.filter(apt => apt.status === 'pending').length;
    const approvedCount = appointments.filter(apt => apt.status === 'approved').length;
    const declinedCount = appointments.filter(apt => apt.status === 'declined').length;

    // Apply date filter FIRST (calendar modal selection)
    let dateFiltered = tabFilteredAppointments;
    if (dateFilter) {
      if (dateFilter.includes('..')) {
        // Range filter: "YYYY-MM-DD..YYYY-MM-DD"
        const [startStr, endStr] = dateFilter.split('..');
        dateFiltered = tabFilteredAppointments.filter(apt => {
          const aptDate = normalizeDate(apt.appointment_date);
          return aptDate >= startStr && aptDate <= endStr;
        });
      } else {
        // Single date filter
        dateFiltered = tabFilteredAppointments.filter(apt => {
          const aptDate = normalizeDate(apt.appointment_date);
          return aptDate === dateFilter;
        });
      }
    }

    // Apply existing filters to date-filtered appointments
    let filtered = dateFiltered.filter(apt => {
      const matchesSearch = !debouncedSearchTerm || 
        apt.user?.first_name?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
        apt.user?.last_name?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
        apt.user?.email?.toLowerCase().includes(debouncedSearchTerm.toLowerCase()) ||
        apt.service_type?.toLowerCase().includes(debouncedSearchTerm.toLowerCase());
      
      const matchesStatus = statusFilter === 'all' || apt.status === statusFilter;
      
      return matchesSearch && matchesStatus;
    });

    // Apply sorting
    filtered = [...filtered].sort((a, b) => {
      let aValue, bValue;
      
      switch(appointmentSort.key) {
        case 'created_at':
          aValue = new Date(a.created_at);
          bValue = new Date(b.created_at);
          break;
        case 'appointment_date':
          aValue = new Date(a.appointment_date);
          bValue = new Date(b.appointment_date);
          break;
        case 'client_name':
          aValue = (a.user?.first_name + ' ' + a.user?.last_name).toLowerCase();
          bValue = (b.user?.first_name + ' ' + b.user?.last_name).toLowerCase();
          break;
        case 'status':
          aValue = a.status;
          bValue = b.status;
          break;
        case 'service_type':
          aValue = a.service_type || a.type;
          bValue = b.service_type || b.type;
          break;
        default:
          return 0;
      }
      
      if (aValue < bValue) return appointmentSort.direction === 'asc' ? -1 : 1;
      if (aValue > bValue) return appointmentSort.direction === 'asc' ? 1 : -1;
      return 0;
    });

    // Calculate pagination
    const totalPages = Math.ceil(filtered.length / appointmentsPerPage);
    const startIdx = (appointmentPage - 1) * appointmentsPerPage;
    const paginatedAppointments = filtered.slice(startIdx, startIdx + appointmentsPerPage);

    return (
    <div className="space-y-4">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h2 className={`text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>Appointment Management</h2>
          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Manage and review all appointments</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => setShowCalendarModal(true)}
            className={`px-3 py-1.5 border rounded text-sm font-medium flex items-center gap-1.5 transition-all duration-200 ${
              isDarkMode
                ? 'border-amber-500/40 text-amber-300 hover:bg-amber-500/10'
                : 'border-amber-400 text-amber-700 hover:bg-amber-50'
            }`}
            title="Open calendar filter"
          >
            <CalendarDaysIcon className="h-4 w-4" />
            Calendar
          </button>
          {dateFilter && (
            <button
              type="button"
              onClick={() => { setDateFilter(null); setDateFilterLabel('All Appointments'); setAppointmentPage(1); }}
              className={`px-3 py-1.5 border rounded text-sm font-medium flex items-center gap-1 transition-all duration-200 ${
                isDarkMode
                  ? 'border-gray-600 text-gray-300 hover:bg-gray-800'
                  : 'border-gray-300 text-gray-600 hover:bg-gray-100'
              }`}
              title="View all appointments"
            >
              📋 View All
            </button>
          )}
          {dateFilter !== todayDateStr && (
            <button
              type="button"
              onClick={() => {
                setDateFilter(todayDateStr);
                setDateFilterLabel(`Today (${todayDateStr})`);
                setAppointmentTab('all');
                setStatusFilter('all');
                setAppointmentPage(1);
              }}
              className={`px-3 py-1.5 border rounded text-sm font-medium flex items-center gap-1 transition-all duration-200 ${
                isDarkMode
                  ? 'border-emerald-500/40 text-emerald-300 hover:bg-emerald-500/10'
                  : 'border-emerald-400 text-emerald-700 hover:bg-emerald-50'
              }`}
              title="Show today's appointments"
            >
              📅 Today
            </button>
          )}
          <button
            onClick={handleRefresh}
            className={`px-3 py-1.5 border rounded font-medium text-sm flex items-center transition-all duration-200 ${
              isDarkMode
                ? 'border-amber-500/30 text-amber-50 hover:bg-amber-500/10'
                : 'border-amber-300 text-amber-700 hover:bg-amber-50'
            }`}
            title="Refresh data"
          >
            <ArrowPathIcon className="h-3 w-3 mr-1" />
            Refresh
          </button>
        </div>
      </div>

      {/* Date filter indicator */}
      <div className={`flex items-center gap-2 px-3 py-2 rounded-lg text-sm ${
        isDarkMode
          ? 'bg-gray-800/60 border border-gray-700/50 text-gray-300'
          : 'bg-gray-50 border border-gray-200 text-gray-600'
      }`}>
        <CalendarDaysIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
        <span>
          Showing appointments for: <strong className={isDarkMode ? 'text-amber-200' : 'text-amber-700'}>{dateFilterLabel}</strong>
        </span>
        <span className={`ml-auto text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
          {filtered.length} result{filtered.length !== 1 ? 's' : ''}
        </span>
      </div>

      {/* Calendar Modal */}
      <AppointmentCalendarModal
        isOpen={showCalendarModal}
        onClose={() => setShowCalendarModal(false)}
        onSelectDate={(date, label) => {
          setDateFilter(date);
          setDateFilterLabel(label);
          setAppointmentPage(1);
        }}
        selectedDate={dateFilter}
        isDarkMode={isDarkMode}
      />

      {/* Appointment Tabs */}
      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-x-auto`}>
        <div className="flex space-x-0">
          {[
            { key: 'all', label: 'All', count: stats.totalAppointments || appointments.length },
            { key: 'pending', label: 'New', count: pendingCount, color: 'amber' },
            { key: 'approved', label: 'Approved', count: approvedCount, color: 'green' },
            { key: 'declined', label: 'Declined', count: declinedCount, color: 'red' }
          ].map(tab => (
            <button
              key={tab.key}
              onClick={() => {
                setAppointmentTab(tab.key);
                setAppointmentPage(1);
              }}
              className={`flex-1 px-4 py-3 text-sm font-medium transition-all duration-200 border-b-2 whitespace-nowrap ${
                appointmentTab === tab.key
                  ? tab.key === 'pending' ? 'border-amber-400 text-amber-50 bg-amber-500/5' :
                    tab.key === 'approved' ? 'border-green-400 text-green-50 bg-green-500/5' :
                    tab.key === 'declined' ? 'border-red-400 text-red-50 bg-red-500/5' :
                    'border-amber-400 text-amber-50 bg-amber-500/5'
                  : 'border-transparent text-gray-400 hover:text-gray-300'
              }`}
            >
              <span>{tab.label}</span>
              <span className={`ml-2 px-2 py-0.5 rounded-full text-xs font-bold ${
                appointmentTab === tab.key
                  ? tab.key === 'pending' ? 'bg-amber-500/30 text-amber-200' :
                    tab.key === 'approved' ? 'bg-green-500/30 text-green-200' :
                    tab.key === 'declined' ? 'bg-red-500/30 text-red-200' :
                    'bg-amber-500/30 text-amber-200'
                  : 'bg-gray-700 text-gray-300'
              }`}>
                {tab.count}
              </span>
            </button>
          ))}
        </div>
      </div>

      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-3 space-y-3`}>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div className="relative">
            <MagnifyingGlassIcon className="absolute left-2 top-1/2 transform -translate-y-1/2 h-3 w-3 text-amber-400" />
            <input
              type="text"
              placeholder="Search appointments..."
              value={searchTerm}
              onChange={(e) => {
                setSearchTerm(e.target.value);
                setAppointmentPage(1);
              }}
              className="w-full pl-7 pr-3 py-1.5 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white placeholder-gray-400"
            />
          </div>
          <div>
            <select
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setAppointmentPage(1);
              }}
              className="w-full px-3 py-1.5 bg-gray-800 border border-gray-600 rounded-lg focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all duration-200 text-sm text-white"
            >
              <option value="all">All Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
              <option value="declined">Declined</option>
            </select>
          </div>
          <div className="flex items-center space-x-1 text-xs text-gray-400">
            <FunnelIcon className="h-3 w-3" />
            <span>{filtered.length} appointments found</span>
          </div>
        </div>
      </div>

      <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-hidden hover:border-amber-500/40 transition-all duration-300`}>
        <div className="overflow-x-auto -mx-4 sm:mx-0">
          <table className="w-full min-w-full">
            <thead className="bg-gray-800">
              <tr>
                <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase tracking-wider cursor-pointer hover:bg-gray-700 transition-colors" onClick={() => handleAppointmentSort('client_name')}>
                  <div className="flex items-center gap-1">
                    Client
                    {appointmentSort.key === 'client_name' && (
                      <span className="text-amber-300">{appointmentSort.direction === 'asc' ? '↑' : '↓'}</span>
                    )}
                  </div>
                </th>
                <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase tracking-wider hidden sm:table-cell">
                  Type & Details
                </th>
                <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase tracking-wider hidden lg:table-cell">
                  Price
                </th>
                <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase tracking-wider cursor-pointer hover:bg-gray-700 transition-colors" onClick={() => handleAppointmentSort('appointment_date')}>
                  <div className="flex items-center gap-1">
                    Date
                    {appointmentSort.key === 'appointment_date' && (
                      <span className="text-amber-300">{appointmentSort.direction === 'asc' ? '↑' : '↓'}</span>
                    )}
                  </div>
                </th>
                <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase tracking-wider hidden md:table-cell cursor-pointer hover:bg-gray-700 transition-colors" onClick={() => handleAppointmentSort('created_at')}>
                  <div className="flex items-center gap-1">
                    Booked
                    {appointmentSort.key === 'created_at' && (
                      <span className="text-amber-300">{appointmentSort.direction === 'asc' ? '↑' : '↓'}</span>
                    )}
                  </div>
                </th>
                <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase tracking-wider hidden md:table-cell cursor-pointer hover:bg-gray-700 transition-colors" onClick={() => handleAppointmentSort('status')}>
                  <div className="flex items-center gap-1">
                    Status
                    {appointmentSort.key === 'status' && (
                      <span className="text-amber-300">{appointmentSort.direction === 'asc' ? '↑' : '↓'}</span>
                    )}
                  </div>
                </th>
                <th className="px-3 py-2 text-left text-xs font-medium text-amber-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-700">
              {paginatedAppointments.map((appointment) => (
                <tr key={appointment.id} className="hover:bg-gray-800 transition-colors duration-200 group">
                  <td className="px-3 py-2">
                    <div className="flex items-center space-x-2">
                      <div className="w-8 h-8 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <UserGroupIcon className="h-4 w-4 text-blue-400" />
                      </div>
                      <div>
                        <div className="text-xs font-medium text-amber-50 group-hover:text-amber-300">
                          {appointment.user?.first_name} {appointment.user?.last_name}
                        </div>
                        <div className="text-xs text-gray-400">{appointment.user?.email}</div>
                        {appointment.user?.phone && (
                          <div className="text-xs text-gray-500 flex items-center mt-0.5">
                            <PhoneIcon className="h-2.5 w-2.5 mr-0.5" />
                            {appointment.user?.phone}
                          </div>
                        )}
                      </div>
                    </div>
                  </td>
                  <td className="px-3 py-2">
                    <div className="text-xs text-amber-50 font-medium">
                      {appointment.services && appointment.services.length > 0 
                        ? appointment.services.map(s => s.name).join(', ')
                        : formatServiceName(appointment)}
                    </div>
                    {appointment.decline_reason && (
                      <div className="text-xs text-red-400 mt-0.5 font-medium">Reason: {appointment.decline_reason}</div>
                    )}
                    {appointment.cancellation_reason && (
                      <div className="text-xs text-red-400 mt-0.5 font-medium">Cancel reason: {appointment.cancellation_reason}</div>
                    )}
                  </td>
                  <td className="px-3 py-2 hidden lg:table-cell">
                    <div className="text-xs text-amber-50 font-medium">
                      {appointment.payment_amount ? formatPrice(appointment.payment_amount) : 
                       appointment.original_price ? formatPrice(appointment.original_price) : 
                       appointment.service?.price ? formatPrice(appointment.service.price) : '—'}
                    </div>
                  </td>
                  <td className="px-3 py-2">
                    <div className="text-xs text-amber-50">
                      {formatDateDisplay(appointment.appointment_date)}
                    </div>
                    <div className="text-xs text-gray-400 hidden sm:block">{appointment.appointment_time}</div>
                  </td>
                  <td className="px-3 py-2 hidden md:table-cell">
                    <div className="text-xs text-amber-50">
                      {new Date(appointment.created_at).toLocaleDateString()}
                    </div>
                    <div className="text-xs text-gray-400">{new Date(appointment.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                  </td>
                  <td className="px-3 py-2 hidden md:table-cell">
                    <StatusBadge status={appointment.status} />
                  </td>
                  <td className="px-3 py-2 text-xs font-medium">
                    <div className="flex flex-wrap gap-1">
                      {appointment.status === 'pending' && (
                        <>
                          <button
                            onClick={() => handleAppointmentAction(appointment.id, 'approve')}
                            className="text-green-400 hover:text-green-300 transition-colors duration-200 font-medium hover:bg-green-500/10 px-2 py-1 rounded border border-green-500/30 hover:border-green-400 text-xs whitespace-nowrap"
                          >
                            Approve
                          </button>
                          <button
                            onClick={() => {
                              setAppointmentToDecline(appointment);
                              setShowDeclineModal(true);
                            }}
                            className="text-red-400 hover:text-red-300 transition-colors duration-200 font-medium hover:bg-red-500/10 px-2 py-1 rounded border border-red-500/30 hover:border-red-400 text-xs whitespace-nowrap"
                          >
                            Decline
                          </button>
                        </>
                      )}
                      {appointment.status === 'approved' && (
                        <>
                          <button
                            onClick={() => handleAppointmentAction(appointment.id, 'complete')}
                            className="text-green-400 hover:text-green-300 transition-colors duration-200 font-medium hover:bg-green-500/10 px-2 py-1 rounded border border-green-500/30 hover:border-green-400 text-xs whitespace-nowrap"
                          >
                            ✓ Complete
                          </button>
                          <button
                            onClick={() => handleAppointmentAction(appointment.id, 'no-show')}
                            className="text-orange-400 hover:text-orange-300 transition-colors duration-200 font-medium hover:bg-orange-500/10 px-2 py-1 rounded border border-orange-500/30 hover:border-orange-400 text-xs whitespace-nowrap"
                          >
                            ✗ No Show
                          </button>
                        </>
                      )}
                      {(appointment.status === 'completed' || appointment.status === 'cancelled' || appointment.status === 'declined' || appointment.status === 'no_show') && (
                        <button
                          onClick={() => handleAppointmentAction(appointment.id, 'pending')}
                          className="text-amber-400 hover:text-amber-300 transition-colors duration-200 font-medium hover:bg-amber-500/10 px-2 py-1 rounded border border-amber-500/30 hover:border-amber-400 text-xs whitespace-nowrap"
                        >
                          Reset
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {paginatedAppointments.length === 0 && (
          <div className="text-center py-6">
            <CalendarIcon className="mx-auto h-12 w-12 text-gray-600" />
            <h3 className="mt-1 text-xs font-medium text-amber-50">No appointments found</h3>
            <p className="mt-0.5 text-xs text-gray-400">
              {dateFilter
                ? `No appointments scheduled for ${dateFilterLabel}`
                : debouncedSearchTerm || statusFilter !== 'all'
                  ? 'Try adjusting your search terms or filters'
                  : 'No appointments in this category'}
            </p>
            {(dateFilter || debouncedSearchTerm || statusFilter !== 'all') && (
              <button
                onClick={() => {
                  setSearchTerm('');
                  setStatusFilter('all');
                  setDateFilter(null);
                  setDateFilterLabel('All Appointments');
                  setAppointmentPage(1);
                }}
                className="mt-2 px-2 py-1 text-amber-400 hover:text-amber-300 text-xs font-medium"
              >
                Clear all filters
              </button>
            )}
          </div>
        )}

        {/* Pagination Controls */}
        <div className={`border-t border-gray-700 bg-gray-800/50 px-4 py-3`}>
          <div className="flex flex-col md:flex-row justify-between items-center gap-4">
            {/* Left: Items per page selector */}
            <div className="flex items-center gap-2">
              <label className="text-xs text-gray-400 font-medium">Show:</label>
              <select
                value={appointmentsPerPage}
                onChange={(e) => {
                  setAppointmentsPerPage(Number(e.target.value));
                  setAppointmentPage(1); // Reset to first page
                }}
                className="px-2 py-1 text-xs bg-gray-700 border border-gray-600 rounded text-gray-300 hover:border-amber-500/40 focus:outline-none focus:border-amber-500 transition-all"
              >
                <option value={5}>5 per page</option>
                <option value={10}>10 per page</option>
                <option value={15}>15 per page</option>
                <option value={20}>20 per page</option>
                <option value={50}>50 per page</option>
                <option value={100}>100 per page</option>
              </select>
              <span className="text-xs text-gray-400">
                Showing {filtered.length > 0 ? startIdx + 1 : 0}-{Math.min(startIdx + appointmentsPerPage, filtered.length)} of {filtered.length}
              </span>
            </div>

            {/* Center: Page navigation */}
            {totalPages > 1 && (
              <div className="flex items-center gap-1">
                <button
                  onClick={() => setAppointmentPage(1)}
                  disabled={appointmentPage === 1}
                  className="px-2 py-1 text-xs font-medium text-gray-300 border border-gray-600 rounded hover:border-amber-500/40 hover:text-amber-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                  title="First page"
                >
                  ⏮
                </button>
                <button
                  onClick={() => setAppointmentPage(Math.max(1, appointmentPage - 1))}
                  disabled={appointmentPage === 1}
                  className="px-2 py-1 text-xs font-medium text-gray-300 border border-gray-600 rounded hover:border-amber-500/40 hover:text-amber-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                  title="Previous page"
                >
                  ←
                </button>
                
                {/* Page numbers */}
                <div className="flex items-center gap-1">
                  {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                    let pageNum;
                    if (totalPages <= 5) {
                      pageNum = i + 1;
                    } else if (appointmentPage <= 3) {
                      pageNum = i + 1;
                    } else if (appointmentPage >= totalPages - 2) {
                      pageNum = totalPages - 4 + i;
                    } else {
                      pageNum = appointmentPage - 2 + i;
                    }
                    return (
                      <button
                        key={pageNum}
                        onClick={() => setAppointmentPage(pageNum)}
                        className={`px-2 py-1 text-xs font-medium rounded transition-all ${
                          appointmentPage === pageNum
                            ? 'bg-amber-500/30 border border-amber-400 text-amber-300'
                            : 'text-gray-300 border border-gray-600 hover:border-amber-500/40 hover:text-amber-400'
                        }`}
                      >
                        {pageNum}
                      </button>
                    );
                  })}
                </div>

                <button
                  onClick={() => setAppointmentPage(Math.min(totalPages, appointmentPage + 1))}
                  disabled={appointmentPage === totalPages}
                  className="px-2 py-1 text-xs font-medium text-gray-300 border border-gray-600 rounded hover:border-amber-500/40 hover:text-amber-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                  title="Next page"
                >
                  →
                </button>
                <button
                  onClick={() => setAppointmentPage(totalPages)}
                  disabled={appointmentPage === totalPages}
                  className="px-2 py-1 text-xs font-medium text-gray-300 border border-gray-600 rounded hover:border-amber-500/40 hover:text-amber-400 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                  title="Last page"
                >
                  ⏭
                </button>
              </div>
            )}

            {/* Right: Page info input */}
            <div className="flex items-center gap-2">
              <span className="text-xs text-gray-400">
                Page <span className="font-semibold text-amber-300">{appointmentPage}</span> of <span className="font-semibold text-amber-300">{totalPages || 1}</span>
              </span>
              {totalPages > 1 && (
                <>
                  <span className="text-gray-600">|</span>
                  <input
                    type="number"
                    min="1"
                    max={totalPages}
                    value={appointmentPage}
                    onChange={(e) => {
                      const pageNum = Math.max(1, Math.min(totalPages, Number(e.target.value) || 1));
                      setAppointmentPage(pageNum);
                    }}
                    className="w-12 px-1 py-1 text-xs text-center bg-gray-700 border border-gray-600 rounded text-gray-300 hover:border-amber-500/40 focus:outline-none focus:border-amber-500 transition-all"
                    title="Jump to page"
                  />
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
    );
  };

  // Calendar render function - full width
  // Reports render function
  const renderReports = () => {
    const rs = reportStats || {};
    const totalAppts = rs.totalAppointments || 0;
    const completedAppts = rs.completedAppointments || 0;
    const cancelledAppts = rs.cancelledAppointments || 0;
    const pendingAppts = rs.pendingAppointments || 0;
    const approvedAppts = rs.approvedAppointments || 0;
    const totalUsersCount = rs.totalUsers || 0;
    const totalStaffCount = rs.totalStaff || 0;
    const revenue = rs.revenue || 0;
    const completionRate = rs.completionRate || '0.0';
    const cancellationRate = rs.cancellationRate || '0.0';
    const qualityReport = rs.qualityReport || {};
    const slotUtil = rs.slotUtilization || {};
    const overallUtil = slotUtil.overall || {};
    const serviceStats = qualityReport.service_stats || [];
    const mostPopular = qualityReport.most_popular_services || [];

    return (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-lg font-bold text-amber-50">Reports & Analytics</h2>
          <p className="text-gray-400 text-sm">Live system reports and statistics</p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={loadReportStats}
            className="px-3 py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-sm flex items-center"
          >
            <ArrowPathIcon className="h-3 w-3 mr-1" />
            Refresh
          </button>
          <button 
            onClick={() => setShowReportModal(true)}
            className="px-3 py-1.5 bg-gradient-to-r from-amber-600 to-amber-700 text-white rounded hover:from-amber-700 hover:to-amber-800 transition-all duration-200 font-medium text-sm flex items-center shadow transform hover:-translate-y-0.5 border border-amber-500/30"
          >
            <DocumentArrowDownIcon className="h-3 w-3 mr-1" />
            Generate Report
          </button>
        </div>
      </div>

      {/* Key Metrics Row */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4`}>
          <p className="text-gray-400 text-xs font-medium">Total Appointments</p>
          <p className="text-2xl font-bold text-amber-50 mt-1">{totalAppts}</p>
          <p className="text-xs text-gray-500 mt-1">{completedAppts} completed</p>
        </div>
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4`}>
          <p className="text-gray-400 text-xs font-medium">Total Users</p>
          <p className="text-2xl font-bold text-amber-50 mt-1">{totalUsersCount}</p>
          <p className="text-xs text-gray-500 mt-1">{totalStaffCount} staff members</p>
        </div>
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4`}>
          <p className="text-gray-400 text-xs font-medium">Total Revenue</p>
          <p className="text-2xl font-bold text-green-400 mt-1">₱{Number(revenue).toLocaleString('en-US', { minimumFractionDigits: 2 })}</p>
          <p className="text-xs text-gray-500 mt-1">from {completedAppts} completed</p>
        </div>
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4`}>
          <p className="text-gray-400 text-xs font-medium">Slot Utilization</p>
          <p className="text-2xl font-bold text-blue-400 mt-1">{overallUtil.overall_utilization_rate || 0}%</p>
          <p className="text-xs text-gray-500 mt-1">{overallUtil.total_booked || 0} / {overallUtil.total_capacity || 0} slots</p>
        </div>
      </div>

      {/* Appointment Status Breakdown & Rates */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4`}>
          <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3 flex items-center`}>
            <ChartBarIcon className="h-4 w-4 mr-2" />
            Appointment Breakdown
          </h3>
          <div className="space-y-2">
            <div className={`flex justify-between items-center p-2 ${isDarkMode ? 'bg-gray-800/50' : 'bg-gray-100'} rounded`}>
              <span className={`${isDarkMode ? 'text-gray-300' : 'text-gray-700'} text-xs flex items-center`}>
                <span className="w-2 h-2 rounded-full bg-yellow-400 mr-2"></span>Pending
              </span>
              <div className="flex items-center gap-2">
                <span className="text-amber-400 font-bold text-xs">{pendingAppts}</span>
                <span className="text-gray-500 text-xs">({totalAppts > 0 ? ((pendingAppts / totalAppts) * 100).toFixed(1) : '0.0'}%)</span>
              </div>
            </div>
            <div className={`flex justify-between items-center p-2 ${isDarkMode ? 'bg-gray-800/50' : 'bg-gray-100'} rounded`}>
              <span className={`${isDarkMode ? 'text-gray-300' : 'text-gray-700'} text-xs flex items-center`}>
                <span className="w-2 h-2 rounded-full bg-blue-400 mr-2"></span>Approved
              </span>
              <div className="flex items-center gap-2">
                <span className="text-blue-400 font-bold text-xs">{approvedAppts}</span>
                <span className="text-gray-500 text-xs">({totalAppts > 0 ? ((approvedAppts / totalAppts) * 100).toFixed(1) : '0.0'}%)</span>
              </div>
            </div>
            <div className={`flex justify-between items-center p-2 ${isDarkMode ? 'bg-gray-800/50' : 'bg-gray-100'} rounded`}>
              <span className={`${isDarkMode ? 'text-gray-300' : 'text-gray-700'} text-xs flex items-center`}>
                <span className="w-2 h-2 rounded-full bg-green-400 mr-2"></span>Completed
              </span>
              <div className="flex items-center gap-2">
                <span className="text-green-400 font-bold text-xs">{completedAppts}</span>
                <span className="text-gray-500 text-xs">({completionRate}%)</span>
              </div>
            </div>
            <div className={`flex justify-between items-center p-2 ${isDarkMode ? 'bg-gray-800/50' : 'bg-gray-100'} rounded`}>
              <span className={`${isDarkMode ? 'text-gray-300' : 'text-gray-700'} text-xs flex items-center`}>
                <span className="w-2 h-2 rounded-full bg-red-400 mr-2"></span>Cancelled
              </span>
              <div className="flex items-center gap-2">
                <span className="text-red-400 font-bold text-xs">{cancelledAppts}</span>
                <span className="text-gray-500 text-xs">({cancellationRate}%)</span>
              </div>
            </div>
          </div>
        </div>

        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4`}>
          <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3 flex items-center`}>
            <ChartPieIcon className="h-4 w-4 mr-2" />
            Performance Rates
          </h3>
          <div className="space-y-4">
            {/* Completion Rate Bar */}
            <div>
              <div className="flex justify-between text-xs mb-1">
                <span className="text-gray-400">Completion Rate</span>
                <span className="text-green-400 font-bold">{completionRate}%</span>
              </div>
              <div className="w-full bg-gray-700 rounded-full h-2.5">
                <div className="bg-green-500 h-2.5 rounded-full transition-all duration-500" style={{ width: `${Math.min(100, parseFloat(completionRate))}%` }}></div>
              </div>
            </div>
            {/* Cancellation Rate Bar */}
            <div>
              <div className="flex justify-between text-xs mb-1">
                <span className="text-gray-400">Cancellation Rate</span>
                <span className="text-red-400 font-bold">{cancellationRate}%</span>
              </div>
              <div className="w-full bg-gray-700 rounded-full h-2.5">
                <div className="bg-red-500 h-2.5 rounded-full transition-all duration-500" style={{ width: `${Math.min(100, parseFloat(cancellationRate))}%` }}></div>
              </div>
            </div>
            {/* Utilization Rate Bar */}
            <div>
              <div className="flex justify-between text-xs mb-1">
                <span className="text-gray-400">Slot Utilization</span>
                <span className="text-blue-400 font-bold">{overallUtil.overall_utilization_rate || 0}%</span>
              </div>
              <div className="w-full bg-gray-700 rounded-full h-2.5">
                <div className="bg-blue-500 h-2.5 rounded-full transition-all duration-500" style={{ width: `${Math.min(100, parseFloat(overallUtil.overall_utilization_rate || 0))}%` }}></div>
              </div>
            </div>
            {/* Revenue per Appointment */}
            <div className={`flex justify-between items-center p-2 ${isDarkMode ? 'bg-gray-800/50' : 'bg-gray-100'} rounded mt-2`}>
              <span className="text-gray-400 text-xs">Avg Revenue / Appointment</span>
              <span className="text-amber-400 font-bold text-xs">₱{completedAppts > 0 ? (revenue / completedAppts).toFixed(2) : '0.00'}</span>
            </div>
          </div>
        </div>
      </div>

      {/* Service Performance Table */}
      {serviceStats.length > 0 && (
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4`}>
          <div className="flex justify-between items-center mb-3">
            <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} flex items-center`}>
              <BuildingLibraryIcon className="h-4 w-4 mr-2" />
              Service Performance
            </h3>
            <span className="text-xs text-gray-400">
              Page {servicePerformancePage} of {Math.ceil(serviceStats.length / servicePerformancePageSize)} ({serviceStats.length} total)
            </span>
          </div>
          <div className={`${isDarkMode ? 'bg-gray-800/30' : 'bg-gray-100/30'} rounded overflow-hidden`} style={{maxHeight: '400px', overflowY: 'auto'}}>
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead className="sticky top-0 z-10">
                  <tr className={`${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-200 border-gray-300'} border-b`}>
                    <th className="text-left py-2 px-3 text-amber-50">Service</th>
                    <th className="text-center py-2 px-3 text-amber-50">Total</th>
                    <th className="text-center py-2 px-3 text-amber-50">Completed</th>
                    <th className="text-center py-2 px-3 text-amber-50">Cancelled</th>
                    <th className="text-center py-2 px-3 text-amber-50">Rate</th>
                    <th className="text-right py-2 px-3 text-amber-50">Revenue</th>
                  </tr>
                </thead>
                <tbody>
                  {serviceStats.slice(
                    (servicePerformancePage - 1) * servicePerformancePageSize,
                    servicePerformancePage * servicePerformancePageSize
                  ).map((service, idx) => (
                    <tr key={idx} className="border-b border-gray-800 hover:bg-gray-800/30">
                      <td className="py-2 px-3 text-gray-300 font-medium">{service.service_name}</td>
                      <td className="py-2 px-3 text-gray-400 text-center">{service.total_appointments || 0}</td>
                      <td className="py-2 px-3 text-green-400 text-center">{service.completed || 0}</td>
                      <td className="py-2 px-3 text-red-400 text-center">{service.cancelled || 0}</td>
                      <td className="py-2 px-3 text-center">
                        <span className={`px-1.5 py-0.5 rounded-full text-xs font-medium ${
                          (service.completion_rate || 0) >= 80 ? 'text-green-400 bg-green-500/20' :
                          (service.completion_rate || 0) >= 50 ? 'text-yellow-400 bg-yellow-500/20' :
                          'text-red-400 bg-red-500/20'
                        }`}>
                          {service.completion_rate || 0}%
                        </span>
                      </td>
                      <td className="py-2 px-3 text-amber-400 text-right">₱{Number(service.revenue || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
          
          {/* Pagination Controls */}
          {Math.ceil(serviceStats.length / servicePerformancePageSize) > 1 && (
            <div className="mt-3 flex items-center justify-between">
              <button
                onClick={() => setServicePerformancePage(p => Math.max(1, p - 1))}
                disabled={servicePerformancePage === 1}
                className="px-2.5 py-1 text-xs border border-gray-600 text-gray-300 rounded hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                ← Previous
              </button>
              <div className="flex items-center gap-1">
                {Array.from({ length: Math.ceil(serviceStats.length / servicePerformancePageSize) }, (_, i) => i + 1).map(page => (
                  <button
                    key={page}
                    onClick={() => setServicePerformancePage(page)}
                    className={`px-2 py-1 text-xs rounded transition-colors ${
                      servicePerformancePage === page
                        ? 'bg-amber-600 text-white'
                        : 'border border-gray-600 text-gray-300 hover:bg-gray-700'
                    }`}
                  >
                    {page}
                  </button>
                ))}
              </div>
              <button
                onClick={() => setServicePerformancePage(p => Math.min(Math.ceil(serviceStats.length / servicePerformancePageSize), p + 1))}
                disabled={servicePerformancePage === Math.ceil(serviceStats.length / servicePerformancePageSize)}
                className="px-2.5 py-1 text-xs border border-gray-600 text-gray-300 rounded hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                Next →
              </button>
            </div>
          )}
        </div>
      )}

      {/* Most Popular Services */}
      {mostPopular.length > 0 && (
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-4`}>
          <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3 flex items-center`}>
            <StarIcon className="h-4 w-4 mr-2" />
            Top Services
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            {mostPopular.map((service, idx) => (
              <div key={idx} className={`p-3 ${isDarkMode ? 'bg-gray-800/50' : 'bg-gray-100'} rounded-lg`}>
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-amber-400 font-bold text-sm">#{idx + 1}</span>
                  <span className="text-amber-50 font-medium text-xs">{service.service_name}</span>
                </div>
                <div className="flex justify-between text-xs text-gray-400">
                  <span>{service.total_appointments || 0} appointments</span>
                  <span className="text-green-400">₱{Number(service.revenue || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Last Updated */}
      {rs.lastUpdated && (
        <div className="text-xs text-gray-500 text-right">
          Last updated: {rs.lastUpdated.toLocaleTimeString()}
        </div>
      )}

      <div className="mt-6">
        <DocumentManagement isDarkMode={isDarkMode} />
      </div>
    </div>
    );
  };

  // Messages render function
  const renderMessages = () => (
    <AdminMessages ref={adminMessagesRef} isDarkMode={isDarkMode} onRefresh={() => {}} />
  );

  // Deactivated Accounts render function
  const renderDeactivatedAccounts = () => {
    // Filter deactivated users based on search and role
    const searchFilteredUsers = filteredDeactivatedUsers.filter(user => {
      const matchesSearch = deactSearch === '' || 
        user.first_name?.toLowerCase().includes(deactSearch.toLowerCase()) ||
        user.last_name?.toLowerCase().includes(deactSearch.toLowerCase()) ||
        user.email?.toLowerCase().includes(deactSearch.toLowerCase()) ||
        user.username?.toLowerCase().includes(deactSearch.toLowerCase());
      const matchesRole = deactRoleFilter === 'all' || user.role === deactRoleFilter;
      return matchesSearch && matchesRole;
    });

    // Filter deactivated admins based on search and role
    const searchFilteredAdmins = filteredDeactivatedAdmins.filter(admin => {
      const matchesSearch = deactSearch === '' || 
        admin.first_name?.toLowerCase().includes(deactSearch.toLowerCase()) ||
        admin.last_name?.toLowerCase().includes(deactSearch.toLowerCase()) ||
        admin.email?.toLowerCase().includes(deactSearch.toLowerCase()) ||
        admin.username?.toLowerCase().includes(deactSearch.toLowerCase());
      const matchesRole = deactRoleFilter === 'all' || admin.role === deactRoleFilter;
      return matchesSearch && matchesRole;
    });

    return (
      <div className="space-y-4">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-lg font-bold text-amber-50">Deactivated Accounts</h2>
            <p className="text-gray-400 text-sm">Manage and reactivate deactivated user and admin accounts</p>
          </div>
          <button
            onClick={() => {
              setDataLoaded(prev => ({ ...prev, deactivated: false }));
              loadDeactivatedAccounts();
            }}
            className="px-3 py-1.5 border border-amber-500/30 text-amber-50 rounded hover:bg-amber-500/10 transition-all duration-200 font-medium text-sm flex items-center"
            title="Refresh deactivated accounts"
          >
            <ArrowPathIcon className="h-3 w-3 mr-1" />
            Refresh
          </button>
        </div>

        {/* Filters */}
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow p-3 transition-colors`}>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div className="relative md:col-span-2">
              <MagnifyingGlassIcon className="absolute left-2 top-1/2 -translate-y-1/2 h-3 w-3 text-amber-400" />
              <input
                type="text"
                placeholder="Search by name, email, or username..."
                value={deactSearch}
                onChange={e => setDeactSearch(e.target.value)}
                className="w-full pl-7 pr-3 py-1.5 bg-gray-800 border border-gray-600 text-white rounded-lg placeholder-gray-400 focus:ring-1 focus:ring-amber-500 focus:border-amber-500 text-sm"
              />
            </div>
            <select
              value={deactRoleFilter}
              onChange={e => setDeactRoleFilter(e.target.value)}
              className="px-3 py-1.5 bg-gray-800 border border-gray-600 text-white rounded-lg focus:ring-1 focus:ring-amber-500 text-sm"
            >
              <option value="all">All Roles</option>
              <option value="admin">Admin</option>
              <option value="staff">Staff</option>
              <option value="client">Client</option>
            </select>
            <div className="flex items-center text-xs text-gray-400">
              <FunnelIcon className="h-3 w-3 mr-1" />
              <span>{searchFilteredUsers.length + searchFilteredAdmins.length} deactivated</span>
            </div>
          </div>
        </div>

        {/* Statistics Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <button
            onClick={() => setDeactivatedTab('users')}
            className={`p-4 rounded-lg border-2 transition-all duration-200 text-left ${
              deactivatedTab === 'users'
                ? 'bg-blue-500/20 border-blue-500 shadow-lg shadow-blue-500/20'
                : 'bg-gray-800/50 border-gray-700 hover:border-blue-500/50'
            }`}
          >
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-400 text-xs">Deactivated Users</p>
                <p className="text-2xl font-bold text-blue-400 mt-1">{searchFilteredUsers.length}</p>
              </div>
              <UserGroupIcon className={`h-8 w-8 ${deactivatedTab === 'users' ? 'text-blue-400' : 'text-gray-500'}`} />
            </div>
          </button>

          <button
            onClick={() => setDeactivatedTab('admins')}
            className={`p-4 rounded-lg border-2 transition-all duration-200 text-left ${
              deactivatedTab === 'admins'
                ? 'bg-purple-500/20 border-purple-500 shadow-lg shadow-purple-500/20'
                : 'bg-gray-800/50 border-gray-700 hover:border-purple-500/50'
            }`}
          >
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-400 text-xs">Deactivated Admin Accounts</p>
                <p className="text-2xl font-bold text-purple-400 mt-1">{searchFilteredAdmins.length}</p>
              </div>
              <ShieldCheckIcon className={`h-8 w-8 ${deactivatedTab === 'admins' ? 'text-purple-400' : 'text-gray-500'}`} />
            </div>
          </button>
        </div>

        {/* Empty State */}
        {deactivatedTab === 'users' && searchFilteredUsers.length === 0 && (
          <div className="bg-gray-800/30 border border-gray-700 rounded-lg p-8 text-center">
            <UserGroupIcon className="h-12 w-12 text-gray-500 mx-auto mb-2 opacity-50" />
            <p className="text-gray-400">No deactivated users</p>
          </div>
        )}

        {deactivatedTab === 'admins' && searchFilteredAdmins.length === 0 && (
          <div className="bg-gray-800/30 border border-gray-700 rounded-lg p-8 text-center">
            <ShieldCheckIcon className="h-12 w-12 text-gray-500 mx-auto mb-2 opacity-50" />
            <p className="text-gray-400">No deactivated admin accounts</p>
          </div>
        )}

        {/* Deactivated Users Table */}
        {deactivatedTab === 'users' && searchFilteredUsers.length > 0 && (
          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-x-auto`}>
            <table className="w-full text-xs">
              <thead>
                <tr className="bg-gray-800/50 border-b border-gray-700">
                  <th className="px-4 py-3 text-left font-semibold text-amber-50">Name</th>
                  <th className="px-3 py-3 text-left font-semibold text-amber-50">Email</th>
                  <th className="px-3 py-3 text-left font-semibold text-amber-50">Phone</th>
                  <th className="px-3 py-3 text-left font-semibold text-amber-50">Deactivated Date</th>
                  <th className="px-3 py-3 text-left font-semibold text-amber-50">Actions</th>
                </tr>
              </thead>
              <tbody>

              {searchFilteredUsers.map((user) => (
                <tr key={user.id} className="border-b border-gray-700 hover:bg-gray-800/30 transition-colors duration-200">
                  <td className="px-4 py-3 text-amber-50 font-medium">{user.first_name} {user.last_name}</td>
                  <td className="px-3 py-3 text-gray-300">{user.email}</td>
                  <td className="px-3 py-3 text-gray-400">{user.phone || 'N/A'}</td>
                  <td className="px-3 py-3 text-gray-400">{user.updated_at ? new Date(user.updated_at).toLocaleDateString() : 'N/A'}</td>
                  <td className="px-3 py-3 text-xs font-medium space-x-1">
                    <button
                      onClick={() => handleToggleUserStatus(user)}
                      className="px-2 py-1 bg-green-600 hover:bg-green-700 text-white rounded transition-colors duration-200"
                      title="Reactivate user"
                    >
                      ↻ Reactivate
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Deactivated Admin Accounts Table */}
      {deactivatedTab === 'admins' && filteredDeactivatedAdmins.length > 0 && (
        <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-lg shadow overflow-x-auto`}>
          <table className="w-full text-xs">
            <thead>
              <tr className="bg-gray-800/50 border-b border-gray-700">
                <th className="px-4 py-3 text-left font-semibold text-amber-50">Name</th>
                <th className="px-3 py-3 text-left font-semibold text-amber-50">Email</th>
                <th className="px-3 py-3 text-left font-semibold text-amber-50">Role</th>
                <th className="px-3 py-3 text-left font-semibold text-amber-50">Phone</th>
                <th className="px-3 py-3 text-left font-semibold text-amber-50">Deactivated Date</th>
                <th className="px-3 py-3 text-left font-semibold text-amber-50">Actions</th>
              </tr>
            </thead>
            <tbody>
              {searchFilteredAdmins.map((admin) => (
                <tr key={admin.id} className="border-b border-gray-700 hover:bg-gray-800/30 transition-colors duration-200">
                  <td className="px-4 py-3 text-amber-50 font-medium">{admin.first_name} {admin.last_name}</td>
                  <td className="px-3 py-3 text-gray-300">{admin.email}</td>
                  <td className="px-3 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${
                      admin.role === 'admin' 
                        ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' 
                        : 'bg-blue-500/20 text-blue-300 border border-blue-500/30'
                    }`}>
                      {admin.role}
                    </span>
                  </td>
                  <td className="px-3 py-3 text-gray-400">{admin.phone || 'N/A'}</td>
                  <td className="px-3 py-3 text-gray-400">{admin.updated_at ? new Date(admin.updated_at).toLocaleDateString() : 'N/A'}</td>
                  <td className="px-3 py-3 text-xs font-medium space-x-1">
                    <button
                      onClick={() => handleToggleAdminStatus(admin)}
                      className="px-2 py-1 bg-green-600 hover:bg-green-700 text-white rounded transition-colors duration-200"
                      title="Reactivate admin"
                    >
                      ↻ Reactivate
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Info Banner */}
      <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4">
        <div className="flex gap-3">
          <ExclamationTriangleIcon className="h-5 w-5 text-amber-400 flex-shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-medium text-amber-200 mb-1">Deactivated Accounts</p>
            <ul className="text-xs text-amber-100/80 space-y-1">
              <li>• Deactivated accounts are disabled but retained in the system</li>
              <li>• Users and admins can be reactivated using the Reactivate button</li>
              <li>• Use the Archive tab to permanently delete accounts if needed</li>
              <li>• Reactivated accounts return to their original active status</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    );
  };

  // Account Action Modal (rendered inline with other content)
  const renderAccountActionModal = () => {
    if (!showAccountActionModal || !accountActionUser) return null;

    const actionOptions = [
      { value: 'deactivated', label: 'Deactivate', desc: 'Disable the account temporarily. User cannot log in but data is preserved.', color: 'text-yellow-400', bg: 'bg-yellow-500/10 border-yellow-500/30' },
      { value: 'blocked', label: 'Block', desc: 'Block the account and revoke sessions. User cannot log in.', color: 'text-orange-400', bg: 'bg-orange-500/10 border-orange-500/30' },
      { value: 'deleted', label: 'Delete', desc: 'Archive the account (soft delete). Can be restored from Archive.', color: 'text-red-400', bg: 'bg-red-500/10 border-red-500/30' },
    ];

    const predefinedReasons = {
      deactivated: [
        'Account inactive for extended period',
        'User requested temporary account suspension',
        'Pending identity verification',
        'Violation of terms of service',
        'Suspicious account activity detected',
      ],
      blocked: [
        'Repeated violation of community guidelines',
        'Harassment or abusive behavior',
        'Fraudulent activity detected',
        'Spam or misleading content',
        'Unauthorized access attempts',
        'Impersonation of another user',
      ],
      deleted: [
        'User requested permanent account deletion',
        'Duplicate account',
        'Account created with false information',
        'Severe terms of service violation',
        'Legal compliance requirement',
      ],
    };

    const currentReasons = predefinedReasons[accountActionType] || [];

    return (
      <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" onClick={() => setShowAccountActionModal(false)}>
        <div className="bg-gray-900 border border-amber-500/30 rounded-xl shadow-2xl w-full max-w-lg mx-auto overflow-hidden max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
          {/* Header */}
          <div className="px-6 py-4 border-b border-amber-500/20 bg-gray-900 sticky top-0 z-10">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center">
                <ExclamationTriangleIcon className="w-6 h-6 text-red-400" />
              </div>
              <div>
                <h3 className="text-lg font-semibold text-amber-50">Account Action</h3>
                <p className="text-xs text-gray-400">
                  {accountActionUser.first_name} {accountActionUser.last_name} ({accountActionUser.email})
                </p>
              </div>
            </div>
          </div>

          {/* Body */}
          <div className="px-6 py-5 space-y-4">
            {/* Action Type Selection */}
            <div>
              <label className="block text-sm font-medium text-amber-200 mb-2">Action Type</label>
              <div className="space-y-2">
                {actionOptions.map(opt => (
                  <label
                    key={opt.value}
                    className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-all ${
                      accountActionType === opt.value
                        ? opt.bg + ' ring-1 ring-amber-500/30'
                        : 'border-gray-700 hover:border-gray-600'
                    }`}
                  >
                    <input
                      type="radio"
                      name="accountAction"
                      value={opt.value}
                      checked={accountActionType === opt.value}
                      onChange={e => {
                        setAccountActionType(e.target.value);
                        setAccountActionReason('');
                      }}
                      className="mt-0.5 accent-amber-500"
                    />
                    <div>
                      <span className={`text-sm font-medium ${opt.color}`}>{opt.label}</span>
                      <p className="text-xs text-gray-400 mt-0.5">{opt.desc}</p>
                    </div>
                  </label>
                ))}
              </div>
            </div>

            {/* Predefined Reasons */}
            <div>
              <label className="block text-sm font-medium text-amber-200 mb-2">Quick Reason</label>
              <div className="space-y-1.5">
                {currentReasons.map((reason, idx) => (
                  <button
                    key={idx}
                    type="button"
                    onClick={() => setAccountActionReason(reason)}
                    className={`w-full text-left px-3 py-2 rounded-lg border text-sm transition-all ${
                      accountActionReason === reason
                        ? 'border-amber-500 bg-amber-500/10 text-amber-200 ring-1 ring-amber-500/30'
                        : 'border-gray-700 text-gray-300 hover:border-gray-500 hover:bg-gray-800/50'
                    }`}
                  >
                    {reason}
                  </button>
                ))}
              </div>
            </div>

            {/* Custom Reason */}
            <div>
              <label className="block text-sm font-medium text-amber-200 mb-1.5">
                Reason <span className="text-red-400">*</span>
                <span className="text-xs text-gray-500 font-normal ml-2">Select a quick reason above or type your own</span>
              </label>
              <textarea
                value={accountActionReason}
                onChange={e => setAccountActionReason(e.target.value)}
                placeholder="Provide a detailed reason for this action (min 10 characters). This will be sent to the user via email."
                rows={3}
                maxLength={1000}
                className="w-full px-3 py-2 rounded-lg border border-gray-600 bg-gray-800 text-white text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none placeholder-gray-500 resize-none"
              />
              <p className="text-xs text-gray-500 mt-1">{accountActionReason.length}/1000 characters</p>
            </div>

            {/* Info notice */}
            <div className="p-3 rounded-lg bg-blue-500/10 border border-blue-500/30">
              <p className="text-xs text-blue-300">
                The user will receive an email notification with your reason and a link to submit an appeal.
              </p>
            </div>

            {/* Error */}
            {accountActionError && (
              <div className="p-2.5 rounded-lg bg-red-500/10 border border-red-500/30">
                <p className="text-sm text-red-400">{accountActionError}</p>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="px-6 py-4 border-t border-gray-700 flex gap-3 sticky bottom-0 bg-gray-900">
            <button
              onClick={() => setShowAccountActionModal(false)}
              className="flex-1 px-4 py-2.5 rounded-lg border border-gray-600 text-gray-300 text-sm font-medium hover:bg-gray-800 transition-colors"
            >
              Cancel
            </button>
            <button
              onClick={handleAccountAction}
              disabled={!accountActionReason || accountActionReason.length < 10 || accountActionLoading}
              className={`flex-1 px-4 py-2.5 rounded-lg text-sm font-medium text-white transition-colors ${
                accountActionReason && accountActionReason.length >= 10 && !accountActionLoading
                  ? 'bg-red-600 hover:bg-red-700'
                  : 'bg-red-800 cursor-not-allowed opacity-50'
              }`}
            >
              {accountActionLoading ? (
                <span className="flex items-center justify-center gap-2">
                  <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                  Processing...
                </span>
              ) : (
                `Confirm ${actionOptions.find(o => o.value === accountActionType)?.label || 'Action'}`
              )}
            </button>
          </div>
        </div>
      </div>
    );
  };

  const renderContent = () => {
    switch (activeTab) {
      case 'dashboard': return renderDashboard();
      case 'users': return renderUsers();
      case 'adminProfile': return renderAdminProfile();
      case 'appointments': return renderAppointments();
      case 'system-config': return <AdminSettings isDarkMode={isDarkMode} />;
      case 'services': return <AdminServices isDarkMode={isDarkMode} />;
      case 'analytics': return <AdminAnalyticsDashboard />;
      case 'reports': return renderReports();
      case 'archive': return renderArchive();
      case 'user-status': return <UserStatusManagement isDarkMode={isDarkMode} deactivatedContent={renderDeactivatedAccounts()} onDataChange={() => { clearApiCache(); loadUsers(); loadAdmins(); loadDeactivatedAccounts(); loadArchivedUsers(); }} />;
      case 'messages': return renderMessages();
      case 'action-logs': return <AdminActionLogs isDarkMode={isDarkMode} />;
      case 'feedback': return <AdminFeedback />;
      case 'refunds': return <AdminRefundManagement isUserLight={!isDarkMode} />;
      case 'appeals': return <AdminAppeals isDarkMode={isDarkMode} />;
      case 'announcements': return <AdminAnnouncements isDarkMode={isDarkMode} />;
      case 'settings': return renderSettings();
      default: return renderDashboard();
    }
  };

  return (
      <div className={`min-h-screen ${isDarkMode ? 'bg-gradient-to-br from-gray-900 to-black' : 'bg-gradient-to-br from-gray-100 to-gray-200'} flex flex-col transition-colors duration-300 lg:h-screen`}>
        {/* Mobile Header with Hamburger */}
        <div className="lg:hidden fixed top-0 left-0 right-0 z-40 flex items-center justify-between px-3 sm:px-4 py-3 bg-gray-900 border-b border-amber-500/20 shadow">
          <div className="w-10"></div>
          <span className="text-amber-50 font-bold text-base">Legal Ease</span>
          <div className="flex items-center gap-1">
            <ThemeToggle />
            <button
              onClick={() => setShowMobileSidebar(!showMobileSidebar)}
              className="text-amber-400 hover:text-amber-300 transition-colors p-2 rounded-lg hover:bg-amber-500/10"
              title="Toggle sidebar"
            >
              <Bars3Icon className="h-6 w-6" />
            </button>
          </div>
        </div>

        {/* Mobile Sidebar Overlay */}
        {showMobileSidebar && (
          <div
            className="lg:hidden fixed inset-0 bg-black/50 z-30 transition-opacity duration-200"
            onClick={() => setShowMobileSidebar(false)}
          ></div>
        )}

        {/* Sidebar - Hidden on mobile by default, shown on desktop and when toggled on mobile */}
        <div className={`fixed lg:fixed inset-y-0 right-0 lg:right-auto lg:left-0 z-40 h-screen lg:h-screen ${isDarkMode ? 'bg-gradient-to-b from-gray-900 to-black border-amber-500/20' : 'bg-gradient-to-b from-gray-50 to-gray-100 border-amber-300/40'} border-l lg:border-l-0 lg:border-r shadow-xl flex-shrink-0 transition-all duration-300 lg:translate-x-0 ${
          showMobileSidebar ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'
        } ${isCollapsedDesktop ? 'lg:w-20' : 'w-64'}`}>
          <div className="flex flex-col h-full overflow-hidden">
            <div className={`flex items-center justify-between h-16 shadow-md ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-gray-50 border-amber-300/40'} px-3 border-b transition-colors duration-300 flex-shrink-0`}>
              <div className="flex items-center space-x-2">
                <div className="w-12 h-12 flex items-center justify-center flex-shrink-0">
                  <img 
                    src={isDarkMode ? '/logo-dark-v2.png' : '/logo-light-v2.png'} 
                    alt="Logo" 
                    className="h-full w-full object-contain pointer-events-none drop-shadow-sm transition-opacity duration-300"
                    onError={(e) => {
                      e.target.onerror = null;
                      e.target.src = '/logo.png';
                    }}
                  />
                </div>
                <span className={`text-sm lg:text-base font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300 truncate hidden lg:inline ${isCollapsedDesktop ? 'lg:hidden' : ''}`}>LEGAL EASE</span>
              </div>
              <div className="flex items-center space-x-1">
                <button
                  onClick={() => setIsCollapsedDesktop(!isCollapsedDesktop)}
                  className="hidden lg:flex text-gray-400 hover:text-amber-400 transition-colors p-1 rounded-lg hover:bg-amber-500/10 flex-shrink-0 items-center justify-center"
                  title={isCollapsedDesktop ? 'Expand sidebar' : 'Collapse sidebar'}
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

            <nav className={`flex-1 py-2 lg:py-2.5 space-y-2 lg:space-y-2.5 overflow-y-auto transition-all duration-300 min-h-0 scrollbar-hide ${isCollapsedDesktop ? 'lg:px-2' : 'px-2 lg:px-2.5'}`}>
              {navigation.map((item, index) => {
                if (item.section) {
                  const isDropdownOpen = openDropdowns[item.section];
                  
                  return (
                    <div key={item.section} className="space-y-1">
                      <div className="flex items-center justify-between px-3 py-1">
                        {!isCollapsedDesktop && (
                          <span className="text-xs font-semibold text-amber-400/70 uppercase tracking-wider text-left">
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
                                setActiveTab(subItem.key);
                                setShowMobileSidebar(false);
                              }}
                              className={`w-full flex items-center justify-start px-2 lg:px-2.5 py-1.5 text-xs font-medium rounded transition-all duration-200 border-l-0 group ${
                                activeTab === subItem.key
                                  ? 'text-amber-400 border-l-4 border-amber-500/40 bg-transparent'
                                  : 'text-gray-400 border-transparent hover:text-amber-300'
                              } ${isCollapsedDesktop ? 'lg:justify-center lg:px-2' : ''}`}
                              title={isCollapsedDesktop ? subItem.name : ''}
                            >
                              <div className="flex items-center min-w-0 w-full">
                                <subItem.icon className={`h-4 w-4 flex-shrink-0 transition-colors ${
                                  activeTab === subItem.key ? 'text-amber-400' : 'text-gray-500 group-hover:text-amber-400'
                                } ${!isCollapsedDesktop ? 'mr-2' : ''}`} />
                                {!isCollapsedDesktop && <span className="truncate">{subItem.name}</span>}
                                {subItem.badge && !isCollapsedDesktop && (
                                  <span className="ml-auto flex-shrink-0 bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center animate-pulse">
                                    {subItem.badge}
                                  </span>
                                )}
                                {subItem.badge && isCollapsedDesktop && (
                                  <span className="absolute top-0 right-0 bg-red-500 w-2 h-2 rounded-full"></span>
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
                      setActiveTab(item.key);
                      setShowMobileSidebar(false);
                    }}
                    className={`w-full flex items-center justify-start px-2 lg:px-2.5 py-1.5 text-xs font-medium rounded transition-all duration-200 border-l-0 group ${
                      activeTab === item.key
                        ? 'text-amber-400 border-l-4 border-amber-500/40 bg-transparent'
                        : 'text-gray-400 border-transparent hover:text-amber-300'
                    } ${isCollapsedDesktop ? 'lg:justify-center lg:px-2' : ''}`}
                    title={isCollapsedDesktop ? item.name : ''}
                  >
                    <div className="flex items-center min-w-0">
                      <item.icon className={`h-4 w-4 flex-shrink-0 transition-colors ${
                        activeTab === item.key ? 'text-amber-400' : 'text-gray-500 group-hover:text-amber-400'
                      } ${!isCollapsedDesktop ? 'mr-2' : ''}`} />
                      {!isCollapsedDesktop && <span className="truncate">{item.name}</span>}
                    </div>
                  </button>
                );
              })}
            </nav>

            <div className={`p-2 lg:p-3 border-t border-amber-500/20 flex-shrink-0 transition-all duration-300 ${isCollapsedDesktop ? 'lg:flex lg:items-center lg:justify-center' : ''}`}>
              {/* sidebar profile removed per request (SA / System Administrator / admin) */}
              
              {/* sidebar logout removed per request - header will show confirmation-only logout */}
            </div>
          </div>
        </div>

        {/* Main content */}
        <div className={`flex-1 flex flex-col min-w-0 mt-16 lg:mt-0 lg:h-screen lg:overflow-y-auto scrollbar-hide transition-all duration-300 ${isCollapsedDesktop ? 'lg:ml-20' : 'lg:ml-64'}`}>
          <header className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-gray-50 border-amber-300/40'} border-b shadow-md flex-shrink-0 sticky top-0 z-30 transition-colors duration-300`}>
            <div className="flex justify-between items-center px-3 sm:px-4 lg:px-6 py-2 lg:py-3">
              <div className="flex items-center space-x-2 lg:space-x-3 min-w-0">
                <div>
                  <h1 className={`text-base lg:text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} transition-colors duration-300 truncate`}>
                    {(() => {
                      const findName = (items, key) => {
                        for (const item of items) {
                          if (item.key === key) return item.name;
                          if (item.items) {
                            const found = item.items.find(subItem => subItem.key === key);
                            if (found) return found.name;
                          }
                        }
                        return 'Admin Dashboard';
                      };
                      return findName(navigation, activeTab);
                    })()}
                  </h1>
                  <p className={`${isDarkMode ? 'text-amber-400/70' : 'text-amber-700/70'} mt-0.5 text-xs lg:text-sm capitalize transition-colors duration-300 hidden sm:block`}>
                    Welcome back, {user?.first_name} {user?.last_name}
                  </p>
                </div>
                {activeTab !== 'dashboard' && (
                  <button
                      type="button"
                      onClick={handleRefresh}
                    className={`p-1.5 ml-2 flex-shrink-0 ${isDarkMode ? 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 border-amber-500/30' : 'text-amber-700 hover:text-amber-600 hover:bg-amber-500/20 border-amber-300/30'} rounded border transition-colors duration-200`}
                    title="Refresh data"
                  >
                    <ArrowPathIcon className="h-3 w-3 lg:h-4 lg:w-4" />
                  </button>
                )}
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
                <ThemeToggle />

                {/* Refresh button - always available on dashboard */}
                {activeTab === 'dashboard' && (
                  <button
                    type="button"
                    onClick={() => {
                      setDataLoaded(prev => ({ ...prev, dashboard: false }));
                      loadDashboardData(timeframeRef.current);
                    }}
                    className={`p-1.5 flex-shrink-0 ${isDarkMode ? 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 border-amber-500/30' : 'text-amber-700 hover:text-amber-600 hover:bg-amber-500/20 border-amber-300/30'} rounded border transition-colors duration-200`}
                    title="Refresh dashboard"
                  >
                    <ArrowPathIcon className="h-3 w-3 lg:h-4 lg:w-4" />
                  </button>
                )}

                <div className="ml-2 flex items-center">
                  <label htmlFor="global-timeframe-select" className="sr-only">Timeframe</label>
                  <select
                    id="global-timeframe-select"
                    value={timeframe}
                    onChange={(e) => {
                      const tf = e.target.value;
                      timeframeRef.current = tf;
                      setTimeframe(tf);
                      // Only reset pagination - charts auto-update via useMemo
                      // No need to reload data - stats endpoint uses timeframe param
                      setCurrentPage(1);
                      setAppointmentPage(1);
                    }}
                    aria-label="Select timeframe"
                    className={`px-2 py-1 text-xs rounded bg-gray-800 border border-gray-700 text-gray-300 focus:outline-none focus:ring-1 focus:ring-amber-500 transition-colors`}
                  >
                    <option value="all">All Time</option>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                  </select>

                  {/* Landing Page CMS button — navigates to separate page */}
                  <button
                    type="button"
                    onClick={() => navigate('/admin/cms')}
                    className={`ml-2 px-2 py-1 text-xs rounded border transition-colors flex items-center gap-1 ${isDarkMode ? 'border-gray-600 text-gray-400 hover:bg-gray-700 hover:text-amber-400' : 'border-gray-300 text-gray-500 hover:bg-gray-50 hover:text-blue-500'}`}
                    title="Landing Page CMS"
                  >
                    <GlobeAltIcon className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">CMS</span>
                  </button>
                  {/* Admin Login removed per request (no credential prompt in header) */}
                  <button
                    type="button"
                    onClick={() => setShowLogoutModal(true)}
                    className="ml-2 px-2 py-1 text-xs rounded border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors"
                  >
                    Logout
                  </button>
                </div>
              </div>
            </div>
          </header>

          {error && (
            <div className="mx-2 sm:mx-4 lg:mx-6 mt-4 bg-red-500/10 border border-red-500/30 rounded-lg p-2 sm:p-3 animate-fadeIn">
              <div className="flex justify-between items-center gap-2">
                <div className="flex items-center min-w-0">
                  <XCircleIcon className="h-4 w-4 text-red-400 mr-1.5 flex-shrink-0" />
                  <p className="text-red-300 text-xs line-clamp-2">{error}</p>
                </div>
                <button
                  onClick={clearError}
                  className="text-red-400 hover:text-red-300 transition-colors duration-200 p-0.5 rounded hover:bg-red-500/10 flex-shrink-0"
                >
                  <XMarkIcon className="h-3 w-3" />
                </button>
              </div>
            </div>
          )}

        <main className={`flex-1 p-2 sm:p-3 lg:p-6 scrollbar-hide ${isDarkMode ? '' : 'bg-gray-100'} transition-colors duration-300`}>
          {renderContent()}
        </main>
      </div>

      {/* Modals */}
      <UserFormModal
        isOpen={showUserModal}
        onClose={() => {
          setShowUserModal(false);
          setSelectedUser(null);
        }}
        user={selectedUser}
        onSave={handleSaveUser}
        loading={apiLoading}
      />

      <AdminFormModal
        isOpen={showAdminModal}
        onClose={() => {
          setShowAdminModal(false);
          setSelectedAdmin(null);
        }}
        admin={selectedAdmin}
        onSave={handleSaveAdmin}
        loading={apiLoading}
      />

      {/* AdminLoginModal removed; header no longer shows credential input button */}

      <UnavailableDateModal
        isOpen={showUnavailableModal}
        onClose={() => setShowUnavailableModal(false)}
        onSave={handleAddUnavailableDate}
        loading={apiLoading}
      />

      <AffectedAppointmentsModal
        isOpen={showAffectedModal}
        onClose={() => {
          setShowAffectedModal(false);
          setPendingUnavailableDate(null);
          setAffectedAppointments([]);
        }}
        affected={affectedAppointments}
        dateData={pendingUnavailableDate}
        onConfirm={handleConfirmAddUnavailable}
        onCancelSelected={handleCancelSelectedAppointments}
        onSendMessage={handleSendMessageToAffected}
        loading={apiLoading}
      />

      <CancelBulkAppointmentsModal
        isOpen={showCancelBulkModal}
        onClose={() => {
          setShowCancelBulkModal(false);
          setBulkCancelData(null);
        }}
        affected={bulkCancelData?.affected || []}
        unavailableDate={bulkCancelData?.dateData}
        onConfirm={handleConfirmBulkCancel}
        loading={apiLoading}
      />

      <UserDetailModal
        isOpen={showUserDetailModal}
        onClose={() => {
          setShowUserDetailModal(false);
          setSelectedUser(null);
        }}
        user={selectedUser}
        onDeactivate={handleToggleUserStatus}
        loading={apiLoading}
      />

      <ReportModal
        isOpen={showReportModal}
        onClose={() => setShowReportModal(false)}
        onGenerate={handleGenerateReport}
        loading={apiLoading}
      />

      {/* Decline Modal */}
      <DeclineModal
        isOpen={showDeclineModal}
        onClose={() => {
          setShowDeclineModal(false);
          setAppointmentToDecline(null);
        }}
        appointment={appointmentToDecline}
        onConfirm={async (reason) => {
          setShowDeclineModal(false);
          await handleAppointmentAction(appointmentToDecline.id, 'decline', reason);
          setAppointmentToDecline(null);
        }}
        loading={apiLoading}
      />

      {/* Completion Modal */}
      <CompletionModal
        isOpen={showCompletionModal}
        onClose={() => {
          setShowCompletionModal(false);
          setAppointmentToComplete(null);
        }}
        appointment={appointmentToComplete}
        onConfirm={async (completionNotes) => {
          await handleAppointmentCompletion(completionNotes);
        }}
        loading={apiLoading}
      />

      {/* Message Modal */}
      <MessageModal
        isOpen={showMessageModal}
        onClose={() => {
          setShowMessageModal(false);
          setSelectedUser(null);
        }}
        user={selectedUser}
        onSend={handleSendMessage}
        loading={apiLoading}
      />

      {/* Logout Confirmation Modal */}
      <LogoutConfirmationModal
        isOpen={showLogoutModal}
        onClose={() => setShowLogoutModal(false)}
        onConfirm={logout}
        loading={apiLoading}
        isDarkMode={isDarkMode}
      />

      <ConfirmationModal
        isOpen={showDeleteModal}
        onClose={() => {
          setShowDeleteModal(false);
          setItemToDelete(null);
        }}
        onConfirm={activeTab === 'adminProfile' ? handleDeleteAdmin : handleDeleteUser}
        title={`Delete ${activeTab === 'adminProfile' ? 'Admin' : 'User'}`}
        message={`Are you sure you want to delete ${itemToDelete?.first_name} ${itemToDelete?.last_name}? This action cannot be undone.`}
        confirmText={`Delete ${activeTab === 'adminProfile' ? 'Admin' : 'User'}`}
        type="danger"
        loading={apiLoading}
        isDarkMode={isDarkMode}
      />

      {/* Account Action Modal (delete/block/deactivate with reason) */}
      {renderAccountActionModal()}
    </div>
  );
};

export default AdminDashboard;