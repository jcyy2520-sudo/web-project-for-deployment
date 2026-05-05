import React, { useEffect, useState, useCallback } from 'react';
import axios from 'axios';
import {
  ChartBarIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon,
  CheckCircleIcon,
  ClockIcon,
  UserGroupIcon,
  LightBulbIcon,
  ShieldExclamationIcon,
  ShieldCheckIcon,
  ArrowTrendingUpIcon,
  InformationCircleIcon,
  SparklesIcon,
  AcademicCapIcon,
  BeakerIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  HandThumbUpIcon,
  HandThumbDownIcon,
  ClipboardDocumentCheckIcon,
  MagnifyingGlassIcon,
  XCircleIcon,
} from '@heroicons/react/24/outline';

// ─── Helpers ──────────────────────────────────────────────────────────────────

const todayStr = () => new Date().toISOString().split('T')[0];

const formatTime12h = (time24) => {
  if (!time24) return '';
  const [h, m] = time24.split(':').map(Number);
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hour12 = h % 12 || 12;
  return `${hour12}:${String(m).padStart(2, '0')} ${ampm}`;
};

const pct = (n, d) => (d > 0 ? Math.round((n / d) * 100) : 0);

// ─── Tab Definitions ──────────────────────────────────────────────────────────

const TAB_DEFS = [
  { id: 'data-quality', label: 'Data Quality', icon: BeakerIcon },
  { id: 'timeslots',    label: 'Time Slots',   icon: ClockIcon },
  { id: 'risk',         label: 'Risk',         icon: ShieldExclamationIcon },
  { id: 'workload',     label: 'Workload',     icon: ChartBarIcon },
];

// ═══════════════════════════════════════════════════════════════════════════════
// MAIN COMPONENT
// ═══════════════════════════════════════════════════════════════════════════════

