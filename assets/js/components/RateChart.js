import React, { useState, useEffect } from 'react';
import { getHistory } from './api';

const RateChart = ({ selectedCurrency }) => {
    const [historyData, setHistoryData] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [isChartHovered, setIsChartHovered] = useState(false);
    const [hoveredPoint, setHoveredPoint] = useState(null);
    const [mousePosition, setMousePosition] = useState({ x: 0, y: 0 });
    const [chartRef, setChartRef] = useState(null);
    const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]);
    const [showDatePicker, setShowDatePicker] = useState(false);

    useEffect(() => {
        if (selectedCurrency) {
            fetchHistory();
        }
    }, [selectedCurrency, selectedDate]);

    const fetchHistory = async () => {
        if (!selectedCurrency) return;

        setLoading(true);
        setError(null);

        try {
            const data = await getHistory(selectedCurrency, selectedDate, 14);
            setHistoryData(data.history || []);
        } catch (err) {
            setError(err.message || 'Failed to load chart data');
            setHistoryData([]);
        } finally {
            setLoading(false);
        }
    };

    // Date navigation functions
    const handlePreviousDay = () => {
        const currentDate = new Date(selectedDate);
        currentDate.setDate(currentDate.getDate() - 1);
        setSelectedDate(currentDate.toISOString().split('T')[0]);
    };

    const handleNextDay = () => {
        const currentDate = new Date(selectedDate);
        const today = new Date();
        
        // Don't allow going beyond today
        if (currentDate.toDateString() !== today.toDateString()) {
            currentDate.setDate(currentDate.getDate() + 1);
            setSelectedDate(currentDate.toISOString().split('T')[0]);
        }
    };

    const handleDateChange = (event) => {
        setSelectedDate(event.target.value);
        setShowDatePicker(false);
    };

    const canGoNext = () => {
        const currentDate = new Date(selectedDate);
        const today = new Date();
        return currentDate.toDateString() !== today.toDateString();
    };

    // Helper function to get mouse position relative to chart
    const handleMouseMove = (event) => {
        if (!chartRef) return;
        
        const rect = chartRef.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;
        setMousePosition({ x, y });

        // Check proximity to points
        if (isChartHovered && historyData.length > 0) {
            const svgElement = chartRef.querySelector('svg');
            if (!svgElement) return;
            
            const svgRect = svgElement.getBoundingClientRect();
            const containerRect = chartRef.getBoundingClientRect();
            
            // Calculate the exact position within the SVG
            const svgX = event.clientX - svgRect.left;
            const svgY = event.clientY - svgRect.top;
            
            // Convert to SVG coordinate system
            const svgRealWidth = chartWidth + marginLeft + marginRight;
            const svgRealHeight = chartHeight + marginTop + marginBottom;
            
            const scaleX = svgRealWidth / svgRect.width;
            const scaleY = svgRealHeight / svgRect.height;
            
            const chartCoordX = svgX * scaleX;
            const chartCoordY = svgY * scaleY;
            
            let closestPoint = null;
            let minDistance = Infinity;
            const proximityThreshold = 20; // Increased for easier interaction
            
            historyData.forEach((item, index) => {
                const pointX = getX(index);
                
                // Check mid rate point
                const midY = getY(item.mid);
                const midDistance = Math.sqrt(Math.pow(chartCoordX - pointX, 2) + Math.pow(chartCoordY - midY, 2));
                if (midDistance < proximityThreshold && midDistance < minDistance) {
                    minDistance = midDistance;
                    closestPoint = `mid-${index}`;
                }
                
                // Check buy rate point
                if (item.buy) {
                    const buyY = getY(item.buy);
                    const buyDistance = Math.sqrt(Math.pow(chartCoordX - pointX, 2) + Math.pow(chartCoordY - buyY, 2));
                    if (buyDistance < proximityThreshold && buyDistance < minDistance) {
                        minDistance = buyDistance;
                        closestPoint = `buy-${index}`;
                    }
                }
                
                // Check sell rate point
                if (item.sell) {
                    const sellY = getY(item.sell);
                    const sellDistance = Math.sqrt(Math.pow(chartCoordX - pointX, 2) + Math.pow(chartCoordY - sellY, 2));
                    if (sellDistance < proximityThreshold && sellDistance < minDistance) {
                        minDistance = sellDistance;
                        closestPoint = `sell-${index}`;
                    }
                }
            });
            
            setHoveredPoint(closestPoint);
        }
    };

    if (!selectedCurrency) {
        return (
            <div className="rate-chart">
                <div className="chart-placeholder">
                    <p>Select a currency to view its chart</p>
                </div>
            </div>
        );
    }

    if (loading) {
        return (
            <div className="rate-chart">
                <div className="chart-header">
                    <h3>{selectedCurrency} History (14 days)</h3>
                </div>
                <div className="chart-placeholder">
                    <p>Loading chart data...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="rate-chart">
                <div className="chart-header">
                    <h3>{selectedCurrency} History (14 days)</h3>
                </div>
                <div className="chart-error">
                    <p>Error: {error}</p>
                    <button className="button button--secondary" onClick={fetchHistory}>
                        Retry
                    </button>
                </div>
            </div>
        );
    }

    // Calculate min/max for all rate types to properly scale the chart
    const allRates = [];
    historyData.forEach(item => {
        allRates.push(parseFloat(item.mid));
        if (item.buy) allRates.push(parseFloat(item.buy));
        if (item.sell) allRates.push(parseFloat(item.sell));
    });
    
    const maxRate = Math.max(...allRates);
    const minRate = Math.min(...allRates);
    const range = maxRate - minRate;
    
    // Chart dimensions with margins for axes - using larger dimensions
    const chartWidth = 520;
    const chartHeight = 200;
    const marginLeft = 60;
    const marginBottom = 40;
    const marginTop = 20;
    const marginRight = 20;

    // Helper function to calculate positions
    const getX = (index) => marginLeft + (index / Math.max(historyData.length - 1, 1)) * chartWidth;
    const getY = (value) => range > 0 
        ? marginTop + chartHeight - ((parseFloat(value) - minRate) / range) * chartHeight 
        : marginTop + chartHeight / 2;

    return (
        <div className="rate-chart">
            <div className="chart-header">
                <h3>{selectedCurrency} History (14 days)</h3>                
                <div className="date-navigation">
                <button 
                    className="date-nav-button" 
                    onClick={handlePreviousDay}
                    title="Previous day"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="15,18 9,12 15,6"></polyline>
                    </svg>
                </button>
                
                <div className="date-picker-container">
                    <button 
                        className="date-display-button"
                        onClick={() => setShowDatePicker(!showDatePicker)}
                        title="Select date"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span>{new Date(selectedDate).toLocaleDateString('en-US', { 
                            month: 'short', 
                            day: 'numeric', 
                            year: 'numeric' 
                        })}</span>
                    </button>
                    
                    {showDatePicker && (
                        <div className="date-picker-dropdown">
                            <input
                                type="date"
                                value={selectedDate}
                                max={new Date().toISOString().split('T')[0]}
                                onChange={handleDateChange}
                                className="date-input"
                                autoFocus
                                onBlur={() => setShowDatePicker(false)}
                            />
                        </div>
                    )}
                </div>
                
                <button 
                    className="date-nav-button" 
                    onClick={handleNextDay}
                    disabled={!canGoNext()}
                    title="Next day"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <polyline points="9,18 15,12 9,6"></polyline>
                    </svg>
                </button>
            </div>
            </div>            
            
            <div 
                className="chart-container"
                ref={setChartRef}
                onMouseMove={handleMouseMove}
                onMouseEnter={() => setIsChartHovered(true)}
                onMouseLeave={() => {
                    setIsChartHovered(false);
                    setHoveredPoint(null);
                }}
            >
                <svg 
                    className="chart-svg" 
                    viewBox={`0 0 ${chartWidth + marginLeft + marginRight} ${chartHeight + marginTop + marginBottom}`}
                >                    
                    
                    {/* Y-axis */}
                    <line 
                        x1={marginLeft} 
                        y1={marginTop} 
                        x2={marginLeft} 
                        y2={marginTop + chartHeight} 
                        stroke="var(--gray-400)" 
                        strokeWidth="1"
                    />
                    
                    {/* X-axis */}
                    <line 
                        x1={marginLeft} 
                        y1={marginTop + chartHeight} 
                        x2={marginLeft + chartWidth} 
                        y2={marginTop + chartHeight} 
                        stroke="var(--gray-400)" 
                        strokeWidth="1"
                    />
                    
                    {/* Y-axis labels */}
                    {[0, 0.25, 0.5, 0.75, 1].map(ratio => {
                        const value = minRate + ratio * range;
                        const y = marginTop + chartHeight - ratio * chartHeight;
                        return (
                            <g key={ratio}>
                                <line 
                                    x1={marginLeft - 5} 
                                    y1={y} 
                                    x2={marginLeft} 
                                    y2={y} 
                                    stroke="var(--gray-400)" 
                                    strokeWidth="1"
                                />
                                <text 
                                    x={marginLeft - 8} 
                                    y={y + 3} 
                                    textAnchor="end" 
                                    fontSize="10" 
                                    fill="var(--gray-600)"
                                >
                                    {value.toFixed(3)}
                                </text>
                            </g>
                        );
                    })}
                    
                    {/* X-axis labels (show every 3rd date to avoid crowding) */}
                    {historyData.map((item, index) => {
                        if (index % 3 === 0 || index === historyData.length - 1) {
                            const x = getX(index);
                            const shortDate = new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                            return (
                                <g key={index}>
                                    <line 
                                        x1={x} 
                                        y1={marginTop + chartHeight} 
                                        x2={x} 
                                        y2={marginTop + chartHeight + 5} 
                                        stroke="var(--gray-400)" 
                                        strokeWidth="1"
                                    />
                                    <text 
                                        x={x} 
                                        y={marginTop + chartHeight + 18} 
                                        textAnchor="middle" 
                                        fontSize="10" 
                                        fill="var(--gray-600)"
                                    >
                                        {shortDate}
                                    </text>
                                </g>
                            );
                        }
                        return null;
                    })}
                    
                    {/* Y-axis title */}
                    <text 
                        x={15} 
                        y={marginTop + chartHeight / 2} 
                        textAnchor="middle" 
                        fontSize="12" 
                        fill="var(--gray-700)"
                        transform={`rotate(-90, 15, ${marginTop + chartHeight / 2})`}
                    >
                        Rate (PLN)
                    </text>
                    
                    {/* Mid rate line (gray) */}
                    {historyData.length > 1 && (
                        <polyline
                            fill="none"
                            stroke="var(--gray-500)"
                            strokeWidth="3"
                            points={historyData.map((item, index) => {
                                const x = getX(index);
                                const y = getY(item.mid);
                                return `${x},${y}`;
                            }).join(' ')}
                        />
                    )}
                    
                    {/* Buy rate line (green) */}
                    {historyData.some(item => item.buy) && historyData.length > 1 && (
                        <polyline
                            fill="none"
                            stroke="var(--success-500)"
                            strokeWidth="3"
                            points={historyData.filter(item => item.buy).map((item, filteredIndex) => {
                                const originalIndex = historyData.findIndex(h => h.date === item.date);
                                const x = getX(originalIndex);
                                const y = getY(item.buy);
                                return `${x},${y}`;
                            }).join(' ')}
                        />
                    )}
                    
                    {/* Sell rate line (red) */}
                    {historyData.some(item => item.sell) && historyData.length > 1 && (
                        <polyline
                            fill="none"
                            stroke="var(--error-500)"
                            strokeWidth="3"
                            points={historyData.filter(item => item.sell).map((item, filteredIndex) => {
                                const originalIndex = historyData.findIndex(h => h.date === item.date);
                                const x = getX(originalIndex);
                                const y = getY(item.sell);
                                return `${x},${y}`;
                            }).join(' ')}
                        />
                    )}
                    
                    {/* Hover-sensitive data points - only visible when chart is hovered */}
                    {isChartHovered && historyData.map((item, index) => {
                        const x = getX(index);
                        const yMid = getY(item.mid);
                        const yBuy = item.buy ? getY(item.buy) : null;
                        const ySell = item.sell ? getY(item.sell) : null;
                        
                        return (
                            <g key={`point-group-${index}`} className="point-group">
                                {/* Mid rate point */}
                                <circle
                                    cx={x}
                                    cy={yMid}
                                    r={hoveredPoint === `mid-${index}` ? 6 : 4}
                                    fill="var(--gray-500)"
                                    className={`chart-point ${hoveredPoint === `mid-${index}` ? 'chart-point--hovered' : ''}`}
                                />
                                
                                {/* Buy rate point */}
                                {item.buy && (
                                    <circle
                                        cx={x}
                                        cy={yBuy}
                                        r={hoveredPoint === `buy-${index}` ? 6 : 4}
                                        fill="var(--success-500)"
                                        className={`chart-point ${hoveredPoint === `buy-${index}` ? 'chart-point--hovered' : ''}`}
                                    />
                                )}
                                
                                {/* Sell rate point */}
                                {item.sell && (
                                    <circle
                                        cx={x}
                                        cy={ySell}
                                        r={hoveredPoint === `sell-${index}` ? 6 : 4}
                                        fill="var(--error-500)"
                                        className={`chart-point ${hoveredPoint === `sell-${index}` ? 'chart-point--hovered' : ''}`}
                                    />
                                )}
                            </g>
                        );
                    })}
                </svg>
            </div>
            
            {/* Tooltip */}
            {hoveredPoint && chartRef && (
                <div 
                    className="chart-tooltip"
                    style={{
                        left: mousePosition.x + 15,
                        top: mousePosition.y - 10,
                        transform: mousePosition.x > chartRef.offsetWidth - 220 ? 'translateX(-100%) translateX(-30px)' : 'none'
                    }}
                >
                    {(() => {
                        const [type, indexStr] = hoveredPoint.split('-');
                        const index = parseInt(indexStr);
                        const item = historyData[index];
                        if (!item) return null;
                        
                        const rateValue = type === 'mid' ? item.mid : 
                                        type === 'buy' ? item.buy : 
                                        item.sell;
                        const rateLabel = type === 'mid' ? 'Mid Rate (NBP)' :
                                        type === 'buy' ? 'Buy Rate' :
                                        'Sell Rate';
                        const rateColor = type === 'mid' ? 'var(--gray-500)' :
                                        type === 'buy' ? 'var(--success-500)' :
                                        'var(--error-500)';
                        
                        return (
                            <div className="tooltip-content">
                                <div className="tooltip-date">{new Date(item.date).toLocaleDateString('en-US', { 
                                    weekday: 'short',
                                    month: 'short', 
                                    day: 'numeric',
                                    year: 'numeric'
                                })}</div>
                                <div className="tooltip-rate">
                                    <span className="tooltip-label" style={{ color: rateColor }}>
                                        {rateLabel}:
                                    </span>
                                    <span className="tooltip-value">
                                        {parseFloat(rateValue).toFixed(4)} PLN
                                    </span>
                                </div>
                                {type === 'mid' && (
                                    <div className="tooltip-extra">
                                        {item.buy && (
                                            <div>Buy: {parseFloat(item.buy).toFixed(4)} PLN</div>
                                        )}
                                        {item.sell && (
                                            <div>Sell: {parseFloat(item.sell).toFixed(4)} PLN</div>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })()}
                </div>
            )}
            
            {/* Legend */}
            <div className="chart-legend">
                <div className="legend-item">
                    <div className="legend-line legend-line--mid"></div>
                    <span>Mid Rate (NBP)</span>
                </div>
                {historyData.some(item => item.buy) && (
                    <div className="legend-item">
                        <div className="legend-line legend-line--buy"></div>
                        <span>Buy Rate</span>
                    </div>
                )}
                {historyData.some(item => item.sell) && (
                    <div className="legend-item">
                        <div className="legend-line legend-line--sell"></div>
                        <span>Sell Rate</span>
                    </div>
                )}
            </div>
            
            <div className="chart-data">
                <div className="chart-table">                
                    <div className="table-body-container">
                        <table className="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Mid Rate</th>
                                    <th>Buy Rate</th>
                                    <th>Sell Rate</th>
                                </tr>
                            </thead>
                            <tbody className="table-body">
                                {historyData
                                    .slice()
                                    .reverse()
                                    .map((item, index) => (
                                    <tr key={index}>
                                        <td>{item.date}</td>
                                        <td>{parseFloat(item.mid).toFixed(4)} PLN</td>
                                        <td>{item.buy ? `${parseFloat(item.buy).toFixed(4)} PLN` : '—'}</td>
                                        <td>{item.sell ? `${parseFloat(item.sell).toFixed(4)} PLN` : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default RateChart;
