import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

// ─── Icons (inline SVGs to avoid heroicon import weight) ─────────────────────
const Icons = {
  back: (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
    </svg>
  ),
  save: (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
    </svg>
  ),
  refresh: (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
    </svg>
  ),
  eye: (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
  ),
  eyeOff: (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
    </svg>
  ),
  edit: (
    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
    </svg>
  ),
  trash: (
    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
  ),
  plus: (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
    </svg>
  ),
  x: (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
    </svg>
  ),
  globe: (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
    </svg>
  ),
  settings: (
    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  ),
  menu: (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
    </svg>
  ),
};

const SECTION_LABELS = {
  hero: 'Hero Banner',
  stats: 'Statistics',
  services: 'Services',
  how_it_works: 'How It Works',
  testimonials: 'Testimonials',
  cta: 'Call to Action',
  chatbot: 'Chatbot',
};

const SETTING_GROUP_LABELS = {
  branding: 'Branding',
  footer: 'Footer',
  seo: 'SEO',
  navigation: 'Navigation',
  chatbot: 'Chatbot',
  feedback: 'Feedback',
};

const LandingPageCMS = () => {
  const navigate = useNavigate();
  const [isDarkMode] = useState(() => localStorage.getItem('theme') !== 'light');
  const [sections, setSections] = useState([]);
  const [settings, setSettings] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(null); // Track which entity is saving
  const [toast, setToast] = useState(null);
  const [activeView, setActiveView] = useState('sections'); // 'sections' | 'settings'
  const [selectedSection, setSelectedSection] = useState(null);
  const [selectedGroup, setSelectedGroup] = useState(null);
  const [mobileSidebar, setMobileSidebar] = useState(false);

  // Editing state
  const [editField, setEditField] = useState(null); // { type, id, field }
  const [editValue, setEditValue] = useState('');
  const [editingItem, setEditingItem] = useState(null);
  const [editingItemData, setEditingItemData] = useState({});
  const [addingItem, setAddingItem] = useState(false);
  const [newItem, setNewItem] = useState({ title: '', description: '', icon: '' });

  const showToast = (msg, type = 'success') => {
    setToast({ msg, type });
    setTimeout(() => setToast(null), 3000);
  };

  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      const [secRes, setRes] = await Promise.all([
        axios.get('/api/admin/landing-page/sections'),
        axios.get('/api/admin/landing-page/settings'),
      ]);
      const secData = secRes.data.data || [];
      const setData = setRes.data.data || [];
      setSections(secData);
      setSettings(setData);
      // Auto-select first section
      if (secData.length > 0 && !selectedSection) {
        setSelectedSection(secData[0].id);
      }
      // Auto-select first settings group
      if (setData.length > 0 && !selectedGroup) {
        const firstGroup = setData[0]?.group || 'general';
        setSelectedGroup(firstGroup);
      }
    } catch (err) {
      showToast(err.response?.data?.message || 'Failed to load data', 'error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);

  // ── Section operations ─────────────────────────────────────────────────────
  const saveField = async (sectionId, field, value) => {
    try {
      setSaving(`section-${sectionId}-${field}`);
      await axios.put(`/api/admin/landing-page/sections/${sectionId}`, { [field]: value });
      setSections(prev => prev.map(s => s.id === sectionId ? { ...s, [field]: value } : s));
      setEditField(null);
      showToast('Saved');
    } catch (err) {
      showToast(err.response?.data?.messages ? Object.values(err.response.data.messages).flat().join(', ') : 'Save failed', 'error');
    } finally {
      setSaving(null);
    }
  };

  const toggleVisibility = async (section) => {
    try {
      const newVal = !section.is_visible;
      await axios.put(`/api/admin/landing-page/sections/${section.id}`, { is_visible: newVal });
      setSections(prev => prev.map(s => s.id === section.id ? { ...s, is_visible: newVal } : s));
      showToast(newVal ? 'Section visible' : 'Section hidden');
    } catch {
      showToast('Failed to toggle', 'error');
    }
  };

  // ── Item operations ────────────────────────────────────────────────────────
  const saveItemEdit = async () => {
    if (!editingItem) return;
    try {
      setSaving(`item-${editingItem}`);
      await axios.put(`/api/admin/landing-page/items/${editingItem}`, editingItemData);
      fetchData();
      setEditingItem(null);
      showToast('Item updated');
    } catch (err) {
      showToast(err.response?.data?.messages ? Object.values(err.response.data.messages).flat().join(', ') : 'Failed to save item', 'error');
    } finally {
      setSaving(null);
    }
  };

  const deleteItem = async (itemId) => {
    if (!window.confirm('Delete this item?')) return;
    try {
      await axios.delete(`/api/admin/landing-page/items/${itemId}`);
      fetchData();
      showToast('Item deleted');
    } catch {
      showToast('Delete failed', 'error');
    }
  };

  const createItem = async (sectionId) => {
    if (!newItem.title.trim()) return;
    try {
      setSaving('new-item');
      const items = currentSection?.all_items || currentSection?.items || [];
      await axios.post(`/api/admin/landing-page/sections/${sectionId}/items`, {
        ...newItem,
        sort_order: items.length + 1,
      });
      setAddingItem(false);
      setNewItem({ title: '', description: '', icon: '' });
      fetchData();
      showToast('Item added');
    } catch (err) {
      showToast(err.response?.data?.messages ? Object.values(err.response.data.messages).flat().join(', ') : 'Failed to add item', 'error');
    } finally {
      setSaving(null);
    }
  };

  // ── Settings operations ────────────────────────────────────────────────────
  const saveSetting = async (id, value) => {
    try {
      setSaving(`setting-${id}`);
      await axios.put(`/api/admin/landing-page/settings/${id}`, { value });
      setSettings(prev => prev.map(s => s.id === id ? { ...s, value } : s));
      setEditField(null);
      showToast('Setting saved');
    } catch {
      showToast('Failed to save setting', 'error');
    } finally {
      setSaving(null);
    }
  };

  // ── Derived data ───────────────────────────────────────────────────────────
  const currentSection = sections.find(s => s.id === selectedSection);
  const settingsByGroup = settings.reduce((acc, s) => {
    const g = s.group || 'general';
    if (!acc[g]) acc[g] = [];
    acc[g].push(s);
    return acc;
  }, {});
  const settingGroups = Object.keys(settingsByGroup);
  const currentGroupSettings = settingsByGroup[selectedGroup] || [];

  // ── Style helpers ──────────────────────────────────────────────────────────
  const dark = isDarkMode;
  const bg = dark ? 'bg-gray-950' : 'bg-gray-50';
  const cardBg = dark ? 'bg-gray-900' : 'bg-white';
  const borderColor = dark ? 'border-gray-800' : 'border-gray-200';
  const textPrimary = dark ? 'text-gray-100' : 'text-gray-900';
  const textSecondary = dark ? 'text-gray-400' : 'text-gray-500';
  const textMuted = dark ? 'text-gray-500' : 'text-gray-400';
  const accent = dark ? 'text-amber-400' : 'text-blue-600';
  const accentBg = dark ? 'bg-amber-500/10' : 'bg-blue-50';
  const accentBorder = dark ? 'border-amber-500/30' : 'border-blue-200';
  const inputCls = `w-full px-3 py-2 rounded-lg text-sm border outline-none transition-colors ${
    dark ? 'bg-gray-800 border-gray-700 text-gray-200 focus:border-amber-500' : 'bg-white border-gray-300 text-gray-800 focus:border-blue-500'
  }`;

  if (loading) {
    return (
      <div className={`min-h-screen ${bg} flex items-center justify-center`}>
        <div className="flex flex-col items-center gap-3">
          <div className={`animate-spin ${accent}`}>{Icons.refresh}</div>
          <p className={`text-sm ${textSecondary}`}>Loading...</p>
        </div>
      </div>
    );
  }

  // ── Sidebar content ────────────────────────────────────────────────────────
  const renderSidebar = () => (
    <div className="flex flex-col h-full">
      {/* View Toggle */}
      <div className={`p-3 border-b ${borderColor}`}>
        <div className={`flex rounded-lg ${dark ? 'bg-gray-800' : 'bg-gray-100'} p-0.5`}>
          <button
            onClick={() => { setActiveView('sections'); setMobileSidebar(false); }}
            className={`flex-1 text-xs font-medium py-1.5 rounded-md transition-colors ${
              activeView === 'sections' ? `${cardBg} ${accent} shadow-sm` : textSecondary
            }`}
          >
            Sections
          </button>
          <button
            onClick={() => { setActiveView('settings'); setMobileSidebar(false); }}
            className={`flex-1 text-xs font-medium py-1.5 rounded-md transition-colors ${
              activeView === 'settings' ? `${cardBg} ${accent} shadow-sm` : textSecondary
            }`}
          >
            Settings
          </button>
        </div>
      </div>

      {/* Nav items */}
      <nav className="flex-1 overflow-y-auto p-2 space-y-0.5">
        {activeView === 'sections' ? (
          sections.map(section => (
            <button
              key={section.id}
              onClick={() => { setSelectedSection(section.id); setMobileSidebar(false); }}
              className={`w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors flex items-center gap-2 ${
                selectedSection === section.id
                  ? `${accentBg} ${accent} font-medium`
                  : `${dark ? 'hover:bg-gray-800 text-gray-300' : 'hover:bg-gray-100 text-gray-700'}`
              }`}
            >
              <span className={`w-1.5 h-1.5 rounded-full flex-shrink-0 ${section.is_visible ? 'bg-green-500' : 'bg-gray-500'}`} />
              <span className="truncate">{SECTION_LABELS[section.section_key] || section.title || section.section_key}</span>
            </button>
          ))
        ) : (
          settingGroups.map(group => (
            <button
              key={group}
              onClick={() => { setSelectedGroup(group); setMobileSidebar(false); }}
              className={`w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors flex items-center gap-2 ${
                selectedGroup === group
                  ? `${accentBg} ${accent} font-medium`
                  : `${dark ? 'hover:bg-gray-800 text-gray-300' : 'hover:bg-gray-100 text-gray-700'}`
              }`}
            >
              <span className="flex-shrink-0">{Icons.settings}</span>
              <span className="truncate capitalize">{SETTING_GROUP_LABELS[group] || group}</span>
              <span className={`ml-auto text-xs ${textMuted}`}>{settingsByGroup[group].length}</span>
            </button>
          ))
        )}
      </nav>
    </div>
  );

  // ── Inline field editor ────────────────────────────────────────────────────
  const InlineField = ({ label, value, fieldKey, sectionId, multiline = false }) => {
    const isEditing = editField?.type === 'section' && editField?.id === sectionId && editField?.field === fieldKey;
    const isSaving = saving === `section-${sectionId}-${fieldKey}`;

    if (isEditing) {
      return (
        <div className="space-y-1.5">
          <label className={`text-xs font-medium ${textSecondary}`}>{label}</label>
          {multiline ? (
            <textarea value={editValue} onChange={(e) => setEditValue(e.target.value)} className={`${inputCls} resize-none`} rows={3} autoFocus />
          ) : (
            <input type="text" value={editValue} onChange={(e) => setEditValue(e.target.value)} className={inputCls} autoFocus
              onKeyDown={(e) => { if (e.key === 'Enter') saveField(sectionId, fieldKey, editValue); if (e.key === 'Escape') setEditField(null); }}
            />
          )}
          <div className="flex gap-1.5">
            <button onClick={() => saveField(sectionId, fieldKey, editValue)} disabled={isSaving}
              className={`px-2.5 py-1 text-xs rounded-md ${dark ? 'bg-amber-500 text-gray-900' : 'bg-blue-500 text-white'} disabled:opacity-50`}
            >{isSaving ? '...' : 'Save'}</button>
            <button onClick={() => setEditField(null)} className={`px-2.5 py-1 text-xs rounded-md ${dark ? 'text-gray-400 hover:bg-gray-800' : 'text-gray-600 hover:bg-gray-100'}`}>Cancel</button>
          </div>
        </div>
      );
    }

    return (
      <div className="group">
        <label className={`text-xs font-medium ${textSecondary}`}>{label}</label>
        <div
          className={`mt-0.5 px-3 py-2 rounded-lg text-sm cursor-pointer border border-transparent transition-colors ${
            dark ? 'hover:bg-gray-800 hover:border-gray-700' : 'hover:bg-gray-50 hover:border-gray-200'
          } ${textPrimary} ${!value ? `italic ${textMuted}` : ''}`}
          onClick={() => { setEditField({ type: 'section', id: sectionId, field: fieldKey }); setEditValue(value || ''); }}
        >
          {value || `Click to set ${label.toLowerCase()}`}
          <span className={`ml-2 opacity-0 group-hover:opacity-100 inline-block transition-opacity ${accent}`}>{Icons.edit}</span>
        </div>
      </div>
    );
  };

  // ── Sections detail view ───────────────────────────────────────────────────
  const renderSectionDetail = () => {
    if (!currentSection) {
      return <div className={`text-center py-20 ${textMuted}`}>Select a section from the sidebar</div>;
    }

    const items = currentSection.all_items || currentSection.items || [];
    const sectionLabel = SECTION_LABELS[currentSection.section_key] || currentSection.title;

    return (
      <div className="space-y-6">
        {/* Section header */}
        <div className="flex items-center justify-between">
          <div>
            <h2 className={`text-lg font-bold ${textPrimary}`}>{sectionLabel}</h2>
            <p className={`text-xs ${textMuted} mt-0.5`}>Key: {currentSection.section_key} &bull; Sort order: {currentSection.sort_order}</p>
          </div>
          <button
            onClick={() => toggleVisibility(currentSection)}
            className={`flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border transition-colors ${
              currentSection.is_visible
                ? (dark ? 'border-green-500/30 text-green-400 hover:bg-green-500/10' : 'border-green-300 text-green-600 hover:bg-green-50')
                : (dark ? 'border-gray-700 text-gray-500 hover:bg-gray-800' : 'border-gray-300 text-gray-400 hover:bg-gray-100')
            }`}
          >
            {currentSection.is_visible ? Icons.eye : Icons.eyeOff}
            {currentSection.is_visible ? 'Visible' : 'Hidden'}
          </button>
        </div>

        {/* Content fields */}
        <div className={`${cardBg} rounded-xl border ${borderColor} p-5`}>
          <h3 className={`text-xs font-semibold uppercase tracking-wide mb-4 ${textMuted}`}>Content</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <InlineField label="Title" value={currentSection.title} fieldKey="title" sectionId={currentSection.id} />
            <InlineField label="Subtitle" value={currentSection.subtitle} fieldKey="subtitle" sectionId={currentSection.id} />
            <InlineField label="Badge Text" value={currentSection.badge_text} fieldKey="badge_text" sectionId={currentSection.id} />
            <InlineField label="Image URL" value={currentSection.image_url} fieldKey="image_url" sectionId={currentSection.id} />
            <div className="md:col-span-2">
              <InlineField label="Description" value={currentSection.description} fieldKey="description" sectionId={currentSection.id} multiline />
            </div>
          </div>
        </div>

        {/* Buttons (only if section has them) */}
        {(currentSection.button_primary_text || currentSection.button_secondary_text || currentSection.section_key === 'hero' || currentSection.section_key === 'cta') && (
          <div className={`${cardBg} rounded-xl border ${borderColor} p-5`}>
            <h3 className={`text-xs font-semibold uppercase tracking-wide mb-4 ${textMuted}`}>Buttons</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <InlineField label="Primary Button Text" value={currentSection.button_primary_text} fieldKey="button_primary_text" sectionId={currentSection.id} />
              <InlineField label="Primary Button Link" value={currentSection.button_primary_link} fieldKey="button_primary_link" sectionId={currentSection.id} />
              <InlineField label="Secondary Button Text" value={currentSection.button_secondary_text} fieldKey="button_secondary_text" sectionId={currentSection.id} />
              <InlineField label="Secondary Button Link" value={currentSection.button_secondary_link} fieldKey="button_secondary_link" sectionId={currentSection.id} />
            </div>
          </div>
        )}

        {/* Items */}
        <div className={`${cardBg} rounded-xl border ${borderColor} p-5`}>
          <div className="flex items-center justify-between mb-4">
            <h3 className={`text-xs font-semibold uppercase tracking-wide ${textMuted}`}>Items ({items.length})</h3>
            <button
              onClick={() => { setAddingItem(true); setNewItem({ title: '', description: '', icon: '' }); }}
              className={`flex items-center gap-1 px-2.5 py-1.5 text-xs rounded-lg transition-colors ${
                dark ? 'bg-amber-500/10 text-amber-400 hover:bg-amber-500/20' : 'bg-blue-50 text-blue-600 hover:bg-blue-100'
              }`}
            >
              {Icons.plus} Add
            </button>
          </div>

          {/* Add Item Form */}
          {addingItem && (
            <div className={`mb-4 p-4 rounded-lg border ${dark ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-200'}`}>
              <div className="space-y-2">
                <input type="text" placeholder="Title" value={newItem.title} onChange={(e) => setNewItem(p => ({ ...p, title: e.target.value }))} className={inputCls} autoFocus />
                <input type="text" placeholder="Description" value={newItem.description} onChange={(e) => setNewItem(p => ({ ...p, description: e.target.value }))} className={inputCls} />
                <input type="text" placeholder="Icon (emoji)" value={newItem.icon} onChange={(e) => setNewItem(p => ({ ...p, icon: e.target.value }))} className={inputCls} />
              </div>
              <div className="flex gap-2 mt-3">
                <button onClick={() => createItem(currentSection.id)} disabled={saving === 'new-item' || !newItem.title.trim()}
                  className={`px-3 py-1.5 text-xs rounded-lg ${dark ? 'bg-amber-500 text-gray-900' : 'bg-blue-500 text-white'} disabled:opacity-50`}
                >{saving === 'new-item' ? 'Adding...' : 'Add Item'}</button>
                <button onClick={() => setAddingItem(false)} className={`px-3 py-1.5 text-xs rounded-lg ${dark ? 'text-gray-400 hover:bg-gray-700' : 'text-gray-600 hover:bg-gray-100'}`}>Cancel</button>
              </div>
            </div>
          )}

          {items.length === 0 ? (
            <p className={`text-sm text-center py-6 ${textMuted}`}>No items in this section</p>
          ) : (
            <div className="space-y-2">
              {items.map(item => (
                <div key={item.id} className={`rounded-lg border p-3 transition-colors ${dark ? 'border-gray-800 hover:border-gray-700' : 'border-gray-100 hover:border-gray-200'}`}>
                  {editingItem === item.id ? (
                    <div className="space-y-2">
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <input type="text" value={editingItemData.title || ''} onChange={(e) => setEditingItemData(p => ({ ...p, title: e.target.value }))} className={inputCls} placeholder="Title" />
                        <input type="text" value={editingItemData.icon || ''} onChange={(e) => setEditingItemData(p => ({ ...p, icon: e.target.value }))} className={inputCls} placeholder="Icon" />
                      </div>
                      <input type="text" value={editingItemData.description || ''} onChange={(e) => setEditingItemData(p => ({ ...p, description: e.target.value }))} className={inputCls} placeholder="Description" />
                      <div className="flex gap-2">
                        <button onClick={saveItemEdit} disabled={saving === `item-${item.id}`}
                          className={`px-2.5 py-1 text-xs rounded-md ${dark ? 'bg-amber-500 text-gray-900' : 'bg-blue-500 text-white'} disabled:opacity-50`}
                        >{saving === `item-${item.id}` ? '...' : 'Save'}</button>
                        <button onClick={() => setEditingItem(null)} className={`px-2.5 py-1 text-xs rounded-md ${dark ? 'text-gray-400' : 'text-gray-600'}`}>Cancel</button>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-center gap-3">
                      {item.icon && <span className="text-lg flex-shrink-0">{item.icon}</span>}
                      <div className="flex-1 min-w-0">
                        <p className={`text-sm font-medium ${textPrimary}`}>{item.title || '(untitled)'}</p>
                        {item.description && <p className={`text-xs ${textMuted} truncate`}>{item.description}</p>}
                      </div>
                      <div className="flex items-center gap-1 flex-shrink-0">
                        <button onClick={() => { setEditingItem(item.id); setEditingItemData({ title: item.title || '', description: item.description || '', icon: item.icon || '', step_number: item.step_number || '', sort_order: item.sort_order || 0 }); }}
                          className={`p-1.5 rounded-md transition-colors ${dark ? 'text-gray-500 hover:text-amber-400 hover:bg-gray-800' : 'text-gray-400 hover:text-blue-500 hover:bg-gray-50'}`}
                        >{Icons.edit}</button>
                        <button onClick={() => deleteItem(item.id)}
                          className={`p-1.5 rounded-md transition-colors ${dark ? 'text-gray-500 hover:text-red-400 hover:bg-gray-800' : 'text-gray-400 hover:text-red-500 hover:bg-gray-50'}`}
                        >{Icons.trash}</button>
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    );
  };

  // ── Settings detail view ───────────────────────────────────────────────────
  const renderSettingsDetail = () => {
    if (currentGroupSettings.length === 0) {
      return <div className={`text-center py-20 ${textMuted}`}>Select a settings group from the sidebar</div>;
    }

    return (
      <div className="space-y-6">
        <h2 className={`text-lg font-bold ${textPrimary} capitalize`}>{SETTING_GROUP_LABELS[selectedGroup] || selectedGroup} Settings</h2>

        <div className={`${cardBg} rounded-xl border ${borderColor} divide-y ${dark ? 'divide-gray-800' : 'divide-gray-100'}`}>
          {currentGroupSettings.map(setting => {
            const isEditing = editField?.type === 'setting' && editField?.id === setting.id;
            const isSaving = saving === `setting-${setting.id}`;

            return (
              <div key={setting.id} className={`px-5 py-4`}>
                {isEditing ? (
                  <div className="space-y-2">
                    <label className={`text-xs font-medium ${textSecondary}`}>{setting.label || setting.key}</label>
                    {setting.type === 'boolean' ? (
                      <select value={editValue} onChange={(e) => setEditValue(e.target.value)} className={inputCls}>
                        <option value="true">Enabled</option>
                        <option value="false">Disabled</option>
                      </select>
                    ) : (setting.value && setting.value.length > 80) || setting.type === 'json' ? (
                      <textarea value={editValue} onChange={(e) => setEditValue(e.target.value)} className={`${inputCls} resize-none font-mono`} rows={4} />
                    ) : (
                      <input type="text" value={editValue} onChange={(e) => setEditValue(e.target.value)} className={inputCls} autoFocus
                        onKeyDown={(e) => { if (e.key === 'Enter') saveSetting(setting.id, editValue); if (e.key === 'Escape') setEditField(null); }}
                      />
                    )}
                    <div className="flex gap-1.5">
                      <button onClick={() => saveSetting(setting.id, editValue)} disabled={isSaving}
                        className={`px-2.5 py-1 text-xs rounded-md ${dark ? 'bg-amber-500 text-gray-900' : 'bg-blue-500 text-white'} disabled:opacity-50`}
                      >{isSaving ? '...' : 'Save'}</button>
                      <button onClick={() => setEditField(null)} className={`px-2.5 py-1 text-xs rounded-md ${dark ? 'text-gray-400 hover:bg-gray-800' : 'text-gray-600 hover:bg-gray-100'}`}>Cancel</button>
                    </div>
                  </div>
                ) : (
                  <div
                    className={`group cursor-pointer`}
                    onClick={() => { setEditField({ type: 'setting', id: setting.id }); setEditValue(setting.value || ''); }}
                  >
                    <div className="flex items-center justify-between">
                      <span className={`text-sm font-medium ${textPrimary}`}>{setting.label || setting.key}</span>
                      <div className="flex items-center gap-2">
                        <span className={`text-[10px] px-1.5 py-0.5 rounded ${dark ? 'bg-gray-800 text-gray-500' : 'bg-gray-100 text-gray-400'}`}>{setting.type}</span>
                        <span className={`opacity-0 group-hover:opacity-100 transition-opacity ${accent}`}>{Icons.edit}</span>
                      </div>
                    </div>
                    <p className={`text-xs mt-1 ${textMuted} ${!setting.value ? 'italic' : ''}`}>
                      {setting.type === 'json'
                        ? '(click to edit JSON)'
                        : setting.type === 'boolean'
                        ? (setting.value === 'true' ? '✓ Enabled' : '✗ Disabled')
                        : setting.value?.length > 100
                        ? setting.value.substring(0, 100) + '...'
                        : setting.value || '(empty)'}
                    </p>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>
    );
  };

  // ── Main layout ────────────────────────────────────────────────────────────
  return (
    <div className={`min-h-screen ${bg}`}>
      {/* Toast notification */}
      {toast && (
        <div className={`fixed top-4 right-4 z-50 px-4 py-2.5 rounded-xl text-sm shadow-lg flex items-center gap-2 animate-in fade-in slide-in-from-top-2 ${
          toast.type === 'error'
            ? 'bg-red-500/90 text-white'
            : dark ? 'bg-green-500/90 text-white' : 'bg-green-600 text-white'
        }`}>
          {toast.type === 'error' ? Icons.x : Icons.save}
          {toast.msg}
        </div>
      )}

      {/* Top bar */}
      <header className={`sticky top-0 z-30 ${cardBg} border-b ${borderColor} backdrop-blur-xl`}>
        <div className="flex items-center justify-between px-4 sm:px-6 py-3">
          <div className="flex items-center gap-3">
            {/* Mobile sidebar toggle */}
            <button onClick={() => setMobileSidebar(!mobileSidebar)} className={`lg:hidden p-1.5 rounded-lg ${dark ? 'text-gray-400 hover:bg-gray-800' : 'text-gray-500 hover:bg-gray-100'}`}>
              {Icons.menu}
            </button>
            <button
              onClick={() => navigate('/admin/dashboard')}
              className={`flex items-center gap-2 text-sm font-medium transition-colors ${dark ? 'text-gray-400 hover:text-amber-400' : 'text-gray-500 hover:text-blue-600'}`}
            >
              {Icons.back}
              <span className="hidden sm:inline">Back to Admin</span>
            </button>
          </div>
          <div className="flex items-center gap-2">
            <span className={`${accent} flex-shrink-0`}>{Icons.globe}</span>
            <h1 className={`text-sm font-bold ${textPrimary} hidden sm:block`}>Landing Page Editor</h1>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={fetchData}
              className={`p-2 rounded-lg transition-colors ${dark ? 'text-gray-400 hover:bg-gray-800 hover:text-gray-200' : 'text-gray-500 hover:bg-gray-100'}`}
              title="Refresh"
            >{Icons.refresh}</button>
            <a
              href="/"
              target="_blank"
              rel="noopener noreferrer"
              className={`flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-lg border transition-colors ${
                dark ? `${accentBorder} ${accent} hover:bg-amber-500/10` : 'border-blue-200 text-blue-600 hover:bg-blue-50'
              }`}
            >
              {Icons.eye} Preview
            </a>
          </div>
        </div>
      </header>

      <div className="flex h-[calc(100vh-53px)]">
        {/* Desktop sidebar */}
        <aside className={`hidden lg:flex flex-col w-56 flex-shrink-0 border-r ${borderColor} ${cardBg}`}>
          {renderSidebar()}
        </aside>

        {/* Mobile sidebar overlay */}
        {mobileSidebar && (
          <>
            <div className="fixed inset-0 z-40 bg-black/40 lg:hidden" onClick={() => setMobileSidebar(false)} />
            <aside className={`fixed inset-y-0 left-0 z-50 w-64 ${cardBg} border-r ${borderColor} lg:hidden pt-14`}>
              {renderSidebar()}
            </aside>
          </>
        )}

        {/* Main content */}
        <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
          <div className="max-w-3xl mx-auto">
            {activeView === 'sections' ? renderSectionDetail() : renderSettingsDetail()}
          </div>
        </main>
      </div>
    </div>
  );
};

export default LandingPageCMS;
