/**
 * Pure-logic hook for accessing landing page CMS content.
 * Data is provided by the caller (fetched via /api/public/init).
 */
const useLandingContent = (sections = {}, settings = {}) => {
  const getSection = (key, defaults = {}) => {
    const section = sections[key];
    if (!section) return { ...defaults, items: [] };
    return {
      ...defaults,
      ...section,
      items: section.items || [],
    };
  };

  const getSetting = (key, defaultValue = '') => {
    for (const group of Object.values(settings)) {
      if (group && typeof group === 'object' && key in group) {
        return group[key];
      }
    }
    return defaultValue;
  };

  const getSettingsGroup = (group) => {
    return settings[group] || {};
  };

  return { getSection, getSetting, getSettingsGroup };
};

export default useLandingContent;
