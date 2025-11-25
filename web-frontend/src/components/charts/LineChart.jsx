import { useMemo } from 'react';
import { ChartBarIcon } from '@heroicons/react/24/outline';

const LineChart = ({ data, title, color = 'amber' }) => {
  const safeData = useMemo(() => 
    data.map(item => ({ ...item, value: Number(item.value) || 0 })), 
    [data]
  );
  const maxValue = Math.max(...safeData.map(item => item.value), 1);
  const points = safeData.map((item, index) => {
    const x = (index / (safeData.length - 1)) * 100;
    const y = 100 - (item.value / maxValue) * 100;
    return `${x},${y}`;
  }).join(' ');

  return (
    <div className="bg-gray-900 border border-amber-500/20 rounded-lg shadow p-4 hover:border-amber-500/40 transition-all duration-300">
      <h3 className="text-sm font-semibold text-amber-50 mb-3 flex items-center">
        <ChartBarIcon className="h-4 w-4 mr-2" />
        {title}
      </h3>
      <div className="relative h-36">
        <svg viewBox="0 0 100 100" className="w-full h-full">
          {[0, 25, 50, 75, 100].map((y) => (
            <line
              key={y}
              x1="0"
              y1={y}
              x2="100"
              y2={y}
              stroke="#374151"
              strokeWidth="0.5"
            />
          ))}
          <polyline
            fill="none"
            stroke="url(#gradient-amber)"
            strokeWidth="2"
            points={points}
            className="animate-draw"
          />
          {safeData.map((item, index) => {
            const x = (index / (safeData.length - 1)) * 100;
            const y = 100 - (item.value / maxValue) * 100;
            return (
              <circle
                key={index}
                cx={x}
                cy={y}
                r="1.5"
                fill="#f59e0b"
                className="hover:r-2 transition-all duration-200 cursor-pointer"
              />
            );
          })}
          <defs>
            <linearGradient id="gradient-amber" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stopColor="#f59e0b" stopOpacity="0.7" />
              <stop offset="100%" stopColor="#f59e0b" stopOpacity="0.3" />
            </linearGradient>
          </defs>
        </svg>
        <div className="absolute bottom-0 left-0 right-0 flex justify-between text-xs text-gray-400">
          {safeData.map((item, index) => (
            <span key={index}>{item.label}</span>
          ))}
        </div>
      </div>
    </div>
  );
};

export default LineChart;
