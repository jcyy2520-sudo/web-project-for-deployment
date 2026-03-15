import { useState, useEffect } from 'react';
import axios from 'axios';
import {
  XMarkIcon,
  ArrowPathIcon,
  ShieldCheckIcon,
  ClockIcon,
  FunnelIcon,
  UserGroupIcon,
  InformationCircleIcon,
  ArrowTrendingUpIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

const AdminFeedbackSettings = ({ isDarkMode = true }) => {
  const [settings, setSettings] = useState({});
  const [originalSettings, setOriginalSettings] = useState({});
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [newWord, setNewWord] = useState('');
  const [saveSuccess, setSaveSuccess] = useState(false);
  const [hasChanges, setHasChanges] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const resp = await axios.get('/api/admin/feedback/settings');
      const data = resp.data?.data || {};
      if (data.profanity_list && typeof data.profanity_list === 'string') {
        data.profanity_list = data.profanity_list.split(',').map(w => w.trim()).filter(Boolean);
      } else if (!data.profanity_list) {
        data.profanity_list = [];
      }
      setSettings(data);
      setOriginalSettings(JSON.parse(JSON.stringify(data)));
      setHasChanges(false);
    } catch (err) {
      console.error('Failed to load settings', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  // Track changes
  useEffect(() => {
    if (!originalSettings.rate_limit) return;
    const changed = 
      settings.rate_limit !== originalSettings.rate_limit ||
      settings.cooldown_days !== originalSettings.cooldown_days ||
      settings.profanity_filter_enabled !== originalSettings.profanity_filter_enabled ||
      settings.duplicate_detection_enabled !== originalSettings.duplicate_detection_enabled ||
      JSON.stringify(settings.profanity_list) !== JSON.stringify(originalSettings.profanity_list);
    setHasChanges(changed);
  }, [settings, originalSettings]);

  const handleSave = async () => {
    setSaving(true);
    setSaveSuccess(false);
    try {
      const payload = {
        rate_limit: settings.rate_limit,
        cooldown_days: settings.cooldown_days,
        profanity_filter_enabled: settings.profanity_filter_enabled,
        duplicate_detection_enabled: settings.duplicate_detection_enabled,
        profanity_list: Array.isArray(settings.profanity_list) ? settings.profanity_list : []
      };
      await axios.put('/api/admin/feedback/settings', payload);
      if (window?.showToast) window.showToast('Settings', 'Feedback settings saved successfully. Changes apply to all users immediately.', 'success');
      // Dispatch event so any user-facing feedback components refresh their rate limit
      window.dispatchEvent(new CustomEvent('feedbackSettingsChanged', { detail: payload }));
      setSaveSuccess(true);
      setOriginalSettings(JSON.parse(JSON.stringify(settings)));
      setHasChanges(false);
      setTimeout(() => setSaveSuccess(false), 5000);
    } catch (err) {
      console.error('Failed to save settings', err);
      if (window?.showToast) window.showToast('Settings', 'Failed to save feedback settings', 'error');
    } finally {
      setSaving(false);
    }
  };

  const addWord = () => {
    if (newWord.trim()) {
      const list = Array.isArray(settings.profanity_list) ? settings.profanity_list : [];
      if (!list.includes(newWord.trim().toLowerCase())) {
        setSettings(prev => ({
          ...prev,
          profanity_list: [...list, newWord.trim().toLowerCase()]
        }));
        setNewWord('');
      }
    }
  };

  const removeWord = (word) => {
    setSettings(prev => ({
      ...prev,
      profanity_list: (Array.isArray(prev.profanity_list) ? prev.profanity_list : []).filter(w => w !== word)
    }));
  };

  const getCooldownLabel = (days) => {
    if (days === 1) return '1 day';
    if (days === 3) return '3 days';
    if (days === 7) return '1 week';
    if (days === 14) return '2 weeks';
    if (days === 30) return '1 month';
    return `${days} days`;
  };

  const isLimitIncreasing = originalSettings.rate_limit && settings.rate_limit > originalSettings.rate_limit;
  const isLimitDecreasing = originalSettings.rate_limit && settings.rate_limit < originalSettings.rate_limit;

  return (
    <div className="space-y-5 sm:space-y-6 max-w-3xl lg:max-w-full">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
          <h2 className={`text-base sm:text-lg font-bold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Feedback Settings</h2>
          <p className={`text-xs sm:text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'} mt-0.5`}>
            Configure limits, moderation & content filtering. Changes apply immediately to all users.
          </p>
        </div>
        <button onClick={load} className={`self-end sm:self-auto p-2 rounded-lg transition-colors ${isDarkMode ? 'hover:bg-gray-800 text-gray-400 hover:text-amber-400' : 'hover:bg-gray-100 text-gray-500 hover:text-amber-600'}`} title="Refresh">
          <ArrowPathIcon className="h-5 w-5" />
        </button>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-12">
          <div className="w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
      ) : (
        <div className="space-y-4 sm:space-y-5">
          {/* Rate Limiting Section */}
          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-xl p-4 sm:p-5`}>
            <div className="flex items-center gap-2 mb-4">
              <ClockIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
              <h3 className={`font-semibold text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Rate Limiting</h3>
            </div>
            
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className={`text-xs font-medium ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} block mb-2`}>
                  Feedback Limit per User
                </label>
                <input
                  type="number"
                  min="1"
                  max="100"
                  value={settings.rate_limit || 2}
                  onChange={e => setSettings(prev => ({...prev, rate_limit: Math.max(1, parseInt(e.target.value) || 1)}))}
                  className={`w-full px-3 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-colors ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-gray-50 border-gray-300 text-gray-900'}`}
                />
                <p className={`text-[10px] sm:text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-1.5`}>
                  Max submissions per cooldown period
                </p>
              </div>
              <div>
                <label className={`text-xs font-medium ${isDarkMode ? 'text-gray-300' : 'text-gray-600'} block mb-2`}>
                  Cooldown Period (days)
                </label>
                <div className="flex items-center gap-2">
                  <input
                    type="number"
                    min="1"
                    max="365"
                    value={settings.cooldown_days || 7}
                    onChange={e => setSettings(prev => ({...prev, cooldown_days: Math.max(1, Math.min(365, parseInt(e.target.value) || 1))}))}
                    className={`w-24 px-3 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-colors ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-gray-50 border-gray-300 text-gray-900'}`}
                  />
                  <select
                    value={[1, 3, 7, 14, 30].includes(settings.cooldown_days) ? settings.cooldown_days : ''}
                    onChange={e => { if (e.target.value) setSettings(prev => ({...prev, cooldown_days: parseInt(e.target.value)})); }}
                    className={`flex-1 px-3 py-2.5 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition-colors ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-gray-50 border-gray-300 text-gray-900'}`}
                  >
                    <option value="" disabled>Quick presets</option>
                    <option value={1}>Every day</option>
                    <option value={3}>Every 3 days</option>
                    <option value={7}>Every week</option>
                    <option value={14}>Every 2 weeks</option>
                    <option value={30}>Every month</option>
                  </select>
                </div>
                <p className={`text-[10px] sm:text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'} mt-1.5`}>
                  Type a custom number or pick a preset. Rolling window — not a fixed calendar reset.
                </p>
              </div>
            </div>

            {/* Current Rule Preview */}
            <div className={`mt-4 p-3 rounded-lg text-xs sm:text-sm ${isDarkMode ? 'bg-amber-500/10 border border-amber-500/20 text-amber-300' : 'bg-amber-50 border border-amber-200 text-amber-700'}`}>
              <strong>Current Rule:</strong> Each user can submit up to <strong>{settings.rate_limit || 2}</strong> feedback{(settings.rate_limit || 2) !== 1 ? 's' : ''} every <strong>{getCooldownLabel(settings.cooldown_days || 7)}</strong>.
            </div>

            {/* Limit Change Impact Notice */}
            {isLimitIncreasing && (
              <div className={`mt-3 p-3 rounded-lg flex items-start gap-2.5 ${isDarkMode ? 'bg-green-500/10 border border-green-500/20' : 'bg-green-50 border border-green-200'}`}>
                <ArrowTrendingUpIcon className={`w-5 h-5 flex-shrink-0 mt-0.5 ${isDarkMode ? 'text-green-400' : 'text-green-600'}`} />
                <div>
                  <p className={`text-xs sm:text-sm font-semibold ${isDarkMode ? 'text-green-300' : 'text-green-700'}`}>
                    Increasing Limit: {originalSettings.rate_limit} → {settings.rate_limit}
                  </p>
                  <p className={`text-[10px] sm:text-xs mt-1 ${isDarkMode ? 'text-green-400/80' : 'text-green-600'}`}>
                    Users who have already submitted feedback will <strong>continue from their current count</strong> — their submissions will not reset to 0. 
                    For example, a user who has sent {originalSettings.rate_limit} feedback{originalSettings.rate_limit !== 1 ? 's' : ''} will immediately gain {settings.rate_limit - originalSettings.rate_limit} more submission{(settings.rate_limit - originalSettings.rate_limit) !== 1 ? 's' : ''}.
                  </p>
                </div>
              </div>
            )}

            {isLimitDecreasing && (
              <div className={`mt-3 p-3 rounded-lg flex items-start gap-2.5 ${isDarkMode ? 'bg-orange-500/10 border border-orange-500/20' : 'bg-orange-50 border border-orange-200'}`}>
                <ExclamationTriangleIcon className={`w-5 h-5 flex-shrink-0 mt-0.5 ${isDarkMode ? 'text-orange-400' : 'text-orange-600'}`} />
                <div>
                  <p className={`text-xs sm:text-sm font-semibold ${isDarkMode ? 'text-orange-300' : 'text-orange-700'}`}>
                    Decreasing Limit: {originalSettings.rate_limit} → {settings.rate_limit}
                  </p>
                  <p className={`text-[10px] sm:text-xs mt-1 ${isDarkMode ? 'text-orange-400/80' : 'text-orange-600'}`}>
                    Users who have already exceeded the new limit will be unable to submit until their older feedback falls outside the cooldown window. Their existing feedback count will <strong>not be reset</strong>.
                  </p>
                </div>
              </div>
            )}

            {/* How It Works */}
            <div className={`mt-3 p-3 rounded-lg ${isDarkMode ? 'bg-gray-800 border border-gray-700' : 'bg-gray-50 border border-gray-200'}`}>
              <div className="flex items-center gap-2 mb-2">
                <UserGroupIcon className={`w-4 h-4 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`} />
                <p className={`text-xs font-semibold ${isDarkMode ? 'text-gray-300' : 'text-gray-600'}`}>How Limits Work</p>
              </div>
              <ul className={`text-[10px] sm:text-xs space-y-1.5 ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>
                <li className="flex items-start gap-1.5">
                  <span className="text-amber-500 mt-0.5">•</span>
                  Changes apply <strong>immediately</strong> to all users — no restart needed.
                </li>
                <li className="flex items-start gap-1.5">
                  <span className="text-amber-500 mt-0.5">•</span>
                  User feedback counts are <strong>never reset</strong>. They continue from their current count.
                </li>
                <li className="flex items-start gap-1.5">
                  <span className="text-amber-500 mt-0.5">•</span>
                  When a user reaches the limit, they see the <strong>exact date and time</strong> (12-hour format) when they can submit again.
                </li>
                <li className="flex items-start gap-1.5">
                  <span className="text-amber-500 mt-0.5">•</span>
                  The cooldown is a <strong>rolling window</strong> — oldest feedback drops off continuously.
                </li>
              </ul>
            </div>
          </div>

          {/* Content Moderation */}
          <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-xl p-4 sm:p-5`}>
            <div className="flex items-center gap-2 mb-4">
              <ShieldCheckIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
              <h3 className={`font-semibold text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Content Moderation</h3>
            </div>

            <div className="space-y-2 sm:space-y-3">
              <label className={`flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors ${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'}`}>
                <div className="relative flex-shrink-0">
                  <input
                    type="checkbox"
                    checked={!!settings.profanity_filter_enabled}
                    onChange={e => setSettings(prev => ({...prev, profanity_filter_enabled: e.target.checked}))}
                    className="sr-only peer"
                  />
                  <div className={`w-9 h-5 rounded-full transition-colors ${settings.profanity_filter_enabled ? 'bg-amber-500' : isDarkMode ? 'bg-gray-700' : 'bg-gray-300'}`}></div>
                  <div className={`absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform ${settings.profanity_filter_enabled ? 'translate-x-4' : ''}`}></div>
                </div>
                <div>
                  <span className={`text-sm font-medium ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>Profanity Filter</span>
                  <p className={`text-[10px] sm:text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Block feedback containing disallowed words</p>
                </div>
              </label>

              <label className={`flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors ${isDarkMode ? 'hover:bg-gray-800' : 'hover:bg-gray-50'}`}>
                <div className="relative flex-shrink-0">
                  <input
                    type="checkbox"
                    checked={!!settings.duplicate_detection_enabled}
                    onChange={e => setSettings(prev => ({...prev, duplicate_detection_enabled: e.target.checked}))}
                    className="sr-only peer"
                  />
                  <div className={`w-9 h-5 rounded-full transition-colors ${settings.duplicate_detection_enabled ? 'bg-amber-500' : isDarkMode ? 'bg-gray-700' : 'bg-gray-300'}`}></div>
                  <div className={`absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform ${settings.duplicate_detection_enabled ? 'translate-x-4' : ''}`}></div>
                </div>
                <div>
                  <span className={`text-sm font-medium ${isDarkMode ? 'text-white' : 'text-gray-900'}`}>Duplicate Detection</span>
                  <p className={`text-[10px] sm:text-xs ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Block identical feedback within cooldown period</p>
                </div>
              </label>
            </div>
          </div>

          {/* Profanity List */}
          {settings.profanity_filter_enabled && (
            <div className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-amber-300/40'} border rounded-xl p-4 sm:p-5`}>
              <div className="flex items-center gap-2 mb-4">
                <FunnelIcon className={`h-5 w-5 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                <h3 className={`font-semibold text-sm ${isDarkMode ? 'text-amber-50' : 'text-amber-900'}`}>Blocked Words</h3>
                <span className={`text-xs px-2 py-0.5 rounded-full ${isDarkMode ? 'bg-gray-800 text-gray-400' : 'bg-gray-100 text-gray-500'}`}>
                  {Array.isArray(settings.profanity_list) ? settings.profanity_list.length : 0} words
                </span>
              </div>

              <div className="flex gap-2 mb-3">
                <input
                  type="text"
                  placeholder="Add blocked word..."
                  value={newWord}
                  onChange={e => setNewWord(e.target.value)}
                  onKeyDown={e => e.key === 'Enter' && (e.preventDefault(), addWord())}
                  className={`flex-1 px-3 py-2 rounded-lg text-sm border focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white placeholder-gray-500' : 'bg-gray-50 border-gray-300 text-gray-900 placeholder-gray-400'}`}
                />
                <button
                  onClick={addWord}
                  disabled={!newWord.trim()}
                  className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-medium transition-colors disabled:opacity-50"
                >
                  Add
                </button>
              </div>

              <div className="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                {Array.isArray(settings.profanity_list) && settings.profanity_list.length > 0 ? (
                  settings.profanity_list.map((word, idx) => (
                    <span
                      key={idx}
                      className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${isDarkMode ? 'bg-red-500/15 text-red-400 border border-red-500/30' : 'bg-red-50 text-red-600 border border-red-200'}`}
                    >
                      {word}
                      <button onClick={() => removeWord(word)} className="hover:text-red-300 transition-colors">
                        <XMarkIcon className="h-3 w-3" />
                      </button>
                    </span>
                  ))
                ) : (
                  <p className={`text-xs italic ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>No blocked words configured</p>
                )}
              </div>
            </div>
          )}

          {/* Save Section */}
          <div className={`flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pt-4 border-t ${isDarkMode ? 'border-gray-800' : 'border-gray-200'}`}>
            <div className="flex-1 min-w-0">
              {saveSuccess && (
                <div className="flex items-center gap-2 animate-fade-in">
                  <CheckCircleIcon className="w-4 h-4 text-green-400 flex-shrink-0" />
                  <p className="text-xs text-green-400">
                    Settings saved — changes are now active for all users
                  </p>
                </div>
              )}
              {hasChanges && !saveSuccess && (
                <div className="flex items-center gap-2">
                  <InformationCircleIcon className={`w-4 h-4 flex-shrink-0 ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`} />
                  <p className={`text-xs ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                    You have unsaved changes
                  </p>
                </div>
              )}
            </div>
            <div className="flex items-center gap-2 w-full sm:w-auto">
              {hasChanges && (
                <button
                  onClick={() => {
                    setSettings(JSON.parse(JSON.stringify(originalSettings)));
                    setHasChanges(false);
                  }}
                  className={`flex-1 sm:flex-initial px-4 py-2.5 rounded-lg text-sm font-medium transition-colors ${isDarkMode ? 'bg-gray-800 text-gray-300 hover:bg-gray-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
                >
                  Discard
                </button>
              )}
              <button
                onClick={handleSave}
                disabled={saving || !hasChanges}
                className="flex-1 sm:flex-initial px-6 py-2.5 bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white rounded-lg font-medium text-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow"
              >
                {saving ? (
                  <span className="flex items-center justify-center gap-2">
                    <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    Saving...
                  </span>
                ) : (
                  'Save Settings'
                )}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminFeedbackSettings;
