import { useMemo, useState, useRef } from 'react';
import { ChartBarIcon } from '@heroicons/react/24/outline';

const LineChart = ({ data = [], title, color = 'amber', height = 144, embedded = false, variant = 'line', responsive = false, maxHeight = null, isDarkMode = true }) => {
  const containerRef = useRef(null);
  const [tooltip, setTooltip] = useState(null); // {xPx, yPx, index}

  const safeData = useMemo(() => 
    data.map(item => ({ ...item, value: Number(item.value) || 0 })), 
    [data]
  );
  const maxValue = Math.max(...safeData.map(item => item.value), 1);
  const pointsArr = safeData.map((item, index) => {
    const x = (index / Math.max(safeData.length - 1, 1)) * 100;
    const y = 100 - (item.value / maxValue) * 100;
    return { x, y };
  });
  const points = pointsArr.map(p => `${p.x},${p.y}`).join(' ');
  // Build a valid SVG path: start with a moveto (M), then line segments (L...)
  // Example: "M x1,y1 L x2,y2 L x3,y3 L 100 100 L 0 100 Z"
  const areaPath = (() => {
    if (!pointsArr.length) return '';
    const move = `M ${pointsArr[0].x},${pointsArr[0].y}`;
    const lines = pointsArr.slice(1).map(p => `L ${p.x},${p.y}`).join(' ');
    return `${move} ${lines} L 100 100 L 0 100 Z`;
  })();

  const handleMouseMove = (e) => {
    if (!containerRef.current) return;
    const rect = containerRef.current.getBoundingClientRect();
    const xPx = e.clientX - rect.left;
    const pct = Math.max(0, Math.min(1, xPx / rect.width));
    const idx = Math.round(pct * (Math.max(safeData.length - 1, 1)));
    const selected = safeData[idx] || null;
    const xPos = pct * 100;
    const yPct = selected ? (100 - (selected.value / maxValue) * 100) : 80;
    // position tooltip above point
    setTooltip({ index: idx, xPx: rect.left + (xPos/100)*rect.width, yPx: rect.top + (yPct/100)*rect.height, data: selected });
  };

  const handleMouseLeave = () => setTooltip(null);

  // helper styles depending on responsive flag
  const embeddedStyle = responsive
    ? (maxHeight ? { maxHeight: `${maxHeight}px` } : { maxHeight: `${height}px` })
    : { height: `${height}px` };

  // when embedded, render only the chart area (no outer card/header)
  if (embedded) {
    // if bars variant requested, render vertical bars
    if (variant === 'bars') {
      const barPct = Math.max(4, 80 / Math.max(pointsArr.length, 1));
      return (
        <div ref={containerRef} className="relative flex flex-col min-h-0" style={embeddedStyle} onMouseMove={handleMouseMove} onMouseLeave={handleMouseLeave}>
          <svg viewBox="0 0 100 100" preserveAspectRatio="none" className="w-full flex-1 min-h-0">
            {[0, 25, 50, 75, 100].map((y) => (
              <line key={y} x1="0" y1={y} x2="100" y2={y} stroke="#374151" strokeWidth="0.5" />
            ))}
            {pointsArr.map((p, i) => {
              const xCenter = p.x;
              const w = barPct;
              const x = Math.max(0, xCenter - w / 2);
              const y = p.y;
              const h = Math.max(0.5, 100 - y);
              return <rect key={i} x={x} y={y} width={w} height={h} rx="1" fill="#f59e0b" />;
            })}
          </svg>

          <div className="flex-none mt-2 flex justify-between text-xs text-gray-400 px-1">
            {safeData.map((item, index) => (
              <span key={index} className="truncate" style={{ maxWidth: `${100 / Math.max(safeData.length,1)}%` }}>{item.label}</span>
            ))}
          </div>

          {tooltip && tooltip.data && (
            <div style={{ position: 'fixed', left: tooltip.xPx + 8, top: tooltip.yPx - 28 }} className="bg-gray-800 text-xs text-amber-50 px-2 py-1 rounded shadow">
              <div className="font-medium">{tooltip.data.label}</div>
              <div className="text-gray-300">{Number(tooltip.data.value).toLocaleString()}</div>
            </div>
          )}
        </div>
      );
    }

    // default embedded (line)
    return (
      <div ref={containerRef} className="relative flex flex-col min-h-0" style={embeddedStyle} onMouseMove={handleMouseMove} onMouseLeave={handleMouseLeave}>
        <svg viewBox="0 0 100 100" preserveAspectRatio="none" className="w-full flex-1 min-h-0">
          {[0, 25, 50, 75, 100].map((y) => (
            <line key={y} x1="0" y1={y} x2="100" y2={y} stroke="#374151" strokeWidth="0.5" />
          ))}
          <path d={areaPath} fill="url(#area-gradient)" opacity="0.16" />
          <polyline fill="none" stroke="url(#gradient-amber)" strokeWidth="2" points={points} className="animate-draw" />
          {pointsArr.map((p, index) => (
            <circle key={index} cx={p.x} cy={p.y} r="1.8" fill="#f59e0b" />
          ))}
          <defs>
            <linearGradient id="gradient-amber" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stopColor="#f59e0b" stopOpacity="0.9" />
              <stop offset="100%" stopColor="#f59e0b" stopOpacity="0.6" />
            </linearGradient>
            <linearGradient id="area-gradient" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" stopColor="#f59e0b" stopOpacity="0.28" />
              <stop offset="100%" stopColor="#f59e0b" stopOpacity="0.02" />
            </linearGradient>
          </defs>
        </svg>

        <div className="flex-none mt-2 flex justify-between text-xs text-gray-400 px-1">
          {safeData.map((item, index) => (
            <span key={index} className="truncate" style={{ maxWidth: `${100 / Math.max(safeData.length,1)}%` }}>{item.label}</span>
          ))}
        </div>

        {tooltip && tooltip.data && (
          <div style={{ position: 'fixed', left: tooltip.xPx + 8, top: tooltip.yPx - 28 }} className="bg-gray-800 text-xs text-amber-50 px-2 py-1 rounded shadow">
            <div className="font-medium">{tooltip.data.label}</div>
            <div className="text-gray-300">{Number(tooltip.data.value).toLocaleString()}</div>
          </div>
        )}
      </div>
    );
  }

  const wrapperStyle = responsive
    ? (maxHeight ? { maxHeight: `${maxHeight}px` } : undefined)
    : { height: `${height + 48}px` };
 
  return (
      <div ref={containerRef} className={`${isDarkMode ? 'bg-gray-900 border-amber-500/20 hover:border-amber-500/40' : 'bg-white border-amber-300/30 hover:border-amber-400/50'} border rounded-lg shadow p-4 transition-all duration-300 relative flex flex-col`} style={wrapperStyle}>
      <h3 className={`text-sm font-semibold ${isDarkMode ? 'text-amber-50' : 'text-amber-900'} mb-3 flex items-center`}>
        <ChartBarIcon className="h-4 w-4 mr-2" />
        {title}
      </h3>
      <div className="relative flex-1 flex flex-col min-h-0" style={responsive ? undefined : { minHeight: `${height}px` }} onMouseMove={handleMouseMove} onMouseLeave={handleMouseLeave}>
        <svg viewBox="0 0 100 100" preserveAspectRatio="none" className="w-full flex-1">
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
          <path d={areaPath} fill="url(#area-gradient)" opacity="0.16" />
          <polyline
            fill="none"
            stroke="url(#gradient-amber)"
            strokeWidth="2"
            points={points}
            className="animate-draw"
          />
          {pointsArr.map((p, index) => (
            <circle key={index} cx={p.x} cy={p.y} r="1.8" fill="#f59e0b" />
          ))}
          <defs>
            <linearGradient id="gradient-amber" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stopColor="#f59e0b" stopOpacity="0.9" />
              <stop offset="100%" stopColor="#f59e0b" stopOpacity="0.6" />
            </linearGradient>
            <linearGradient id="area-gradient" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" stopColor="#f59e0b" stopOpacity="0.28" />
              <stop offset="100%" stopColor="#f59e0b" stopOpacity="0.02" />
            </linearGradient>
          </defs>
        </svg>

        {/* x labels */}
        <div className="flex-none mt-2 flex justify-between text-xs text-gray-400 px-1">
          {safeData.map((item, index) => (
            <span key={index} className="truncate" style={{ maxWidth: `${100 / Math.max(safeData.length,1)}%` }}>{item.label}</span>
          ))}
        </div>

        {/* Tooltip */}
        {tooltip && tooltip.data && (
          <div style={{ position: 'fixed', left: tooltip.xPx + 8, top: tooltip.yPx - 28 }} className="bg-gray-800 text-xs text-amber-50 px-2 py-1 rounded shadow">
            <div className="font-medium">{tooltip.data.label}</div>
            <div className="text-gray-300">{Number(tooltip.data.value).toLocaleString()}</div>
          </div>
        )}
      </div>
    </div>
  );
};

export default LineChart;
