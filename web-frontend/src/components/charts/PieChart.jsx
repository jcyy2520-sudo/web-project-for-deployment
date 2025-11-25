import { useMemo } from 'react';
import { ChartPieIcon } from '@heroicons/react/24/outline';

const PieChart = ({ data, title }) => {
  const safeData = useMemo(() => 
    data.map(item => ({ ...item, value: Number(item.value) || 0 })), 
    [data]
  );
  const total = Math.max(safeData.reduce((sum, item) => sum + item.value, 0), 1);
  let currentAngle = 0;
  
  return (
    <div className="bg-gray-900 border border-amber-500/20 rounded-lg shadow p-4 hover:border-amber-500/40 transition-all duration-300">
      <h3 className="text-sm font-semibold text-amber-50 mb-3 flex items-center">
        <ChartPieIcon className="h-4 w-4 mr-2" />
        {title}
      </h3>
      <div className="flex items-center justify-center">
        <div className="relative w-32 h-32 group">
          <svg viewBox="0 0 100 100" className="w-full h-full transform -rotate-90 transition-transform duration-300 group-hover:scale-105">
            {safeData.map((item, index) => {
              const percentage = (item.value / total) * 100;
              const angle = (percentage / 100) * 360;
              const largeArcFlag = percentage > 50 ? 1 : 0;
              
              const x1 = 50 + 50 * Math.cos(currentAngle * Math.PI / 180);
              const y1 = 50 + 50 * Math.sin(currentAngle * Math.PI / 180);
              currentAngle += angle;
              const x2 = 50 + 50 * Math.cos(currentAngle * Math.PI / 180);
              const y2 = 50 + 50 * Math.sin(currentAngle * Math.PI / 180);
              
              const pathData = [
                `M 50 50`,
                `L ${x1} ${y1}`,
                `A 50 50 0 ${largeArcFlag} 1 ${x2} ${y2}`,
                `Z`
              ].join(' ');
              
              return (
                <path
                  key={index}
                  d={pathData}
                  fill={item.color}
                  stroke="#1f2937"
                  strokeWidth="2"
                  className="transition-all duration-300 hover:opacity-80 cursor-pointer"
                />
              );
            })}
          </svg>
          <div className="absolute inset-0 flex items-center justify-center">
            <div className="text-center">
              <span className="text-amber-50 font-bold text-sm block">{total}</span>
              <span className="text-amber-400 text-xs">Total</span>
            </div>
          </div>
        </div>
      </div>
      <div className="mt-3 space-y-1">
        {safeData.map((item, index) => (
          <div key={index} className="flex items-center text-xs group cursor-pointer hover:bg-gray-800 p-1 rounded transition-colors">
            <div 
              className="w-2 h-2 rounded-full mr-2 transition-transform group-hover:scale-125"
              style={{ backgroundColor: item.color }}
            ></div>
            <span className="text-gray-300 flex-1 group-hover:text-amber-50 truncate">{item.label}</span>
            <span className="text-amber-50 font-medium text-xs">
              {((item.value / total) * 100).toFixed(1)}%
            </span>
            <span className="text-gray-500 text-xs ml-1">({item.value})</span>
          </div>
        ))}
      </div>
    </div>
  );
};

export default PieChart;
