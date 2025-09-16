import React, { useState, useEffect } from 'react';
import { getHistory } from './api';

const RateChart = ({ selectedCurrency }) => {
    const [historyData, setHistoryData] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (selectedCurrency) {
            fetchHistory();
        }
    }, [selectedCurrency]);

    const fetchHistory = async () => {
        if (!selectedCurrency) return;

        setLoading(true);
        setError(null);

        try {
            const today = new Date().toISOString().split('T')[0];
            const data = await getHistory(selectedCurrency, today, 14);
            setHistoryData(data.history || []);
        } catch (err) {
            setError(err.message || 'Failed to load chart data');
            setHistoryData([]);
        } finally {
            setLoading(false);
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

    const maxRate = Math.max(...historyData.map(item => parseFloat(item.mid)));
    const minRate = Math.min(...historyData.map(item => parseFloat(item.mid)));
    const range = maxRate - minRate;

    return (
        <div className="rate-chart">
            <div className="chart-header">
                <h3>{selectedCurrency} History (14 days)</h3>
                <div className="chart-stats">
                    <span>Max: {maxRate.toFixed(4)} PLN</span>
                    <span>Min: {minRate.toFixed(4)} PLN</span>
                </div>
            </div>
            
            <div className="chart-container">
                <svg className="chart-svg" viewBox="0 0 400 200">
                    {/* Background grid */}
                    <defs>
                        <pattern id="grid" width="40" height="20" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 20" fill="none" stroke="var(--gray-200)" strokeWidth="0.5"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                    
                    {/* Chart line */}
                    {historyData.length > 1 && (
                        <polyline
                            fill="none"
                            stroke="var(--accent-600)"
                            strokeWidth="2"
                            points={historyData.map((item, index) => {
                                const x = (index / (historyData.length - 1)) * 380 + 10;
                                const y = range > 0 
                                    ? 190 - ((parseFloat(item.mid) - minRate) / range) * 180 
                                    : 100;
                                return `${x},${y}`;
                            }).join(' ')}
                        />
                    )}
                    
                    {/* Data points */}
                    {historyData.map((item, index) => {
                        const x = (index / (historyData.length - 1)) * 380 + 10;
                        const y = range > 0 
                            ? 190 - ((parseFloat(item.mid) - minRate) / range) * 180 
                            : 100;
                        return (
                            <circle
                                key={index}
                                cx={x}
                                cy={y}
                                r="3"
                                fill="var(--accent-600)"
                                className="chart-point"
                            >
                                <title>{`${item.date}: ${parseFloat(item.mid).toFixed(4)} PLN`}</title>
                            </circle>
                        );
                    })}
                </svg>
            </div>
            
            <div className="chart-data">
                <table className="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Mid Rate</th>
                            <th>Buy Rate</th>
                            <th>Sell Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        {historyData.slice(0, 7).map((item, index) => (
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
    );
};

export default RateChart;
