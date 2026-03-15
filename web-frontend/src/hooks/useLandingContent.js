import { useState, useEffect } from 'react';
import axios from 'axios';
import logger from '../utils/logger';

/**
 * Hook to fetch landing page CMS content from the backend.
 * Falls back to embedded defaults if API is unavailable.
 */
const useLandingContent = () => {
  const [sections, setSections] = useState({});
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let isMounted = true;

    const fetchContent = async () => {
      try {
        const response = await axios.get('/api/landing-page', { timeout: 5000 });
        if (isMounted && response.data?.data) {
          setSections(response.data.data.sections || {});
          setSettings(response.data.data.settings || {});
        }
      } catch (err) {
        logger.debug('Landing page CMS unavailable, using defaults');
      } finally {
        if (isMounted) setLoading(false);
      }
    };

    fetchContent();
    return () => { isMounted = false; };
  }, []);

  // Helper: get a section by key with defaults
  const getSection = (key, defaults = {}) => {
    const section = sections[key];
    if (!section) return { ...defaults, items: [] };
    return {
      ...defaults,
      ...section,
      items: section.items || [],
    };
  };

  // Helper: get a setting value
  const getSetting = (key, defaultValue = '') => {
    // Settings are grouped, search across all groups
    for (const group of Object.values(settings)) {
      if (group && typeof group === 'object' && key in group) {
        return group[key];
      }
    }
    return defaultValue;
  };

  // Helper: get a settings group
  const getSettingsGroup = (group) => {
    return settings[group] || {};
  };

  return {
    sections,
    settings,
    loading,
    getSection,
    getSetting,
    getSettingsGroup,
  };
};

export default useLandingContent;
