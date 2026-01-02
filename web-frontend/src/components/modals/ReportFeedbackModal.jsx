import { useState } from 'react';

const ReportFeedbackModal = ({ isOpen, onClose, onSubmit, defaultReason = 'spam' }) => {
  const [reason, setReason] = useState(defaultReason);
  const [explanation, setExplanation] = useState('');

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow-xl max-w-lg w-full p-5">
        <h3 className="text-lg font-bold mb-2">Report Feedback</h3>
        <p className="text-sm text-gray-600 dark:text-gray-300 mb-4">Select a reason and optionally add an explanation. This will notify the user.</p>

        <div className="space-y-3">
          <div>
            <label className="text-xs font-medium">Reason</label>
            <select value={reason} onChange={e => setReason(e.target.value)} className="w-full p-2 rounded-md mt-1 bg-gray-50 dark:bg-gray-700">
              <option value="harassment">Harassment</option>
              <option value="hate_speech">Hate Speech</option>
              <option value="spam">Spam</option>
              <option value="threats">Threats</option>
              <option value="false_information">False Information</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div>
            <label className="text-xs font-medium">Explanation (optional)</label>
            <textarea value={explanation} onChange={e => setExplanation(e.target.value)} rows={4} className="w-full p-2 mt-1 rounded-md bg-gray-50 dark:bg-gray-700" />
          </div>

          <div className="flex justify-end space-x-2">
            <button onClick={onClose} className="px-4 py-2 rounded-lg border">Cancel</button>
            <button onClick={() => onSubmit({ reason, explanation })} className="px-4 py-2 rounded-lg bg-red-600 text-white">Report</button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ReportFeedbackModal;
