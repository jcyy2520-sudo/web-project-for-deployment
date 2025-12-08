import React from 'react';

const ChatbotMessage = ({ message }) => {
  const isUser = message.role === 'user';

  return (
    <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
      <div
        className={`max-w-[75%] rounded-xl px-4 py-3 ${
          isUser
            ? 'bg-amber-500 text-white shadow-lg shadow-amber-500/20'
            : 'bg-gray-900 text-gray-100 border border-amber-500/20'
        }`}
      >
        <div>
          <p className="text-sm leading-relaxed break-words">{message.message}</p>

          {/* Render structured appointment card when present */}
          {message.meta && message.meta.data && message.meta.data.next_appointment && (
            <div className="mt-3 p-3 bg-gray-800 border border-amber-500/10 rounded-lg">
              <div className="text-sm text-gray-300 font-semibold">Next appointment</div>
              <div className="mt-2 text-sm text-gray-200">
                <div><strong>Date:</strong> {message.meta.data.next_appointment.date}</div>
                <div><strong>Time:</strong> {message.meta.data.next_appointment.time}</div>
                <div><strong>Service:</strong> {message.meta.data.next_appointment.service}</div>
                <div><strong>Status:</strong> {message.meta.data.next_appointment.status}</div>
              </div>
              <div className="mt-3 flex gap-2">
                <button className="px-3 py-1 bg-amber-500 text-black rounded">Reschedule</button>
                <button className="px-3 py-1 border border-amber-500 text-amber-300 rounded">Cancel</button>
              </div>
            </div>
          )}

          <span className={`text-xs mt-2 block ${isUser ? 'text-amber-100/70' : 'text-gray-400'}`}>
            {new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
          </span>
        </div>
      </div>
    </div>
  );
};

export default ChatbotMessage;
