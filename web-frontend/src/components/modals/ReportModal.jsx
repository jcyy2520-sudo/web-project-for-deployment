import { useEffect, useState } from 'react';
import { XMarkIcon, DocumentArrowDownIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

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

export default ReportModal;
