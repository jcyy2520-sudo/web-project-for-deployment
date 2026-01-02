import { useState, useEffect } from 'react';
import axios from 'axios';
import { XMarkIcon } from '@heroicons/react/24/outline';

const AdminFeedbackSettings = () => {
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [newWord, setNewWord] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      const resp = await axios.get('/api/admin/feedback/settings');
      const data = resp.data?.data || {};
      // Ensure profanity_list is an array
      if (data.profanity_list && typeof data.profanity_list === 'string') {
        data.profanity_list = data.profanity_list.split(',').map(w => w.trim()).filter(Boolean);
      } else if (!data.profanity_list) {
        data.profanity_list = [];
      }
      setSettings(data);
    } catch (err) {
      console.error('Failed to load settings', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload = {
        rate_limit: settings.rate_limit,
        cooldown_days: settings.cooldown_days,
        profanity_filter_enabled: settings.profanity_filter_enabled,
        duplicate_detection_enabled: settings.duplicate_detection_enabled,
        profanity_list: Array.isArray(settings.profanity_list) ? settings.profanity_list : []
      };
      await axios.put('/api/admin/feedback/settings', payload);
      alert('Settings saved successfully');
      load();
    } catch (err) {
      console.error('Failed to save settings', err);
      alert('Failed to save settings');
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

  return (
    <div className="bg-white dark:bg-gray-800 p-6 rounded-lg border border-gray-200 dark:border-gray-700 space-y-6">
      <div>
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-1">Feedback Settings</h3>
        <p className="text-sm text-gray-500 dark:text-gray-400">Manage feedback system behavior and content moderation</p>
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-8">
          <div className="w-6 h-6 border-2 border-amber-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
      ) : (
        <div className="space-y-6">
          {/* Rate Limiting Section */}
          <div className="p-4 bg-gray-50 dark:bg-gray-900/30 rounded-lg border border-gray-200 dark:border-gray-700">
            <h4 className="font-semibold text-sm text-gray-900 dark:text-white mb-3">Rate Limiting</h4>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">Rate Limit (submissions per period)</label>
                <input
                  type="number"
                  min="1"
                  max="100"
                  value={settings.rate_limit || 2}
                  onChange={e => setSettings(prev => ({...prev, rate_limit: parseInt(e.target.value || 1)}))}
                  className="w-full px-3 py-2 rounded-md bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Maximum feedback submissions allowed</p>
              </div>
              <div>
                <label className="text-xs font-medium text-gray-700 dark:text-gray-300 block mb-2">Cooldown Period (days)</label>
                <input
                  type="number"
                  min="1"
                  max="365"
                  value={settings.cooldown_days || 7}
                  onChange={e => setSettings(prev => ({...prev, cooldown_days: parseInt(e.target.value || 1)}))}
                  className="w-full px-3 py-2 rounded-md bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Period between rate limit resets</p>
              </div>
            </div>
          </div>

          {/* Content Moderation Section */}
          <div className="p-4 bg-gray-50 dark:bg-gray-900/30 rounded-lg border border-gray-200 dark:border-gray-700">
            <h4 className="font-semibold text-sm text-gray-900 dark:text-white mb-3">Content Moderation</h4>
            <div className="space-y-3">
              <div className="flex items-center space-x-3 p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800/50">
                <input
                  type="checkbox"
                  checked={!!settings.profanity_filter_enabled}
                  onChange={e => setSettings(prev => ({...prev, profanity_filter_enabled: e.target.checked}))}
                  className="rounded"
                />
                <div>
                  <label className="text-sm font-medium text-gray-900 dark:text-white block">Enable Profanity Filter</label>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Block feedback containing disallowed words</p>
                </div>
              </div>
              <div className="flex items-center space-x-3 p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800/50">
                <input
                  type="checkbox"
                  checked={!!settings.duplicate_detection_enabled}
                  onChange={e => setSettings(prev => ({...prev, duplicate_detection_enabled: e.target.checked}))}
                  className="rounded"
                />
                <div>
                  <label className="text-sm font-medium text-gray-900 dark:text-white block">Enable Duplicate Detection</label>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Block identical feedback within cooldown period</p>
                </div>
              </div>
            </div>
          </div>

          {/* Profanity List Section */}
          {settings.profanity_filter_enabled && (
            <div className="p-4 bg-gray-50 dark:bg-gray-900/30 rounded-lg border border-gray-200 dark:border-gray-700">
              <h4 className="font-semibold text-sm text-gray-900 dark:text-white mb-3">Profanity Word List</h4>
              
              {/* Add New Word */}
              <div className="mb-4 flex gap-2">
                <input
                  type="text"
                  placeholder="Enter disallowed word..."
                  value={newWord}
                  onChange={e => setNewWord(e.target.value)}
                  onKeyPress={e => e.key === 'Enter' && addWord()}
                  className="flex-1 px-3 py-2 rounded-md bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
                <button
                  onClick={addWord}
                  className="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-md text-sm font-medium transition-colors"
                >
                  Add
                </button>
              </div>

              {/* Words List */}
              <div className="space-y-2">
                {Array.isArray(settings.profanity_list) && settings.profanity_list.length > 0 ? (
                  <div className="flex flex-wrap gap-2">
                    {settings.profanity_list.map((word, idx) => (
                      <div
                        key={idx}
                        className="flex items-center gap-2 px-3 py-1 rounded-full bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 text-sm"
                      >
                        <span>{word}</span>
                        <button
                          onClick={() => removeWord(word)}
                          className="hover:text-red-600 dark:hover:text-red-400 transition-colors"
                        >
                          <XMarkIcon className="h-4 w-4" />
                        </button>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-sm text-gray-500 dark:text-gray-400 italic">No profanity words configured</p>
                )}
              </div>
            </div>
          )}

          {/* Save Button */}
          <div className="flex justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
            <button
              onClick={handleSave}
              disabled={saving}
              className="px-6 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg font-medium transition-colors"
            >
              {saving ? 'Saving...' : 'Save Settings'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default AdminFeedbackSettings;