const AdminDecisionSupport = ({ isDarkMode = true }) => {
  const [activeTab, setActiveTab] = useState('data-quality');
  const [mlStatus, setMlStatus] = useState(null);
  const [lastRefresh, setLastRefresh] = useState(null);

  // Fetch ML status on mount for the AI badge
  useEffect(() => {
    const fetchMlStatus = async () => {
      try {
        const res = await axios.get('/api/decision-support/data-quality');
        const data = res.data?.data || res.data || {};
        const qualityData = data.data || {};
        const modelStatus = data.model_status || data.model || data.model_info || null;
        setMlStatus({
          available: data.status !== 'service_unavailable',
          hasModel: Boolean(modelStatus?.has_model ?? modelStatus),
          records: qualityData.total_records ?? qualityData.record_count ?? data.total_records ?? data.record_count ?? 0,
        });
        setLastRefresh(new Date());
      } catch {
        setMlStatus({ available: false, hasModel: false, records: 0 });
      }
    };
    fetchMlStatus();
  }, []);

  // ─── Common style helpers ─────────────────────────────────────────────

  const cls = {
    card: `rounded-xl border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'}`,
    cardInner: `rounded-lg border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'}`,
    cardSubtle: `rounded-lg ${isDarkMode ? 'bg-gray-700/40' : 'bg-gray-50'}`,
    textPrimary: isDarkMode ? 'text-gray-100' : 'text-gray-900',
    textSecondary: isDarkMode ? 'text-gray-400' : 'text-gray-500',
    textMuted: isDarkMode ? 'text-gray-500' : 'text-gray-400',
    border: isDarkMode ? 'border-gray-700' : 'border-gray-200',
    inputCls: `w-full px-3 py-2 rounded-lg text-sm border ${
      isDarkMode ? 'bg-gray-700 border-gray-600 text-white placeholder-gray-400' : 'bg-white border-gray-300 text-gray-900 placeholder-gray-500'
    }`,
    btnPrimary: `px-4 py-2 rounded-lg text-sm font-semibold transition-colors ${
      isDarkMode ? 'bg-amber-500 hover:bg-amber-400 text-gray-900' : 'bg-amber-500 hover:bg-amber-600 text-white'
    }`,
    btnSecondary: `px-3 py-1.5 rounded-lg text-xs font-medium transition-colors border ${
      isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50'
    }`,
    btnDanger: `px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
      isDarkMode ? 'bg-red-500/20 text-red-300 hover:bg-red-500/30' : 'bg-red-50 text-red-700 hover:bg-red-100'
    }`,
    btnSuccess: `px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
      isDarkMode ? 'bg-green-500/20 text-green-300 hover:bg-green-500/30' : 'bg-green-50 text-green-700 hover:bg-green-100'
    }`,
  };

  return (
    <div className={`rounded-xl border shadow-sm ${isDarkMode ? 'bg-gray-900 border-gray-700' : 'bg-white border-gray-200'}`}>
      {/* Header */}
      <div className={`px-5 py-4 border-b flex items-center justify-between ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
        <div className="flex items-center gap-3">
          <div className={`p-2 rounded-lg ${isDarkMode ? 'bg-amber-500/10' : 'bg-amber-50'}`}>
            <SparklesIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
          </div>
          <div>
            <h3 className={`text-sm font-bold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
              Decision Support Center
            </h3>
            <p className={`text-xs ${cls.textSecondary}`}>
              ML-powered scheduling intelligence
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {mlStatus && (
            <span className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${
              mlStatus.hasModel
                ? isDarkMode ? 'bg-green-500/15 text-green-300' : 'bg-green-50 text-green-700'
                : mlStatus.available
                ? isDarkMode ? 'bg-amber-500/15 text-amber-300' : 'bg-amber-50 text-amber-700'
                : isDarkMode ? 'bg-red-500/15 text-red-300' : 'bg-red-50 text-red-700'
            }`}>
              <span className={`w-1.5 h-1.5 rounded-full ${
                mlStatus.hasModel ? 'bg-green-400' : mlStatus.available ? 'bg-amber-400' : 'bg-red-400'
              }`} />
              {mlStatus.hasModel ? 'AI Active' : mlStatus.available ? 'Model Not Trained' : 'ML Offline'}
            </span>
          )}
          {lastRefresh && (
            <span className={`text-xs ${cls.textMuted}`}>
              Updated {lastRefresh.toLocaleTimeString()}
            </span>
          )}
        </div>
      </div>

      {/* Tab Navigation */}
      <div className={`px-4 border-b overflow-x-auto ${isDarkMode ? 'border-gray-700' : 'border-gray-200'}`}>
        <div className="flex gap-0.5 min-w-max">
          {TAB_DEFS.map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`flex items-center gap-1.5 px-3 py-2.5 text-xs font-medium border-b-2 transition-colors whitespace-nowrap ${
                activeTab === tab.id
                  ? isDarkMode
                    ? 'text-amber-400 border-amber-400 bg-gray-800'
                    : 'text-amber-600 border-amber-500 bg-amber-50'
                  : isDarkMode
                  ? 'text-gray-400 border-transparent hover:text-gray-200 hover:bg-gray-800'
                  : 'text-gray-500 border-transparent hover:text-gray-700 hover:bg-gray-50'
              }`}
            >
              <tab.icon className="h-4 w-4" />
              {tab.label}
            </button>
          ))}
        </div>
      </div>

      {/* Tab Content */}
      <div className="p-5 max-h-[65vh] overflow-y-auto">
        {activeTab === 'data-quality' && <DataQualityTab isDarkMode={isDarkMode} cls={cls} onRefresh={() => setLastRefresh(new Date())} />}
        {activeTab === 'timeslots' && <TimeSlotsTab isDarkMode={isDarkMode} cls={cls} onRefresh={() => setLastRefresh(new Date())} />}
        {activeTab === 'risk' && <RiskTab isDarkMode={isDarkMode} cls={cls} onRefresh={() => setLastRefresh(new Date())} />}
        {activeTab === 'workload' && <WorkloadTab isDarkMode={isDarkMode} cls={cls} onRefresh={() => setLastRefresh(new Date())} />}
      </div>
    </div>
  );
};

// ═══════════════════════════════════════════════════════════════════════════════
// TAB 1 - DATA QUALITY & TRAINING
// ═══════════════════════════════════════════════════════════════════════════════

const DataQualityTab = ({ isDarkMode, cls, onRefresh }) => {
  const [quality, setQuality] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [training, setTraining] = useState(false);
  const [trainResult, setTrainResult] = useState(null);

  const fetchQuality = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await axios.get('/api/decision-support/data-quality');
      setQuality(res.data?.data || res.data || null);
    } catch (err) {
      console.error('Data quality fetch failed', err);
      setError('Failed to load data quality information.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchQuality();
  }, [fetchQuality]);

  const handleTrain = async () => {
    setTraining(true);
    setTrainResult(null);
    try {
      const res = await axios.post('/api/decision-support/train');
      setTrainResult(res.data?.data || res.data || { status: 'completed' });
      fetchQuality();
    } catch (err) {
      console.error('Training failed', err);
      setTrainResult({ status: 'error', message: err.response?.data?.message || 'Training failed. Please try again.' });
    } finally {
      setTraining(false);
    }
  };

  if (loading && !quality) {
    return <LoadingState isDarkMode={isDarkMode} message="Checking data quality..." />;
  }

  if (error && !quality) {
    return <ErrorState isDarkMode={isDarkMode} message={error} onRetry={fetchQuality} />;
  }

  // Handle service_unavailable status
  if (quality?.status === 'service_unavailable') {
    return (
      <div className="space-y-4">
        <div className={`p-4 rounded-lg border ${isDarkMode ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50 border-amber-200'}`}>
          <div className="flex items-start gap-3">
            <ExclamationTriangleIcon className={`h-5 w-5 mt-0.5 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <div>
              <p className={`text-sm font-semibold ${isDarkMode ? 'text-amber-300' : 'text-amber-800'}`}>ML Service Unavailable</p>
              <p className={`text-xs mt-1 ${isDarkMode ? 'text-amber-200/70' : 'text-amber-700'}`}>
                {quality.message || 'The ML service is not currently running. Start it to enable intelligent decision support.'}
              </p>
            </div>
          </div>
        </div>
      </div>
    );
  }

  const data = quality?.data || quality || {};
  const recordCount = data.total_records ?? data.record_count ?? 0;
  const threshold = data.min_required ?? 500;
  const readiness = pct(recordCount, threshold);
  const isBelowThreshold = recordCount < threshold;

  const completed = data.class_distribution?.completed ?? data.completed ?? 0;
  const cancelled = data.class_distribution?.cancelled ?? data.cancelled ?? 0;
  const noShow = data.class_distribution?.no_show ?? data.no_show ?? 0;
  const totalOutcomes = completed + cancelled + noShow;

  const features = data.features ?? data.feature_completeness ?? [];
  const modelInfo = data.model ?? data.model_info ?? null;

  return (
    <div className="space-y-5">
      {/* Low Data Warning */}
      {isBelowThreshold && (
        <div className={`p-4 rounded-lg border ${isDarkMode ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50 border-amber-200'}`}>
          <div className="flex items-start gap-3">
            <ExclamationTriangleIcon className={`h-5 w-5 mt-0.5 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <div>
              <p className={`text-sm font-semibold ${isDarkMode ? 'text-amber-300' : 'text-amber-800'}`}>
                Insufficient Training Data
              </p>
              <p className={`text-xs mt-1 ${isDarkMode ? 'text-amber-200/70' : 'text-amber-700'}`}>
                The ML model requires at least {threshold} completed appointment records to train accurately.
                You currently have {recordCount} records. Continue operating normally and data will accumulate over time.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Record Count Progress */}
      <div className={cls.cardInner + ' p-4'}>
        <div className="flex items-center justify-between mb-2">
          <h4 className={`text-xs font-semibold uppercase tracking-wide ${cls.textSecondary}`}>Training Data Records</h4>
          <span className={`text-sm font-bold ${
            readiness >= 100 ? (isDarkMode ? 'text-green-400' : 'text-green-600')
              : readiness >= 60 ? (isDarkMode ? 'text-amber-400' : 'text-amber-600')
              : (isDarkMode ? 'text-red-400' : 'text-red-600')
          }`}>
            {recordCount} / {threshold}
          </span>
        </div>
        <div className={`w-full h-3 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
          <div
            className={`h-full rounded-full transition-all duration-500 ${
              readiness >= 100 ? 'bg-green-500' : readiness >= 60 ? 'bg-amber-500' : 'bg-red-500'
            }`}
            style={{ width: `${Math.min(readiness, 100)}%` }}
          />
        </div>
        <p className={`text-xs mt-1.5 ${cls.textMuted}`}>
          {readiness >= 100
            ? 'Data threshold met. You can train or retrain the model.'
            : `${threshold - recordCount} more records needed to reach the minimum threshold.`}
        </p>
      </div>

      {/* Class Balance */}
      {totalOutcomes > 0 && (
        <div className={cls.cardInner + ' p-4'}>
          <h4 className={`text-xs font-semibold uppercase tracking-wide mb-3 ${cls.textSecondary}`}>Class Distribution</h4>
          <div className="space-y-2">
            <ClassBar label="Completed" count={completed} total={totalOutcomes} color="bg-green-500" isDarkMode={isDarkMode} />
            <ClassBar label="Cancelled" count={cancelled} total={totalOutcomes} color="bg-red-500" isDarkMode={isDarkMode} />
            <ClassBar label="No-Show" count={noShow} total={totalOutcomes} color="bg-amber-500" isDarkMode={isDarkMode} />
          </div>
        </div>
      )}

      {/* Feature Completeness */}
      {Array.isArray(features) && features.length > 0 && (
        <div className={cls.cardInner + ' p-4'}>
          <h4 className={`text-xs font-semibold uppercase tracking-wide mb-3 ${cls.textSecondary}`}>Feature Completeness</h4>
          <div className="grid grid-cols-2 gap-2">
            {features.map((feat, idx) => {
              const name = typeof feat === 'string' ? feat : feat.name || feat.feature || '';
              const ok = typeof feat === 'object' ? (feat.available ?? feat.complete ?? true) : true;
              const completeness = typeof feat === 'object' ? (feat.completeness ?? null) : null;

              return (
                <div key={idx} className={`flex items-center gap-2 p-2 rounded-lg ${isDarkMode ? 'bg-gray-700/30' : 'bg-white/60'}`}>
                  {ok ? (
                    <CheckCircleIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
                  ) : (
                    <XCircleIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-red-400' : 'text-red-600'}`} />
                  )}
                  <span className={`text-xs ${cls.textPrimary}`}>{name}</span>
                  {completeness !== null && (
                    <span className={`text-xs ml-auto ${cls.textMuted}`}>{completeness}%</span>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Model Status */}
      {modelInfo && (
        <div className={cls.cardInner + ' p-4'}>
          <h4 className={`text-xs font-semibold uppercase tracking-wide mb-3 ${cls.textSecondary}`}>Current Model</h4>
          <div className="grid grid-cols-3 gap-3">
            <div className={`p-2.5 rounded-lg text-center ${isDarkMode ? 'bg-gray-700/50' : 'bg-gray-50'}`}>
              <p className={`text-xs ${cls.textSecondary}`}>Algorithm</p>
              <p className={`text-sm font-bold mt-0.5 ${cls.textPrimary}`}>{modelInfo.algorithm || 'N/A'}</p>
            </div>
            <div className={`p-2.5 rounded-lg text-center ${isDarkMode ? 'bg-gray-700/50' : 'bg-gray-50'}`}>
              <p className={`text-xs ${cls.textSecondary}`}>ROC-AUC</p>
              <p className={`text-sm font-bold mt-0.5 ${
                (modelInfo.roc_auc ?? 0) >= 0.8 ? (isDarkMode ? 'text-green-400' : 'text-green-600')
                  : (modelInfo.roc_auc ?? 0) >= 0.6 ? (isDarkMode ? 'text-amber-400' : 'text-amber-600')
                  : (isDarkMode ? 'text-red-400' : 'text-red-600')
              }`}>
                {modelInfo.roc_auc != null ? Number(modelInfo.roc_auc).toFixed(3) : 'N/A'}
              </p>
            </div>
            <div className={`p-2.5 rounded-lg text-center ${isDarkMode ? 'bg-gray-700/50' : 'bg-gray-50'}`}>
              <p className={`text-xs ${cls.textSecondary}`}>Trained</p>
              <p className={`text-xs font-medium mt-1 ${cls.textPrimary}`}>
                {modelInfo.trained_at ? new Date(modelInfo.trained_at).toLocaleDateString() : 'Never'}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Train Button */}
      <div className="flex items-center gap-3">
        <button
          onClick={handleTrain}
          disabled={training || isBelowThreshold}
          className={`${cls.btnPrimary} flex items-center gap-2 ${(training || isBelowThreshold) ? 'opacity-50 cursor-not-allowed' : ''}`}
        >
          {training ? (
            <>
              <ArrowPathIcon className="h-4 w-4 animate-spin" />
              Training...
            </>
          ) : (
            <>
              <AcademicCapIcon className="h-4 w-4" />
              Train Model
            </>
          )}
        </button>
        <button onClick={fetchQuality} disabled={loading} className={cls.btnSecondary}>
          <ArrowPathIcon className={`h-3.5 w-3.5 inline mr-1 ${loading ? 'animate-spin' : ''}`} />
          Refresh
        </button>
      </div>

      {/* Training Result */}
      {trainResult && (
        <div className={`p-4 rounded-lg border ${
          trainResult.status === 'error'
            ? isDarkMode ? 'bg-red-500/10 border-red-500/30' : 'bg-red-50 border-red-200'
            : isDarkMode ? 'bg-green-500/10 border-green-500/30' : 'bg-green-50 border-green-200'
        }`}>
          <div className="flex items-start gap-2">
            {trainResult.status === 'error' ? (
              <XCircleIcon className={`h-5 w-5 mt-0.5 flex-shrink-0 ${isDarkMode ? 'text-red-400' : 'text-red-600'}`} />
            ) : (
              <CheckCircleIcon className={`h-5 w-5 mt-0.5 flex-shrink-0 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
            )}
            <div className="flex-1 min-w-0">
              <p className={`text-sm font-semibold ${
                trainResult.status === 'error'
                  ? isDarkMode ? 'text-red-300' : 'text-red-800'
                  : isDarkMode ? 'text-green-300' : 'text-green-800'
              }`}>
                {trainResult.status === 'error' ? 'Training Failed' : 'Training Completed'}
              </p>
              {trainResult.message && (
                <p className={`text-xs mt-0.5 ${cls.textSecondary}`}>{trainResult.message}</p>
              )}
              {trainResult.metrics && (
                <div className="grid grid-cols-3 gap-2 mt-2">
                  {Object.entries(trainResult.metrics).map(([k, v]) => (
                    <div key={k} className={`p-1.5 rounded text-center ${isDarkMode ? 'bg-gray-700/40' : 'bg-white/80'}`}>
                      <p className={`text-xs ${cls.textSecondary}`}>{k.replace(/_/g, ' ')}</p>
                      <p className={`text-sm font-bold ${cls.textPrimary}`}>
                        {typeof v === 'number' ? (v < 1 ? v.toFixed(3) : v) : v}
                      </p>
                    </div>
                  ))}
                </div>
              )}
              {trainResult.algorithm && (
                <p className={`text-xs mt-2 ${cls.textMuted}`}>
                  Algorithm selected: <span className={`font-semibold ${cls.textPrimary}`}>{trainResult.algorithm}</span>
                </p>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// ═══════════════════════════════════════════════════════════════════════════════
// TAB 2 - TIME SLOT SUGGESTIONS
// ═══════════════════════════════════════════════════════════════════════════════

const TimeSlotsTab = ({ isDarkMode, cls, onRefresh }) => {
  const [date, setDate] = useState(todayStr());
  const [slots, setSlots] = useState(null);
  const [summary, setSummary] = useState(null);
  const [responseStatus, setResponseStatus] = useState(null);
  const [responseMessage, setResponseMessage] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [selectedSlot, setSelectedSlot] = useState(null);
  const [rejectedSlots, setRejectedSlots] = useState([]);

  const fetchSlots = useCallback(async (rejected = []) => {
    if (!date) return;
    setLoading(true);
    setError(null);
    try {
      const res = await axios.get('/api/decision-support/time-slot-recommendations', {
        params: { appointment_date: date },
      });
      setResponseStatus(res.data?.status || 'ok');
      setResponseMessage(res.data?.message || null);

      const data = res.data?.data;
      let slotList = [];
      if (Array.isArray(data)) {
        slotList = data;
      } else if (data?.slots) {
        slotList = data.slots;
      }

      // Filter out rejected slots
      if (rejected.length > 0) {
        slotList = slotList.filter(s => !rejected.includes(s.time));
      }

      setSlots(slotList);
      setSummary(res.data?.summary || data?.summary || null);
    } catch (err) {
      console.error('Time slot recommendations failed', err);
      setError('Failed to fetch time slot recommendations.');
    } finally {
      setLoading(false);
    }
  }, [date]);

  const handleFetch = () => {
    setRejectedSlots([]);
    setSelectedSlot(null);
    fetchSlots([]);
  };

  // Auto-fetch on mount and auto-refresh every 120s
  useEffect(() => {
    fetchSlots([]);
    const interval = setInterval(() => fetchSlots([]), 120000);
    return () => clearInterval(interval);
  }, [fetchSlots]);

  // Notify parent of refresh
  useEffect(() => {
    if (slots && onRefresh) onRefresh();
  }, [slots]);

  const handleAcceptSlot = (slot) => {
    setSelectedSlot(slot.time);
  };

  const handleRejectSlot = (slotTime) => {
    const newRejected = [...rejectedSlots, slotTime];
    setRejectedSlots(newRejected);
    fetchSlots(newRejected);
  };

  const getTagConfig = (tag) => {
    const tags = {
      'Best Time': { cls: isDarkMode ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : 'bg-amber-100 text-amber-700 border-amber-300' },
      'Recommended': { cls: isDarkMode ? 'bg-green-500/20 text-green-300 border-green-500/40' : 'bg-green-100 text-green-700 border-green-300' },
      'Filling Up': { cls: isDarkMode ? 'bg-red-500/20 text-red-300 border-red-500/40' : 'bg-red-100 text-red-700 border-red-300' },
      'Full': { cls: isDarkMode ? 'bg-red-600/20 text-red-400 border-red-500/40' : 'bg-red-100 text-red-600 border-red-300' },
    };
    return tags[tag] || { cls: isDarkMode ? 'bg-gray-700 text-gray-300 border-gray-600' : 'bg-gray-100 text-gray-600 border-gray-200' };
  };

  const getDemandBadge = (level) => {
    const cfgs = {
      low: { label: 'Low Demand', cls: isDarkMode ? 'bg-green-500/20 text-green-300' : 'bg-green-100 text-green-700' },
      moderate: { label: 'Moderate', cls: isDarkMode ? 'bg-blue-500/20 text-blue-300' : 'bg-blue-100 text-blue-700' },
      high: { label: 'High Demand', cls: isDarkMode ? 'bg-amber-500/20 text-amber-300' : 'bg-amber-100 text-amber-700' },
      very_high: { label: 'Very Busy', cls: isDarkMode ? 'bg-red-500/20 text-red-300' : 'bg-red-100 text-red-700' },
      full: { label: 'Full', cls: isDarkMode ? 'bg-red-500/20 text-red-400' : 'bg-red-100 text-red-600' },
    };
    const cfg = cfgs[level] || cfgs.low;
    return <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${cfg.cls}`}>{cfg.label}</span>;
  };

  const getSlotScoreColor = (score) => {
    if (score >= 35) return 'bg-green-500';
    if (score >= 20) return 'bg-amber-500';
    return 'bg-gray-400';
  };

  if (responseStatus === 'no_model' && !loading) {
    return (
      <div className="space-y-4">
        <NoModelBanner isDarkMode={isDarkMode} message={responseMessage} />
        <DatePickerRow cls={cls} isDarkMode={isDarkMode} date={date} setDate={setDate} onFetch={handleFetch} loading={loading} />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <DatePickerRow cls={cls} isDarkMode={isDarkMode} date={date} setDate={setDate} onFetch={handleFetch} loading={loading} />

      {loading && <LoadingState isDarkMode={isDarkMode} message="Finding best time slots..." />}
      {error && <ErrorState isDarkMode={isDarkMode} message={error} onRetry={handleFetch} />}

      {/* Summary */}
      {summary && !loading && (
        <div className={`p-3 rounded-lg text-xs ${isDarkMode ? 'bg-gray-800 text-gray-300' : 'bg-gray-50 text-gray-600'}`}>
          <span className="font-medium">{summary.available_slots}</span> of {summary.total_slots} slots available
          {summary.busiest_period && (
            <> {' | '} Busiest: <span className="font-medium">{summary.busiest_period}</span></>
          )}
          {summary.quietest_period && (
            <> {' | '} Quietest: <span className="font-medium">{summary.quietest_period}</span></>
          )}
        </div>
      )}

      {/* Slot Grid */}
      {slots && !loading && slots.length > 0 && (
        <div className="grid grid-cols-2 gap-2.5">
          {slots.map((slot, index) => {
            const isSelected = selectedSlot === slot.time;
            const displayTime = slot.display || formatTime12h(slot.time);
            const tagCfg = slot.tag ? getTagConfig(slot.tag) : null;

            return (
              <div
                key={slot.time || index}
                className={`p-3 rounded-lg border-2 transition-all ${
                  isSelected
                    ? isDarkMode ? 'bg-amber-500/15 border-amber-500 ring-1 ring-amber-500/30' : 'bg-amber-50 border-amber-500 ring-1 ring-amber-200'
                    : !slot.available
                    ? isDarkMode ? 'bg-gray-800/50 border-gray-700 opacity-40' : 'bg-gray-100 border-gray-200 opacity-40'
                    : isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-white border-gray-200'
                }`}
              >
                {/* Time & Tag */}
                <div className="flex items-center justify-between mb-1.5">
                  <span className={`text-sm font-bold ${cls.textPrimary}`}>{displayTime}</span>
                  {slot.tag && (
                    <span className={`text-xs font-bold px-2 py-0.5 rounded-full border ${tagCfg.cls}`}>{slot.tag}</span>
                  )}
                </div>

                {/* Staff & Demand */}
                <div className="flex items-center justify-between mb-2">
                  {slot.available ? (
                    <span className={`flex items-center gap-1 text-xs ${cls.textSecondary}`}>
                      <UserGroupIcon className="h-3.5 w-3.5" />
                      {slot.available_staff} staff
                    </span>
                  ) : (
                    <span className={`flex items-center gap-1 text-xs ${isDarkMode ? 'text-red-400' : 'text-red-500'}`}>
                      <ExclamationTriangleIcon className="h-3.5 w-3.5" />
                      Fully booked
                    </span>
                  )}
                  {slot.available && slot.demand_level && getDemandBadge(slot.demand_level)}
                </div>

                {/* Score Bar */}
                {slot.available && slot.score !== undefined && (
                  <div className={`w-full h-1.5 rounded-full overflow-hidden mb-2 ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
                    <div
                      className={`h-full rounded-full transition-all ${getSlotScoreColor(slot.score)}`}
                      style={{ width: `${Math.min((slot.score / (slot.max_score || 50)) * 100, 100)}%` }}
                    />
                  </div>
                )}

                {/* Historical completion rate */}
                {slot.completion_rate != null && (
                  <p className={`text-xs mb-2 ${cls.textMuted}`}>
                    Historical completion: {slot.completion_rate}%
                  </p>
                )}

                {/* Reasoning */}
                {slot.reasoning && slot.reasoning.length > 0 && slot.available && (
                  <div className="space-y-0.5 mb-2">
                    {slot.reasoning.slice(0, 2).map((r, i) => (
                      <p key={i} className={`text-xs ${cls.textSecondary}`}>
                        - {r}
                      </p>
                    ))}
                  </div>
                )}

                {/* Action buttons */}
                {slot.available && (
                  <div className="flex items-center gap-1.5 pt-1">
                    <button onClick={() => handleAcceptSlot(slot)} className={`${cls.btnSuccess} text-xs px-2 py-1`}>
                      Accept
                    </button>
                    <button onClick={() => handleRejectSlot(slot.time)} className={`${cls.btnDanger} text-xs px-2 py-1`}>
                      Reject
                    </button>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {slots && !loading && slots.length === 0 && (
        <EmptyState isDarkMode={isDarkMode} message="No time slot recommendations available for this date." />
      )}
    </div>
  );
};

// ═══════════════════════════════════════════════════════════════════════════════
// TAB 4 - APPOINTMENT RISK
// ═══════════════════════════════════════════════════════════════════════════════

const RiskTab = ({ isDarkMode, cls, onRefresh }) => {
  const [appointmentId, setAppointmentId] = useState('');
  const [todayAppointments, setTodayAppointments] = useState([]);
  const [assessment, setAssessment] = useState(null);
  const [responseStatus, setResponseStatus] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [showAllFactors, setShowAllFactors] = useState(false);
  const [overrideReason, setOverrideReason] = useState('');
  const [showOverrideInput, setShowOverrideInput] = useState(false);
  const [riskOverview, setRiskOverview] = useState([]);
  const [loadingOverview, setLoadingOverview] = useState(false);

  // Fetch today's appointments and auto-assess risk for each
  useEffect(() => {
    const fetchTodayWithRisk = async () => {
      setLoadingOverview(true);
      try {
        const res = await axios.get('/api/appointments', {
          params: { date: todayStr(), per_page: 50 },
        });
        const data = res.data?.data || res.data?.appointments || [];
        const appts = Array.isArray(data) ? data : [];
        setTodayAppointments(appts);

        // Assess risk for up to 10 appointments in parallel
        const toAssess = appts.filter(a => a.status === 'pending' || a.status === 'approved').slice(0, 10);
        if (toAssess.length > 0) {
          const riskResults = await Promise.allSettled(
            toAssess.map(a =>
              axios.get(`/api/decision-support/appointment-risk/${a.id}`).then(r => ({
                ...a,
                risk: r.data?.data || null,
              }))
            )
          );
          const assessed = riskResults
            .filter(r => r.status === 'fulfilled' && r.value.risk?.risk_score != null)
            .map(r => r.value)
            .sort((a, b) => (b.risk?.risk_score || 0) - (a.risk?.risk_score || 0));
          setRiskOverview(assessed);
          if (onRefresh) onRefresh();
        }
      } catch (_) {
        // Non-critical
      } finally {
        setLoadingOverview(false);
      }
    };
    fetchTodayWithRisk();
    const interval = setInterval(fetchTodayWithRisk, 120000);
    return () => clearInterval(interval);
  }, []);

  const fetchRisk = useCallback(async (id) => {
    const targetId = id || appointmentId;
    if (!targetId) return;
    setLoading(true);
    setError(null);
    setAssessment(null);
    try {
      const res = await axios.get(`/api/decision-support/appointment-risk/${targetId}`);
      setResponseStatus(res.data?.data?.status || res.data?.status || 'ok');
      setAssessment(res.data?.data || null);
    } catch (err) {
      console.error('Risk assessment failed', err);
      if (err.response?.status === 404) {
        setError('Appointment not found. Please enter a valid appointment ID.');
      } else {
        setError('Failed to fetch risk assessment.');
      }
    } finally {
      setLoading(false);
    }
  }, [appointmentId]);

  const handleAccept = async () => {
    if (!assessment) return;
    try {
      await axios.post('/api/decision-support/outcome', {
        appointment_id: assessment.appointment_id || appointmentId,
        outcome: 'completed',
        feedback: 'accepted',
      });
    } catch (_) {}
    alert('Risk assessment accepted.');
  };

  const handleOverride = async () => {
    if (!assessment) return;
    try {
      await axios.post('/api/decision-support/outcome', {
        appointment_id: assessment.appointment_id || appointmentId,
        outcome: 'completed',
        feedback: 'overridden',
        reason: overrideReason || 'Admin override via decision support',
      });
    } catch (_) {}
    setShowOverrideInput(false);
    setOverrideReason('');
    alert('Assessment overridden.');
  };

  const getRiskConfig = (level) => {
    const cfgs = {
      high: {
        label: 'High Risk', bar: 'bg-red-500',
        text: isDarkMode ? 'text-red-400' : 'text-red-600',
        bg: isDarkMode ? 'bg-red-500/10' : 'bg-red-50',
        border: isDarkMode ? 'border-red-500/30' : 'border-red-200',
      },
      medium: {
        label: 'Medium Risk', bar: 'bg-amber-500',
        text: isDarkMode ? 'text-amber-400' : 'text-amber-600',
        bg: isDarkMode ? 'bg-amber-500/10' : 'bg-amber-50',
        border: isDarkMode ? 'border-amber-500/30' : 'border-amber-200',
      },
      low: {
        label: 'Low Risk', bar: 'bg-green-500',
        text: isDarkMode ? 'text-green-400' : 'text-green-600',
        bg: isDarkMode ? 'bg-green-500/10' : 'bg-green-50',
        border: isDarkMode ? 'border-green-500/30' : 'border-green-200',
      },
    };
    return cfgs[level] || cfgs.low;
  };

  const getImpactBadge = (impact) => {
    const cfgs = {
      high: isDarkMode ? 'bg-red-500/20 text-red-300 border-red-500/30' : 'bg-red-50 text-red-700 border-red-200',
      medium: isDarkMode ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' : 'bg-amber-50 text-amber-700 border-amber-200',
      low: isDarkMode ? 'bg-blue-500/20 text-blue-300 border-blue-500/30' : 'bg-blue-50 text-blue-700 border-blue-200',
    };
    const c = cfgs[impact] || cfgs.low;
    return <span className={`text-xs font-medium px-1.5 py-0.5 rounded border ${c}`}>{impact}</span>;
  };

  // no_model response
  if (responseStatus === 'no_model' && !loading && assessment) {
    return (
      <div className="space-y-4">
        <NoModelBanner isDarkMode={isDarkMode} message={assessment.message} />
        <div className="flex items-center gap-2">
          <input
            type="number"
            value={appointmentId}
            onChange={(e) => setAppointmentId(e.target.value)}
            placeholder="Appointment ID"
            className={cls.inputCls + ' max-w-[200px]'}
          />
          <button onClick={() => fetchRisk()} disabled={loading || !appointmentId} className={cls.btnPrimary}>
            Assess Risk
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Today's Risk Overview - Auto-loaded */}
      {loadingOverview && !riskOverview.length && (
        <LoadingState isDarkMode={isDarkMode} message="Scanning today's appointments for risk..." />
      )}
      {riskOverview.length > 0 && !assessment && (
        <div className={cls.cardInner + ' p-4'}>
          <div className="flex items-center gap-2 mb-3">
            <ShieldExclamationIcon className={`h-4 w-4 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
            <h4 className={`text-xs font-bold uppercase tracking-wide ${cls.textPrimary}`}>
              Today's Risk Overview
            </h4>
            <span className={`ml-auto text-xs px-2 py-0.5 rounded-full ${isDarkMode ? 'bg-blue-500/15 text-blue-300' : 'bg-blue-50 text-blue-700'}`}>
              AI-assessed
            </span>
          </div>
          <div className="space-y-2">
            {riskOverview.map((appt) => {
              const risk = appt.risk;
              const riskColor = risk.risk_level === 'high'
                ? isDarkMode ? 'text-red-400 bg-red-500/10 border-red-500/30' : 'text-red-600 bg-red-50 border-red-200'
                : risk.risk_level === 'medium'
                ? isDarkMode ? 'text-amber-400 bg-amber-500/10 border-amber-500/30' : 'text-amber-600 bg-amber-50 border-amber-200'
                : isDarkMode ? 'text-green-400 bg-green-500/10 border-green-500/30' : 'text-green-600 bg-green-50 border-green-200';
              return (
                <button
                  key={appt.id}
                  onClick={() => { setAppointmentId(String(appt.id)); fetchRisk(appt.id); }}
                  className={`w-full text-left p-3 rounded-lg border transition-colors ${riskColor} hover:opacity-80`}
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <span className={`text-sm font-semibold ${cls.textPrimary}`}>
                        #{appt.id} — {appt.customer_name || appt.user?.name || `User ${appt.user_id}`}
                      </span>
                      <p className={`text-xs mt-0.5 ${cls.textSecondary}`}>
                        {appt.appointment_time} • {appt.service?.name || appt.service_type || 'Service'}
                      </p>
                    </div>
                    <div className="text-right">
                      <span className={`text-lg font-black ${riskColor.split(' ')[0]}`}>{risk.risk_score}</span>
                      <p className={`text-xs capitalize font-medium ${riskColor.split(' ')[0]}`}>{risk.risk_level}</p>
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        </div>
      )}
      {!loadingOverview && riskOverview.length === 0 && todayAppointments.length > 0 && !assessment && (
        <div className={`p-3 rounded-lg text-xs ${isDarkMode ? 'bg-green-500/10 text-green-300 border border-green-500/30' : 'bg-green-50 text-green-700 border border-green-200'}`}>
          <div className="flex items-center gap-2">
            <ShieldCheckIcon className="h-4 w-4" />
            No high-risk appointments detected for today.
          </div>
        </div>
      )}

      {/* Input Row */}
      <div className="flex flex-wrap items-end gap-3">
        <div className="flex-1 min-w-[160px]">
          <label className={`block text-xs font-medium mb-1 ${cls.textSecondary}`}>Appointment ID</label>
          <input
            type="number"
            value={appointmentId}
            onChange={(e) => setAppointmentId(e.target.value)}
            placeholder="Enter ID..."
            className={cls.inputCls}
            onKeyDown={(e) => e.key === 'Enter' && fetchRisk()}
          />
        </div>
        <button onClick={() => fetchRisk()} disabled={loading || !appointmentId} className={`${cls.btnPrimary} ${(!appointmentId || loading) ? 'opacity-50 cursor-not-allowed' : ''}`}>
          <MagnifyingGlassIcon className="h-4 w-4 inline mr-1" />
          Assess Risk
        </button>
      </div>

      {/* Quick Selection - Today's Appointments */}
      {todayAppointments.length > 0 && !assessment && (
        <div>
          <p className={`text-xs font-semibold mb-2 ${cls.textSecondary}`}>Today's Appointments (Quick Select)</p>
          <div className="flex flex-wrap gap-1.5">
            {todayAppointments.slice(0, 12).map((appt) => (
              <button
                key={appt.id}
                onClick={() => { setAppointmentId(String(appt.id)); fetchRisk(appt.id); }}
                className={`px-2.5 py-1 rounded-lg text-xs font-medium border transition-colors ${
                  isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-700 hover:border-amber-500/50' : 'border-gray-300 text-gray-600 hover:bg-amber-50 hover:border-amber-400'
                }`}
              >
                #{appt.id} - {appt.customer_name || appt.user?.name || `User ${appt.user_id}`}
              </button>
            ))}
          </div>
        </div>
      )}

      {loading && <LoadingState isDarkMode={isDarkMode} message="Assessing appointment risk..." />}
      {error && <ErrorState isDarkMode={isDarkMode} message={error} onRetry={() => fetchRisk()} />}

      {/* Assessment Results */}
      {assessment && !loading && assessment.risk_score != null && (
        <div className="space-y-4">
          {(() => {
            const config = getRiskConfig(assessment.risk_level);
            const scorePercent = assessment.max_risk_score
              ? Math.min((assessment.risk_score / assessment.max_risk_score) * 100, 100)
              : Math.min(assessment.risk_score, 100);

            const riskFactors = (assessment.risk_factors || []).map(f =>
              typeof f === 'string' ? { factor: f, impact: 'medium', points: 0 } : f
            );
            const visibleFactors = showAllFactors ? riskFactors : riskFactors.slice(0, 4);

            return (
              <>
                {/* Large Risk Gauge */}
                <div className={`p-5 rounded-xl border ${config.bg} ${config.border}`}>
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                      {assessment.risk_level === 'high' ? (
                        <ExclamationTriangleIcon className={`h-6 w-6 ${config.text}`} />
                      ) : assessment.risk_level === 'low' ? (
                        <ShieldCheckIcon className={`h-6 w-6 ${config.text}`} />
                      ) : (
                        <ExclamationTriangleIcon className={`h-6 w-6 ${config.text}`} />
                      )}
                      <span className={`text-lg font-bold capitalize ${config.text}`}>{config.label}</span>
                    </div>
                    <div className="text-right">
                      <span className={`text-3xl font-black ${config.text}`}>{assessment.risk_score}</span>
                      {assessment.max_risk_score && (
                        <span className={`text-sm ${cls.textMuted}`}> / {assessment.max_risk_score}</span>
                      )}
                    </div>
                  </div>

                  {/* Gauge Bar */}
                  <div className={`w-full h-3 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
                    <div
                      className={`h-full rounded-full transition-all duration-700 ${config.bar}`}
                      style={{ width: `${scorePercent}%` }}
                    />
                  </div>

                  {/* Confidence */}
                  {assessment.confidence != null && (
                    <p className={`text-xs mt-2 ${cls.textSecondary}`}>
                      Confidence: <span className={`font-semibold ${cls.textPrimary}`}>
                        {typeof assessment.confidence === 'number'
                          ? `${(assessment.confidence * 100).toFixed(0)}%`
                          : assessment.confidence}
                      </span>
                    </p>
                  )}
                </div>

                {/* Risk Factors - Horizontal Bars */}
                {riskFactors.length > 0 && (
                  <div className={cls.cardInner + ' p-4'}>
                    <p className={`text-xs font-bold mb-3 ${cls.textPrimary}`}>
                      Risk Factors ({riskFactors.length})
                    </p>
                    <div className="space-y-2">
                      {visibleFactors.map((factor, idx) => (
                        <div key={idx} className={`p-2.5 rounded-lg ${isDarkMode ? 'bg-gray-700/30' : 'bg-white/60'}`}>
                          <div className="flex items-start justify-between gap-2">
                            <div className="flex items-start gap-2 flex-1 min-w-0">
                              <ExclamationTriangleIcon className={`h-4 w-4 mt-0.5 flex-shrink-0 ${isDarkMode ? 'text-red-400' : 'text-red-500'}`} />
                              <div className="min-w-0 flex-1">
                                <p className={`text-xs font-medium ${cls.textPrimary}`}>{factor.factor}</p>
                                {factor.detail && (
                                  <p className={`text-xs mt-0.5 ${cls.textMuted}`}>{factor.detail}</p>
                                )}
                                {/* Impact bar */}
                                {(factor.importance != null || factor.points > 0) && (
                                  <div className="mt-1.5">
                                    <div className={`w-full h-1.5 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
                                      <div
                                        className="h-full rounded-full bg-red-500 transition-all"
                                        style={{ width: `${Math.min((factor.importance || factor.points || 0) * 100, 100)}%` }}
                                      />
                                    </div>
                                  </div>
                                )}
                              </div>
                            </div>
                            <div className="flex items-center gap-1.5 flex-shrink-0">
                              {factor.impact && getImpactBadge(factor.impact)}
                              {factor.points > 0 && (
                                <span className={`text-xs font-bold ${isDarkMode ? 'text-red-400' : 'text-red-600'}`}>+{factor.points}</span>
                              )}
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                    {riskFactors.length > 4 && (
                      <button
                        onClick={() => setShowAllFactors(!showAllFactors)}
                        className={`flex items-center gap-1 mt-2 text-xs font-medium ${cls.textSecondary}`}
                      >
                        {showAllFactors ? (
                          <>Show less <ChevronUpIcon className="h-3 w-3" /></>
                        ) : (
                          <>Show {riskFactors.length - 4} more <ChevronDownIcon className="h-3 w-3" /></>
                        )}
                      </button>
                    )}
                  </div>
                )}

                {/* Positive Factors */}
                {assessment.positive_factors && assessment.positive_factors.length > 0 && (
                  <div className={cls.cardInner + ' p-4'}>
                    <p className={`text-xs font-bold mb-2 flex items-center gap-1.5 ${isDarkMode ? 'text-green-400' : 'text-green-700'}`}>
                      <HandThumbUpIcon className="h-4 w-4" />
                      Positive Factors
                    </p>
                    <div className="space-y-1">
                      {assessment.positive_factors.map((factor, idx) => {
                        const text = typeof factor === 'string' ? factor : factor.factor || factor.description;
                        return (
                          <div key={idx} className="flex items-center gap-2">
                            <CheckCircleIcon className={`h-4 w-4 flex-shrink-0 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
                            <p className={`text-xs ${isDarkMode ? 'text-green-300' : 'text-green-700'}`}>{text}</p>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                {/* Reasoning */}
                {assessment.reasoning && assessment.reasoning.length > 0 && (
                  <div className={cls.cardInner + ' p-4'}>
                    <p className={`text-xs font-bold mb-2 ${cls.textPrimary}`}>Reasoning</p>
                    <ul className="space-y-1.5">
                      {assessment.reasoning.map((r, idx) => (
                        <li key={idx} className="flex items-start gap-2">
                          <span className={`mt-1 block w-1.5 h-1.5 rounded-full flex-shrink-0 ${isDarkMode ? 'bg-gray-500' : 'bg-gray-400'}`} />
                          <p className={`text-xs ${cls.textSecondary}`}>{typeof r === 'string' ? r : r.description || r.text}</p>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* Recommendations */}
                {assessment.recommendations && assessment.recommendations.length > 0 && (
                  <div className={cls.cardInner + ' p-4'}>
                    <p className={`text-xs font-bold mb-2 ${cls.textPrimary}`}>
                      Recommendations ({assessment.recommendations.length})
                    </p>
                    <div className="space-y-2">
                      {assessment.recommendations.map((rec, idx) => {
                        const isHighPriority = rec.priority === 'high';
                        return (
                          <div key={idx} className={`p-2.5 rounded-lg border ${
                            isHighPriority
                              ? isDarkMode ? 'bg-red-500/10 border-red-500/30' : 'bg-red-50 border-red-200'
                              : isDarkMode ? 'bg-gray-700/30 border-gray-600/50' : 'bg-gray-50 border-gray-200'
                          }`}>
                            <div className="flex items-start gap-2">
                              <ClipboardDocumentCheckIcon className={`h-4 w-4 mt-0.5 flex-shrink-0 ${
                                isHighPriority
                                  ? isDarkMode ? 'text-red-400' : 'text-red-600'
                                  : isDarkMode ? 'text-gray-400' : 'text-gray-500'
                              }`} />
                              <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2">
                                  <p className={`text-xs font-semibold ${cls.textPrimary}`}>{rec.title || rec.description}</p>
                                  {rec.priority && (
                                    <span className={`text-xs px-1.5 py-0.5 rounded border font-medium ${
                                      isHighPriority
                                        ? isDarkMode ? 'bg-red-500/20 text-red-300 border-red-500/30' : 'bg-red-100 text-red-700 border-red-200'
                                        : isDarkMode ? 'bg-gray-600 text-gray-300 border-gray-500' : 'bg-gray-100 text-gray-600 border-gray-200'
                                    }`}>{rec.priority}</span>
                                  )}
                                </div>
                                {rec.title && rec.description && (
                                  <p className={`text-xs mt-0.5 ${cls.textMuted}`}>{rec.description}</p>
                                )}
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                {/* Accept / Override Buttons */}
                <div className="flex items-center gap-2 pt-1">
                  <button onClick={handleAccept} className={cls.btnSuccess}>
                    <CheckCircleIcon className="h-4 w-4 inline mr-1" />
                    Accept Assessment
                  </button>
                  <button
                    onClick={() => setShowOverrideInput(!showOverrideInput)}
                    className={cls.btnDanger}
                  >
                    <ShieldExclamationIcon className="h-4 w-4 inline mr-1" />
                    Override
                  </button>
                </div>

                {showOverrideInput && (
                  <div className={`p-3 rounded-lg border ${isDarkMode ? 'bg-gray-800 border-gray-700' : 'bg-gray-50 border-gray-200'}`}>
                    <label className={`block text-xs font-medium mb-1 ${cls.textSecondary}`}>Override reason (optional)</label>
                    <input
                      type="text"
                      value={overrideReason}
                      onChange={(e) => setOverrideReason(e.target.value)}
                      placeholder="Why are you overriding this assessment?"
                      className={cls.inputCls}
                    />
                    <div className="flex gap-2 mt-2">
                      <button onClick={handleOverride} className={cls.btnDanger}>Confirm Override</button>
                      <button onClick={() => setShowOverrideInput(false)} className={cls.btnSecondary}>Cancel</button>
                    </div>
                  </div>
                )}
              </>
            );
          })()}
        </div>
      )}
    </div>
  );
};

// ═══════════════════════════════════════════════════════════════════════════════
// TAB 5 - WORKLOAD OVERVIEW
// ═══════════════════════════════════════════════════════════════════════════════

const WorkloadTab = ({ isDarkMode, cls, onRefresh }) => {
  const [date, setDate] = useState(todayStr());
  const [staffData, setStaffData] = useState([]);
  const [summary, setSummary] = useState(null);
  const [insights, setInsights] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [expandedStaff, setExpandedStaff] = useState(null);

  const fetchWorkload = useCallback(async () => {
    if (!date) return;
    setLoading(true);
    setError(null);
    try {
      const res = await axios.get('/api/decision-support/workload-optimization', {
        params: { appointment_date: date },
      });
      const data = res.data?.data;
      if (Array.isArray(data)) {
        setStaffData(data);
      } else if (data?.staff) {
        setStaffData(data.staff);
      } else {
        setStaffData(data || []);
      }
      setSummary(res.data?.summary || null);
      setInsights(res.data?.insights || []);
      if (onRefresh) onRefresh();
    } catch (err) {
      console.error('Workload fetch failed', err);
      setError('Failed to fetch workload data.');
    } finally {
      setLoading(false);
    }
  }, [date]);

  const handleFetch = () => fetchWorkload();

  // Auto-fetch on mount and auto-refresh every 120s
  useEffect(() => {
    fetchWorkload();
    const interval = setInterval(fetchWorkload, 120000);
    return () => clearInterval(interval);
  }, [fetchWorkload]);

  const getStatusConfig = (status) => {
    const cfgs = {
      available: {
        label: 'Available', bar: 'bg-green-500', dot: 'bg-green-400',
        text: isDarkMode ? 'text-green-400' : 'text-green-600',
        bg: isDarkMode ? 'bg-green-500/10' : 'bg-green-50',
        border: isDarkMode ? 'border-green-500/30' : 'border-green-200',
      },
      busy: {
        label: 'Busy', bar: 'bg-amber-500', dot: 'bg-amber-400',
        text: isDarkMode ? 'text-amber-400' : 'text-amber-600',
        bg: isDarkMode ? 'bg-amber-500/10' : 'bg-amber-50',
        border: isDarkMode ? 'border-amber-500/30' : 'border-amber-200',
      },
      overloaded: {
        label: 'Overloaded', bar: 'bg-red-500', dot: 'bg-red-400',
        text: isDarkMode ? 'text-red-400' : 'text-red-600',
        bg: isDarkMode ? 'bg-red-500/10' : 'bg-red-50',
        border: isDarkMode ? 'border-red-500/30' : 'border-red-200',
      },
    };
    return cfgs[status] || cfgs.available;
  };

  const getBalanceColor = (score) => {
    if (score >= 80) return { text: isDarkMode ? 'text-green-400' : 'text-green-600', bar: 'bg-green-500', label: 'Excellent' };
    if (score >= 60) return { text: isDarkMode ? 'text-blue-400' : 'text-blue-600', bar: 'bg-blue-500', label: 'Good' };
    if (score >= 40) return { text: isDarkMode ? 'text-amber-400' : 'text-amber-600', bar: 'bg-amber-500', label: 'Fair' };
    return { text: isDarkMode ? 'text-red-400' : 'text-red-600', bar: 'bg-red-500', label: 'Needs Attention' };
  };

  return (
    <div className="space-y-4">
      <DatePickerRow cls={cls} isDarkMode={isDarkMode} date={date} setDate={setDate} onFetch={handleFetch} loading={loading} />

      {loading && <LoadingState isDarkMode={isDarkMode} message="Analyzing workload..." />}
      {error && <ErrorState isDarkMode={isDarkMode} message={error} onRetry={handleFetch} />}

      {/* Summary Stats */}
      {summary && !loading && (
        <div className="space-y-3">
          {/* Balance Score */}
          {summary.balance_score != null && (
            <div className={cls.cardInner + ' p-4'}>
              {(() => {
                const bc = getBalanceColor(summary.balance_score);
                return (
                  <>
                    <div className="flex items-center justify-between mb-2">
                      <span className={`text-xs font-medium ${cls.textSecondary}`}>Workload Balance</span>
                      <span className={`text-sm font-bold ${bc.text}`}>{summary.balance_score}% -- {bc.label}</span>
                    </div>
                    <div className={`w-full h-2.5 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
                      <div className={`h-full rounded-full transition-all ${bc.bar}`} style={{ width: `${summary.balance_score}%` }} />
                    </div>
                  </>
                );
              })()}
            </div>
          )}

          {/* Quick Stats */}
          <div className="grid grid-cols-4 gap-2">
            <StatCard label="Staff" value={summary.total_staff ?? staffData.length} isDarkMode={isDarkMode} color={isDarkMode ? 'text-blue-400' : 'text-blue-600'} />
            <StatCard label="Available" value={summary.total_available ?? '-'} isDarkMode={isDarkMode} color={isDarkMode ? 'text-green-400' : 'text-green-600'} />
            <StatCard label="Avg Load" value={summary.average_load ?? '-'} isDarkMode={isDarkMode} color={isDarkMode ? 'text-amber-400' : 'text-amber-600'} />
            <StatCard label="Utilization" value={summary.utilization_percentage != null ? `${summary.utilization_percentage}%` : '-'} isDarkMode={isDarkMode} color={isDarkMode ? 'text-purple-400' : 'text-purple-600'} />
          </div>
        </div>
      )}

      {/* Staff Cards */}
      {staffData.length > 0 && !loading && (
        <div className="space-y-2">
          {staffData.map((staff) => {
            const config = getStatusConfig(staff.status);
            const isExpanded = expandedStaff === staff.staff_id;
            const isHighlighted = staff.status === 'overloaded' || staff.status === 'busy';

            return (
              <div
                key={staff.staff_id}
                className={`rounded-lg border transition-all cursor-pointer ${config.bg} ${config.border} ${
                  isHighlighted ? 'ring-1 ring-offset-0' : ''
                } ${staff.status === 'overloaded' ? (isDarkMode ? 'ring-red-500/30' : 'ring-red-200') : staff.status === 'busy' ? (isDarkMode ? 'ring-amber-500/20' : 'ring-amber-200') : ''}`}
                onClick={() => setExpandedStaff(isExpanded ? null : staff.staff_id)}
              >
                <div className="p-3">
                  <div className="flex items-center justify-between mb-2">
                    <div className="flex items-center gap-2">
                      <span className={`w-2.5 h-2.5 rounded-full ${config.dot}`} />
                      <p className={`text-sm font-semibold ${cls.textPrimary}`}>{staff.staff_name}</p>
                    </div>
                    <span className={`text-xs font-bold px-2 py-0.5 rounded-full border ${config.text} ${config.bg} ${config.border}`}>
                      {config.label}
                    </span>
                  </div>

                  {/* Utilization Bar */}
                  <div className="flex items-center gap-2 mb-1.5">
                    <div className={`flex-1 h-2 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
                      <div
                        className={`h-full rounded-full transition-all ${config.bar}`}
                        style={{ width: `${Math.min(staff.capacity_percentage || 0, 100)}%` }}
                      />
                    </div>
                    <span className={`text-xs font-medium w-10 text-right ${config.text}`}>
                      {Math.round(staff.capacity_percentage || 0)}%
                    </span>
                  </div>

                  {/* Quick Info */}
                  <div className="flex items-center gap-3 text-xs">
                    <span className={cls.textSecondary}>
                      {staff.appointments_scheduled} appts
                    </span>
                    <span className={cls.textMuted}>|</span>
                    <span className={cls.textSecondary}>
                      {staff.available_slots} slots free
                    </span>
                    {staff.next_available && (
                      <>
                        <span className={cls.textMuted}>|</span>
                        <span className={`flex items-center gap-1 ${cls.textSecondary}`}>
                          <ClockIcon className="h-3 w-3" />
                          Next: {staff.next_available}
                        </span>
                      </>
                    )}
                  </div>
                </div>

                {/* Expanded Details */}
                {isExpanded && (
                  <div className={`px-3 pb-3 pt-1 border-t ${isDarkMode ? 'border-gray-700/50' : 'border-gray-200/80'}`}>
                    {/* Time Distribution */}
                    {staff.time_distribution && Object.keys(staff.time_distribution).length > 0 && (
                      <div className="mb-2">
                        <p className={`text-xs font-medium mb-1.5 ${cls.textSecondary}`}>Time Distribution</p>
                        <div className="grid grid-cols-3 gap-1.5">
                          {Object.entries(staff.time_distribution).map(([period, count]) => (
                            <div key={period} className={`text-center p-1.5 rounded ${isDarkMode ? 'bg-gray-700/50' : 'bg-white/80'}`}>
                              <p className={`text-xs capitalize ${cls.textSecondary}`}>{period}</p>
                              <p className={`text-sm font-bold ${cls.textPrimary}`}>{count}</p>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* Recommendation */}
                    {staff.recommendation && (
                      <div className={`p-2 rounded text-xs ${isDarkMode ? 'bg-gray-700/30 text-gray-300' : 'bg-white/60 text-gray-600'}`}>
                        <strong>Suggestion:</strong> {staff.recommendation}
                      </div>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* Insights */}
      {insights.length > 0 && !loading && (
        <div className={`rounded-lg border p-3 space-y-2 ${isDarkMode ? 'bg-gray-700/30 border-gray-700' : 'bg-blue-50/50 border-blue-100'}`}>
          <p className={`text-xs font-bold flex items-center gap-1.5 ${cls.textPrimary}`}>
            <LightBulbIcon className="h-4 w-4 text-amber-500" />
            Redistribution Insights
          </p>
          {insights.map((insight, idx) => (
            <div key={idx} className="flex items-start gap-2">
              {insight.type === 'warning' || insight.severity === 'warning' ? (
                <ExclamationTriangleIcon className="h-4 w-4 text-amber-500 flex-shrink-0 mt-0.5" />
              ) : insight.type === 'success' || insight.severity === 'success' ? (
                <CheckCircleIcon className="h-4 w-4 text-green-500 flex-shrink-0 mt-0.5" />
              ) : (
                <LightBulbIcon className="h-4 w-4 text-blue-400 flex-shrink-0 mt-0.5" />
              )}
              <div>
                {insight.title && <p className={`text-xs font-medium ${cls.textPrimary}`}>{insight.title}</p>}
                <p className={`text-xs ${cls.textSecondary}`}>{insight.message}</p>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Best Assignment Footer */}
      {staffData.length > 0 && !loading && (
        <div className={`px-4 py-2.5 rounded-lg text-xs flex items-center gap-2 ${
          isDarkMode ? 'bg-blue-500/5 text-blue-300 border border-blue-500/20' : 'bg-blue-50/80 text-blue-700 border border-blue-200'
        }`}>
          <ArrowTrendingUpIcon className="h-4 w-4" />
          <span>
            <strong>Best assignment:</strong>{' '}
            {staffData.reduce((best, s) => (s.available_slots || 0) > (best.available_slots || 0) ? s : best, staffData[0]).staff_name}
            {' -- most available capacity'}
          </span>
        </div>
      )}

      {staffData.length === 0 && !loading && !error && (
        <EmptyState isDarkMode={isDarkMode} message="No staff workload data available for this date." />
      )}
    </div>
  );
};

// ═══════════════════════════════════════════════════════════════════════════════
// SHARED SUB-COMPONENTS
// ═══════════════════════════════════════════════════════════════════════════════

const LoadingState = ({ isDarkMode, message }) => (
  <div className="flex items-center justify-center py-8">
    <div className="flex flex-col items-center gap-3">
      <div className={`animate-spin rounded-full h-7 w-7 border-2 border-t-transparent ${isDarkMode ? 'border-amber-400' : 'border-amber-500'}`} />
      <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{message || 'Loading...'}</p>
    </div>
  </div>
);

const ErrorState = ({ isDarkMode, message, onRetry }) => (
  <div className={`p-4 rounded-lg border ${isDarkMode ? 'bg-red-500/10 border-red-500/30' : 'bg-red-50 border-red-200'}`}>
    <div className="flex items-center gap-2">
      <ExclamationTriangleIcon className={`h-5 w-5 ${isDarkMode ? 'text-red-400' : 'text-red-500'}`} />
      <p className={`text-sm ${isDarkMode ? 'text-red-300' : 'text-red-700'}`}>{message}</p>
    </div>
    {onRetry && (
      <button onClick={onRetry} className={`mt-2 text-xs underline ${isDarkMode ? 'text-red-400 hover:text-red-300' : 'text-red-600 hover:text-red-500'}`}>
        Try again
      </button>
    )}
  </div>
);

const EmptyState = ({ isDarkMode, message }) => (
  <div className={`text-center py-8 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
    <InformationCircleIcon className="h-8 w-8 mx-auto mb-2 opacity-50" />
    <p className="text-sm">{message}</p>
  </div>
);

const NoModelBanner = ({ isDarkMode, message }) => (
  <div className={`p-4 rounded-lg border ${isDarkMode ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50 border-amber-200'}`}>
    <div className="flex items-start gap-3">
      <AcademicCapIcon className={`h-5 w-5 mt-0.5 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
      <div>
        <p className={`text-sm font-semibold ${isDarkMode ? 'text-amber-300' : 'text-amber-800'}`}>
          ML Model Not Trained
        </p>
        <p className={`text-xs mt-1 ${isDarkMode ? 'text-amber-200/70' : 'text-amber-700'}`}>
          {message || 'The ML model has not been trained yet. Go to the Data Quality tab and click "Train Model" when you have 500+ appointment records.'}
        </p>
      </div>
    </div>
  </div>
);

const ClassBar = ({ label, count, total, color, isDarkMode }) => {
  const percentage = pct(count, total);
  return (
    <div>
      <div className="flex items-center justify-between text-xs mb-1">
        <span className={isDarkMode ? 'text-gray-300' : 'text-gray-700'}>{label}</span>
        <span className={isDarkMode ? 'text-gray-400' : 'text-gray-500'}>{count} ({percentage}%)</span>
      </div>
      <div className={`w-full h-2 rounded-full overflow-hidden ${isDarkMode ? 'bg-gray-700' : 'bg-gray-200'}`}>
        <div className={`h-full rounded-full transition-all ${color}`} style={{ width: `${percentage}%` }} />
      </div>
    </div>
  );
};

const StatCard = ({ label, value, isDarkMode, color }) => (
  <div className={`p-2.5 rounded-lg text-center ${isDarkMode ? 'bg-gray-700/50' : 'bg-gray-50'}`}>
    <p className={`text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{label}</p>
    <p className={`text-lg font-bold ${color}`}>{value}</p>
  </div>
);

const InputRow = ({ cls, isDarkMode, date, setDate, time, setTime, serviceType, setServiceType, onFetch, loading }) => (
  <div className="flex flex-wrap items-end gap-3">
    <div className="min-w-[140px]">
      <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Date</label>
      <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className={cls.inputCls} />
    </div>
    <div className="min-w-[120px]">
      <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Time</label>
      <input type="time" value={time} onChange={(e) => setTime(e.target.value)} className={cls.inputCls} />
    </div>
    <div className="min-w-[140px] flex-1">
      <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Service (optional)</label>
      <input
        type="text"
        value={serviceType}
        onChange={(e) => setServiceType(e.target.value)}
        placeholder="e.g., consultation"
        className={cls.inputCls}
      />
    </div>
    <button onClick={onFetch} disabled={loading || !date || !time} className={`${cls.btnPrimary} ${(!date || !time || loading) ? 'opacity-50 cursor-not-allowed' : ''}`}>
      {loading ? (
        <ArrowPathIcon className="h-4 w-4 inline animate-spin" />
      ) : (
        <MagnifyingGlassIcon className="h-4 w-4 inline mr-1" />
      )}
      {loading ? ' Fetching...' : ' Get Recommendations'}
    </button>
  </div>
);

const DatePickerRow = ({ cls, isDarkMode, date, setDate, onFetch, loading }) => (
  <div className="flex items-end gap-3">
    <div className="min-w-[160px]">
      <label className={`block text-xs font-medium mb-1 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Date</label>
      <input type="date" value={date} onChange={(e) => setDate(e.target.value)} className={cls.inputCls} />
    </div>
    <button onClick={onFetch} disabled={loading || !date} className={`${cls.btnPrimary} ${(!date || loading) ? 'opacity-50 cursor-not-allowed' : ''}`}>
      {loading ? (
        <ArrowPathIcon className="h-4 w-4 inline animate-spin" />
      ) : (
        <MagnifyingGlassIcon className="h-4 w-4 inline mr-1" />
      )}
      {loading ? ' Loading...' : ' Fetch'}
    </button>
  </div>
);

export default AdminDecisionSupport;
