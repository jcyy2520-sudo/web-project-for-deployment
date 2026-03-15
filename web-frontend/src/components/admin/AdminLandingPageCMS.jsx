import React, { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
  PencilIcon,
  CheckIcon,
  XMarkIcon,
  EyeIcon,
  EyeSlashIcon,
  PlusIcon,
  TrashIcon,
  ArrowPathIcon,
  ChevronDownIcon,
  ChevronUpIcon,
  GlobeAltIcon,
} from '@heroicons/react/24/outline';

const AdminLandingPageCMS = ({ isDarkMode }) => {
  const [sections, setSections] = useState([]);
  const [settings, setSettings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [activeTab, setActiveTab] = useState('sections');
  const [expandedSection, setExpandedSection] = useState(null);
  const [editingSection, setEditingSection] = useState(null);
  const [editingSectionData, setEditingSectionData] = useState({});
  const [editingSetting, setEditingSetting] = useState(null);
  const [editingSettingValue, setEditingSettingValue] = useState('');
  const [editingItem, setEditingItem] = useState(null);
  const [editingItemData, setEditingItemData] = useState({});
  const [addingItemToSection, setAddingItemToSection] = useState(null);
  const [newItemData, setNewItemData] = useState({ title: '', description: '', icon: '', sort_order: 1 });

  const showSuccess = (msg) => {
    setSuccess(msg);
    setTimeout(() => setSuccess(null), 3000);
  };

  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      const [sectionsRes, settingsRes] = await Promise.all([
        axios.get('/api/admin/landing-page/sections'),
        axios.get('/api/admin/landing-page/settings'),
      ]);
      setSections(sectionsRes.data.data || []);
      setSettings(settingsRes.data.data || []);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load CMS data');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  // === Section operations ===
  const startEditSection = (section) => {
    setEditingSection(section.id);
    setEditingSectionData({
      title: section.title || '',
      subtitle: section.subtitle || '',
      description: section.description || '',
      badge_text: section.badge_text || '',
      button_primary_text: section.button_primary_text || '',
      button_primary_link: section.button_primary_link || '',
      button_secondary_text: section.button_secondary_text || '',
      button_secondary_link: section.button_secondary_link || '',
      image_url: section.image_url || '',
      image_alt: section.image_alt || '',
      is_visible: section.is_visible,
    });
  };

  const saveSection = async (id) => {
    try {
      setSaving(true);
      await axios.put(`/api/admin/landing-page/sections/${id}`, editingSectionData);
      setEditingSection(null);
      showSuccess('Section updated successfully');
      fetchData();
    } catch (err) {
      setError(err.response?.data?.messages ? Object.values(err.response.data.messages).flat().join(', ') : 'Failed to update section');
    } finally {
      setSaving(false);
    }
  };

  const toggleSectionVisibility = async (section) => {
    try {
      await axios.put(`/api/admin/landing-page/sections/${section.id}`, { is_visible: !section.is_visible });
      showSuccess(`Section ${!section.is_visible ? 'shown' : 'hidden'}`);
      fetchData();
    } catch {
      setError('Failed to toggle visibility');
    }
  };

  // === Item operations ===
  const startEditItem = (item) => {
    setEditingItem(item.id);
    setEditingItemData({
      title: item.title || '',
      description: item.description || '',
      icon: item.icon || '',
      step_number: item.step_number || '',
      link: item.link || '',
      sort_order: item.sort_order || 0,
      is_visible: item.is_visible,
    });
  };

  const saveItem = async (itemId) => {
    try {
      setSaving(true);
      await axios.put(`/api/admin/landing-page/items/${itemId}`, editingItemData);
      setEditingItem(null);
      showSuccess('Item updated successfully');
      fetchData();
    } catch (err) {
      setError(err.response?.data?.messages ? Object.values(err.response.data.messages).flat().join(', ') : 'Failed to update item');
    } finally {
      setSaving(false);
    }
  };

  const deleteItem = async (itemId) => {
    if (!window.confirm('Are you sure you want to delete this item?')) return;
    try {
      await axios.delete(`/api/admin/landing-page/items/${itemId}`);
      showSuccess('Item deleted');
      fetchData();
    } catch {
      setError('Failed to delete item');
    }
  };

  const addItem = async (sectionId) => {
    try {
      setSaving(true);
      await axios.post(`/api/admin/landing-page/sections/${sectionId}/items`, newItemData);
      setAddingItemToSection(null);
      setNewItemData({ title: '', description: '', icon: '', sort_order: 1 });
      showSuccess('Item added successfully');
      fetchData();
    } catch (err) {
      setError(err.response?.data?.messages ? Object.values(err.response.data.messages).flat().join(', ') : 'Failed to add item');
    } finally {
      setSaving(false);
    }
  };

  // === Settings operations ===
  const startEditSetting = (setting) => {
    setEditingSetting(setting.id);
    setEditingSettingValue(setting.value || '');
  };

  const saveSetting = async (id) => {
    try {
      setSaving(true);
      await axios.put(`/api/admin/landing-page/settings/${id}`, { value: editingSettingValue });
      setEditingSetting(null);
      showSuccess('Setting updated');
      fetchData();
    } catch {
      setError('Failed to update setting');
    } finally {
      setSaving(false);
    }
  };

  // Group settings by group
  const settingsByGroup = settings.reduce((acc, s) => {
    const g = s.group || 'general';
    if (!acc[g]) acc[g] = [];
    acc[g].push(s);
    return acc;
  }, {});

  const inputClass = `w-full px-3 py-2 rounded-lg text-sm border transition-colors ${
    isDarkMode
      ? 'bg-gray-800 border-gray-700 text-gray-200 focus:border-amber-500 focus:ring-1 focus:ring-amber-500'
      : 'bg-white border-gray-300 text-gray-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500'
  } outline-none`;

  const cardClass = `rounded-xl border transition-colors ${
    isDarkMode ? 'bg-gray-800/50 border-gray-700/50' : 'bg-white border-gray-200'
  }`;

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="flex flex-col items-center gap-3">
          <ArrowPathIcon className={`h-8 w-8 animate-spin ${isDarkMode ? 'text-amber-400' : 'text-blue-500'}`} />
          <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-600'}`}>Loading CMS data...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className={`p-2.5 rounded-xl ${isDarkMode ? 'bg-amber-500/10' : 'bg-blue-50'}`}>
            <GlobeAltIcon className={`h-6 w-6 ${isDarkMode ? 'text-amber-400' : 'text-blue-500'}`} />
          </div>
          <div>
            <h2 className={`text-xl font-bold ${isDarkMode ? 'text-gray-100' : 'text-gray-900'}`}>Landing Page CMS</h2>
            <p className={`text-sm ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Manage your landing page content and settings</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={fetchData}
            className={`px-3 py-2 text-sm rounded-lg border transition-colors flex items-center gap-1.5 ${
              isDarkMode ? 'border-gray-700 text-gray-300 hover:bg-gray-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50'
            }`}
          >
            <ArrowPathIcon className="h-4 w-4" /> Refresh
          </button>
          <a
            href="/"
            target="_blank"
            rel="noopener noreferrer"
            className={`px-3 py-2 text-sm rounded-lg border transition-colors flex items-center gap-1.5 ${
              isDarkMode ? 'border-amber-500/30 text-amber-400 hover:bg-amber-500/10' : 'border-blue-300 text-blue-600 hover:bg-blue-50'
            }`}
          >
            <EyeIcon className="h-4 w-4" /> Preview Site
          </a>
        </div>
      </div>

      {/* Status messages */}
      {error && (
        <div className="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-xl text-sm flex items-center justify-between">
          <span>{error}</span>
          <button onClick={() => setError(null)}><XMarkIcon className="h-4 w-4" /></button>
        </div>
      )}
      {success && (
        <div className={`px-4 py-3 rounded-xl text-sm flex items-center gap-2 ${isDarkMode ? 'bg-green-500/10 border border-green-500/30 text-green-400' : 'bg-green-50 border border-green-200 text-green-700'}`}>
          <CheckIcon className="h-4 w-4" /> {success}
        </div>
      )}

      {/* Tabs */}
      <div className={`flex gap-1 p-1 rounded-xl ${isDarkMode ? 'bg-gray-800/50' : 'bg-gray-100'}`}>
        {['sections', 'settings'].map(tab => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`flex-1 px-4 py-2.5 rounded-lg text-sm font-medium transition-all capitalize ${
              activeTab === tab
                ? (isDarkMode ? 'bg-amber-500/20 text-amber-400 shadow-sm' : 'bg-white text-blue-600 shadow-sm')
                : (isDarkMode ? 'text-gray-400 hover:text-gray-300' : 'text-gray-500 hover:text-gray-700')
            }`}
          >
            {tab === 'sections' ? `Page Sections (${sections.length})` : `Settings (${settings.length})`}
          </button>
        ))}
      </div>

      {/* Sections Tab */}
      {activeTab === 'sections' && (
        <div className="space-y-3">
          {sections.map(section => (
            <div key={section.id} className={cardClass}>
              {/* Section Header */}
              <div
                className={`flex items-center justify-between px-5 py-4 cursor-pointer ${
                  isDarkMode ? 'hover:bg-gray-700/30' : 'hover:bg-gray-50'
                } rounded-t-xl transition-colors`}
                onClick={() => setExpandedSection(expandedSection === section.id ? null : section.id)}
              >
                <div className="flex items-center gap-3 min-w-0">
                  <div className={`w-2 h-2 rounded-full flex-shrink-0 ${section.is_visible ? 'bg-green-500' : 'bg-gray-500'}`} />
                  <div className="min-w-0">
                    <h3 className={`font-semibold truncate ${isDarkMode ? 'text-gray-200' : 'text-gray-800'}`}>
                      {section.title || section.section_key}
                    </h3>
                    <p className={`text-xs ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                      Key: {section.section_key} &bull; Order: {section.sort_order} &bull; {(section.all_items || section.items || []).length} items
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                  <button
                    onClick={(e) => { e.stopPropagation(); toggleSectionVisibility(section); }}
                    className={`p-1.5 rounded-lg transition-colors ${
                      section.is_visible
                        ? (isDarkMode ? 'text-green-400 hover:bg-green-500/10' : 'text-green-600 hover:bg-green-50')
                        : (isDarkMode ? 'text-gray-500 hover:bg-gray-700' : 'text-gray-400 hover:bg-gray-100')
                    }`}
                    title={section.is_visible ? 'Hide section' : 'Show section'}
                  >
                    {section.is_visible ? <EyeIcon className="h-4 w-4" /> : <EyeSlashIcon className="h-4 w-4" />}
                  </button>
                  <button
                    onClick={(e) => { e.stopPropagation(); startEditSection(section); }}
                    className={`p-1.5 rounded-lg transition-colors ${isDarkMode ? 'text-amber-400 hover:bg-amber-500/10' : 'text-blue-500 hover:bg-blue-50'}`}
                    title="Edit section"
                  >
                    <PencilIcon className="h-4 w-4" />
                  </button>
                  {expandedSection === section.id ? <ChevronUpIcon className={`h-4 w-4 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} /> : <ChevronDownIcon className={`h-4 w-4 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`} />}
                </div>
              </div>

              {/* Edit Section Form */}
              {editingSection === section.id && (
                <div className={`px-5 py-4 border-t ${isDarkMode ? 'border-gray-700/50 bg-gray-800/30' : 'border-gray-100 bg-gray-50/50'}`}>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {[
                      { key: 'title', label: 'Title' },
                      { key: 'subtitle', label: 'Subtitle' },
                      { key: 'badge_text', label: 'Badge Text' },
                      { key: 'image_url', label: 'Image URL' },
                      { key: 'image_alt', label: 'Image Alt' },
                      { key: 'button_primary_text', label: 'Primary Button Text' },
                      { key: 'button_primary_link', label: 'Primary Button Link' },
                      { key: 'button_secondary_text', label: 'Secondary Button Text' },
                      { key: 'button_secondary_link', label: 'Secondary Button Link' },
                    ].map(({ key, label }) => (
                      <div key={key}>
                        <label className={`text-xs font-medium mb-1 block ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{label}</label>
                        <input
                          type="text"
                          value={editingSectionData[key] || ''}
                          onChange={(e) => setEditingSectionData(prev => ({ ...prev, [key]: e.target.value }))}
                          className={inputClass}
                        />
                      </div>
                    ))}
                    <div className="md:col-span-2">
                      <label className={`text-xs font-medium mb-1 block ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Description</label>
                      <textarea
                        value={editingSectionData.description || ''}
                        onChange={(e) => setEditingSectionData(prev => ({ ...prev, description: e.target.value }))}
                        className={`${inputClass} resize-none`}
                        rows={3}
                      />
                    </div>
                  </div>
                  <div className="flex items-center justify-between mt-4">
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={editingSectionData.is_visible}
                        onChange={(e) => setEditingSectionData(prev => ({ ...prev, is_visible: e.target.checked }))}
                        className="rounded"
                      />
                      <span className={`text-sm ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>Visible</span>
                    </label>
                    <div className="flex gap-2">
                      <button
                        onClick={() => setEditingSection(null)}
                        className={`px-3 py-1.5 text-sm rounded-lg border transition-colors ${isDarkMode ? 'border-gray-600 text-gray-400 hover:bg-gray-700' : 'border-gray-300 text-gray-600 hover:bg-gray-100'}`}
                      >
                        Cancel
                      </button>
                      <button
                        onClick={() => saveSection(section.id)}
                        disabled={saving}
                        className={`px-3 py-1.5 text-sm rounded-lg transition-colors flex items-center gap-1 ${
                          isDarkMode ? 'bg-amber-500 text-gray-900 hover:bg-amber-400' : 'bg-blue-500 text-white hover:bg-blue-600'
                        } disabled:opacity-50`}
                      >
                        <CheckIcon className="h-3.5 w-3.5" /> {saving ? 'Saving...' : 'Save'}
                      </button>
                    </div>
                  </div>
                </div>
              )}

              {/* Expanded Items */}
              {expandedSection === section.id && (
                <div className={`border-t ${isDarkMode ? 'border-gray-700/50' : 'border-gray-100'}`}>
                  <div className="px-5 py-3">
                    <div className="flex items-center justify-between mb-3">
                      <h4 className={`text-sm font-medium ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>Items</h4>
                      <button
                        onClick={() => { setAddingItemToSection(section.id); setNewItemData({ title: '', description: '', icon: '', sort_order: (section.all_items || section.items || []).length + 1 }); }}
                        className={`px-2.5 py-1 text-xs rounded-lg flex items-center gap-1 transition-colors ${
                          isDarkMode ? 'bg-amber-500/10 text-amber-400 hover:bg-amber-500/20' : 'bg-blue-50 text-blue-600 hover:bg-blue-100'
                        }`}
                      >
                        <PlusIcon className="h-3.5 w-3.5" /> Add Item
                      </button>
                    </div>

                    {/* Add Item Form */}
                    {addingItemToSection === section.id && (
                      <div className={`mb-3 p-3 rounded-lg border ${isDarkMode ? 'bg-gray-800/40 border-gray-700/40' : 'bg-gray-50 border-gray-200'}`}>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                          <input type="text" placeholder="Title" value={newItemData.title} onChange={(e) => setNewItemData(p => ({ ...p, title: e.target.value }))} className={inputClass} />
                          <input type="text" placeholder="Icon (emoji)" value={newItemData.icon} onChange={(e) => setNewItemData(p => ({ ...p, icon: e.target.value }))} className={inputClass} />
                          <input type="text" placeholder="Description" value={newItemData.description} onChange={(e) => setNewItemData(p => ({ ...p, description: e.target.value }))} className={`${inputClass} sm:col-span-2`} />
                        </div>
                        <div className="flex gap-2 justify-end">
                          <button onClick={() => setAddingItemToSection(null)} className={`px-2.5 py-1 text-xs rounded-lg ${isDarkMode ? 'text-gray-400 hover:bg-gray-700' : 'text-gray-600 hover:bg-gray-100'}`}>Cancel</button>
                          <button onClick={() => addItem(section.id)} disabled={saving || !newItemData.title} className={`px-2.5 py-1 text-xs rounded-lg ${isDarkMode ? 'bg-amber-500 text-gray-900' : 'bg-blue-500 text-white'} disabled:opacity-50`}>{saving ? 'Adding...' : 'Add'}</button>
                        </div>
                      </div>
                    )}

                    {/* Items List */}
                    {(section.all_items || section.items || []).length === 0 ? (
                      <p className={`text-sm text-center py-4 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>No items in this section</p>
                    ) : (
                      <div className="space-y-2">
                        {(section.all_items || section.items || []).map(item => (
                          <div key={item.id} className={`p-3 rounded-lg border ${isDarkMode ? 'bg-gray-800/30 border-gray-700/30' : 'bg-white border-gray-100'}`}>
                            {editingItem === item.id ? (
                              <div>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-2">
                                  <input type="text" value={editingItemData.title} onChange={(e) => setEditingItemData(p => ({ ...p, title: e.target.value }))} className={inputClass} placeholder="Title" />
                                  <input type="text" value={editingItemData.icon} onChange={(e) => setEditingItemData(p => ({ ...p, icon: e.target.value }))} className={inputClass} placeholder="Icon" />
                                  <input type="text" value={editingItemData.description} onChange={(e) => setEditingItemData(p => ({ ...p, description: e.target.value }))} className={`${inputClass} sm:col-span-2`} placeholder="Description" />
                                  <input type="text" value={editingItemData.step_number} onChange={(e) => setEditingItemData(p => ({ ...p, step_number: e.target.value }))} className={inputClass} placeholder="Step #" />
                                  <input type="number" value={editingItemData.sort_order} onChange={(e) => setEditingItemData(p => ({ ...p, sort_order: parseInt(e.target.value) || 0 }))} className={inputClass} placeholder="Sort Order" />
                                </div>
                                <div className="flex gap-2 justify-end">
                                  <button onClick={() => setEditingItem(null)} className={`px-2.5 py-1 text-xs rounded-lg ${isDarkMode ? 'text-gray-400 hover:bg-gray-700' : 'text-gray-600 hover:bg-gray-100'}`}>Cancel</button>
                                  <button onClick={() => saveItem(item.id)} disabled={saving} className={`px-2.5 py-1 text-xs rounded-lg ${isDarkMode ? 'bg-amber-500 text-gray-900' : 'bg-blue-500 text-white'} disabled:opacity-50`}>{saving ? 'Saving...' : 'Save'}</button>
                                </div>
                              </div>
                            ) : (
                              <div className="flex items-center justify-between gap-2">
                                <div className="flex items-center gap-2 min-w-0">
                                  {item.icon && <span className="text-lg flex-shrink-0">{item.icon}</span>}
                                  <div className="min-w-0">
                                    <p className={`text-sm font-medium truncate ${isDarkMode ? 'text-gray-200' : 'text-gray-800'}`}>{item.title || '(no title)'}</p>
                                    {item.description && <p className={`text-xs truncate ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>{item.description}</p>}
                                  </div>
                                </div>
                                <div className="flex items-center gap-1 flex-shrink-0">
                                  <span className={`text-xs ${isDarkMode ? 'text-gray-600' : 'text-gray-300'}`}>#{item.sort_order}</span>
                                  <button onClick={() => startEditItem(item)} className={`p-1 rounded ${isDarkMode ? 'text-gray-500 hover:text-amber-400' : 'text-gray-400 hover:text-blue-500'}`}><PencilIcon className="h-3.5 w-3.5" /></button>
                                  <button onClick={() => deleteItem(item.id)} className={`p-1 rounded ${isDarkMode ? 'text-gray-500 hover:text-red-400' : 'text-gray-400 hover:text-red-500'}`}><TrashIcon className="h-3.5 w-3.5" /></button>
                                </div>
                              </div>
                            )}
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Settings Tab */}
      {activeTab === 'settings' && (
        <div className="space-y-6">
          {Object.entries(settingsByGroup).map(([group, groupSettings]) => (
            <div key={group} className={cardClass}>
              <div className={`px-5 py-3 border-b ${isDarkMode ? 'border-gray-700/50' : 'border-gray-100'}`}>
                <h3 className={`text-sm font-semibold capitalize ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>{group}</h3>
              </div>
              <div className="divide-y divide-gray-700/20">
                {groupSettings.map(setting => (
                  <div key={setting.id} className={`px-5 py-3 flex items-center justify-between gap-4 ${isDarkMode ? 'hover:bg-gray-700/20' : 'hover:bg-gray-50'} transition-colors`}>
                    {editingSetting === setting.id ? (
                      <div className="flex-1 flex items-center gap-2">
                        <div className="flex-1">
                          <label className={`text-xs font-medium mb-0.5 block ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>{setting.label || setting.key}</label>
                          {setting.type === 'boolean' ? (
                            <select value={editingSettingValue} onChange={(e) => setEditingSettingValue(e.target.value)} className={inputClass}>
                              <option value="true">True</option>
                              <option value="false">False</option>
                            </select>
                          ) : setting.value && setting.value.length > 100 ? (
                            <textarea value={editingSettingValue} onChange={(e) => setEditingSettingValue(e.target.value)} className={`${inputClass} resize-none`} rows={3} />
                          ) : (
                            <input type="text" value={editingSettingValue} onChange={(e) => setEditingSettingValue(e.target.value)} className={inputClass} />
                          )}
                        </div>
                        <div className="flex gap-1 flex-shrink-0 self-end">
                          <button onClick={() => setEditingSetting(null)} className={`p-1.5 rounded-lg ${isDarkMode ? 'text-gray-400 hover:bg-gray-700' : 'text-gray-500 hover:bg-gray-100'}`}><XMarkIcon className="h-4 w-4" /></button>
                          <button onClick={() => saveSetting(setting.id)} disabled={saving} className={`p-1.5 rounded-lg ${isDarkMode ? 'text-amber-400 hover:bg-amber-500/10' : 'text-blue-500 hover:bg-blue-50'} disabled:opacity-50`}><CheckIcon className="h-4 w-4" /></button>
                        </div>
                      </div>
                    ) : (
                      <>
                        <div className="min-w-0 flex-1">
                          <p className={`text-sm font-medium ${isDarkMode ? 'text-gray-200' : 'text-gray-800'}`}>{setting.label || setting.key}</p>
                          <p className={`text-xs truncate mt-0.5 ${isDarkMode ? 'text-gray-500' : 'text-gray-400'}`}>
                            {setting.type === 'json' ? '(JSON data)' : (setting.value?.length > 80 ? setting.value.substring(0, 80) + '...' : setting.value || '(empty)')}
                          </p>
                        </div>
                        <div className="flex items-center gap-1 flex-shrink-0">
                          <span className={`text-[10px] px-1.5 py-0.5 rounded ${isDarkMode ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-500'}`}>{setting.type}</span>
                          <button onClick={() => startEditSetting(setting)} className={`p-1.5 rounded-lg ${isDarkMode ? 'text-gray-500 hover:text-amber-400 hover:bg-amber-500/10' : 'text-gray-400 hover:text-blue-500 hover:bg-blue-50'}`}><PencilIcon className="h-4 w-4" /></button>
                        </div>
                      </>
                    )}
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default AdminLandingPageCMS;
