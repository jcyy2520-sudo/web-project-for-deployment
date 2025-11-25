import { useState, useCallback } from 'react';
import { useApi } from './useApi';

export const useArchiveSystem = () => {
  const { callApi } = useApi();
  const [archivedItems, setArchivedItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loaded, setLoaded] = useState(false);

  const loadArchivedItems = useCallback(async () => {
    if (loaded) return;
    
    setLoading(true);
    const result = await callApi((signal) => axios.get('/api/archive', { signal }));
    
    if (result.success) {
      setArchivedItems(result.data);
      setLoaded(true);
    } else {
      console.error('Failed to load archive:', result.error);
    }
    setLoading(false);
  }, [callApi, loaded]);

  const restoreItem = useCallback(async (itemId, itemType) => {
    const result = await callApi((signal) => axios.post('/api/archive/restore', { item_id: itemId, item_type: itemType }, { signal }));

    if (result.success) {
      setLoaded(false);
      await loadArchivedItems();
      return { success: true };
    }
    return { success: false, error: result.error };
  }, [callApi, loadArchivedItems]);

  const permanentDelete = useCallback(async (itemId, itemType) => {
    const result = await callApi((signal) => axios.delete(`/api/archive/${itemId}`, { data: { item_type: itemType }, signal }));

    if (result.success) {
      setLoaded(false);
      await loadArchivedItems();
      return { success: true };
    }
    return { success: false, error: result.error };
  }, [callApi, loadArchivedItems]);

  const refreshArchive = useCallback(() => {
    setLoaded(false);
  }, []);

  return {
    archivedItems,
    loading,
    restoreItem,
    permanentDelete,
    refreshArchive,
    loadArchivedItems
  };
};