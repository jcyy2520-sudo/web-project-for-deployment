import { useMemo } from 'react';
import { ChartBarIcon } from '@heroicons/react/24/outline';

const BarChart = ({ data, title, color = 'amber', height = 160 }) => {
  const safeData = useMemo(() => 
    data.map(item => ({ ...item, value: Number(item.value) || 0 })), 
    [data]
  );
  const maxValue = Math.max(...safeData.map(item => item.value), 1);
  
  return (
    <div className="bg-gray-900 border border-amber-500/20 rounded-lg shadow p-4 hover:border-amber-500/40 transition-all duration-300">
      <h3 className="text-sm font-semibold text-amber-50 mb-3 flex items-center">
        <ChartBarIcon className="h-4 w-4 mr-2" />
        {title}
      </h3>
      <div className="space-y-2" style={{ height: `${height}px` }}>
        {safeData.map((item, index) => (
          <div key={index} className="flex items-center justify-between group">
            <span className="text-xs text-gray-300 w-16 truncate group-hover:text-amber-200 transition-colors">
              {item.label}
            </span>
            <div className="flex-1 mx-2">
              <div 
                className="h-4 rounded-md bg-gradient-to-r from-amber-500 to-amber-600 transition-all duration-500 group-hover:from-amber-400 group-hover:to-amber-500 shadow group-hover:shadow-amber-500/25 relative overflow-hidden"
                style={{ 
                  width: `${(item.value / maxValue) * 100}%`,
                  maxWidth: '100%'
                }}
              >
                <div className="absolute inset-0 bg-white/10 animate-pulse"></div>
              </div>
            </div>
            <span className="text-xs font-medium text-amber-50 w-6 text-right group-hover:scale-110 transition-transform">
              {item.value}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
};

export default BarChart;
