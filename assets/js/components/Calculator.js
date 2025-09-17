import React, { useState, useEffect, useCallback } from 'react';
import { postQuote, getCurrentRates } from './api';

// Custom useDebounce hook
const useDebounce = (value, delay) => {
    const [debouncedValue, setDebouncedValue] = useState(value);

    useEffect(() => {
        const handler = setTimeout(() => {
            setDebouncedValue(value);
        }, delay);

        return () => {
            clearTimeout(handler);
        };
    }, [value, delay]);

    return debouncedValue;
};

const Calculator = ({ selectedCurrency, onCurrencyChange }) => {
    const [foreignAmount, setForeignAmount] = useState('');
    const [plnAmount, setPlnAmount] = useState('');
    const [operation, setOperation] = useState('sell'); // 'sell' = client wants to buy foreign currency, 'buy' = client wants to sell foreign currency
    const [quote, setQuote] = useState(null);
    const [currentRates, setCurrentRates] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [history, setHistory] = useState([]);
    const [showHistory, setShowHistory] = useState(true);

    // Load history from localStorage on component mount
    useEffect(() => {
        const savedHistory = localStorage.getItem('calculatorHistory');
        if (savedHistory) {
            try {
                const parsedHistory = JSON.parse(savedHistory);
                if (Array.isArray(parsedHistory)) {
                    setHistory(parsedHistory);
                }
            } catch (error) {
                console.error('Error parsing history from localStorage:', error);
                localStorage.removeItem('calculatorHistory');
            }
        }
    }, []);

    // Debounce the foreign amount input
    const debouncedForeignAmount = useDebounce(foreignAmount, 500);

    // Currency data
    const currencyData = {
        'PLN': { name: 'Polish Złoty', flag: '🇵🇱', symbol: 'zł' },
        'EUR': { name: 'Euro', flag: '🇪🇺', symbol: '€' },
        'USD': { name: 'US Dollar', flag: '🇺🇸', symbol: '$' },
        'CZK': { name: 'Czech Koruna', flag: '🇨🇿', symbol: 'Kč' },
        'IDR': { name: 'Indonesian Rupiah', flag: '🇮🇩', symbol: 'Rp' },
        'BRL': { name: 'Brazilian Real', flag: '🇧🇷', symbol: 'R$' }
    };

    const handleSwap = () => {
        setOperation(operation === 'sell' ? 'buy' : 'sell');
        // Recalculate with new operation if amount exists
        if (foreignAmount && currentRates) {
            calculatePLNAmount(foreignAmount);
        }
    };

    const handleForeignAmountChange = (value) => {
        setForeignAmount(value);
        if (!value) {
            setPlnAmount('');
            setQuote(null);
            setError(null);
        }
    };

    const calculatePLNAmount = async (amount) => {
        if (!selectedCurrency || !amount || !currentRates) return;

        setLoading(true);
        setError(null);

        try {
            const request = {
                code: selectedCurrency,
                side: operation,
                amount: amount
            };
            
            const result = await postQuote(request);
            setQuote(result);
            setPlnAmount(result.total);
            
            // Save successful quote to history
            saveToHistory(request, result);
        } catch (err) {
            setError(err.message || 'Failed to calculate exchange');
            setPlnAmount('');
            setQuote(null);
        } finally {
            setLoading(false);
        }
    };

    const loadCurrentRates = async () => {
        if (!selectedCurrency) return;

        try {
            const rates = await getCurrentRates([selectedCurrency]);
            const rate = rates.rates?.find(r => r.code === selectedCurrency);
            setCurrentRates(rate);
        } catch (err) {
            console.error('Failed to load current rates:', err);
        }
    };

    // Save quote request to history
    const saveToHistory = (request, result) => {
        const historyEntry = {
            id: Date.now() + Math.random(), // Simple unique ID
            timestamp: new Date().toISOString(),
            request: {
                currency: request.code,
                operation: request.side,
                amount: request.amount
            },
            result: {
                unitRate: result.unitRate,
                total: result.total,
                effectiveDate: result.effectiveDate
            }
        };

        const newHistory = [historyEntry, ...history].slice(0, 50); // Keep last 50 entries
        setHistory(newHistory);

        // Save to localStorage
        try {
            localStorage.setItem('calculatorHistory', JSON.stringify(newHistory));
        } catch (error) {
            console.error('Error saving history to localStorage:', error);
        }
    };

    // Load request from history
    const loadFromHistory = (historyEntry) => {
        const { currency, operation, amount } = historyEntry.request;
        
        // Switch currency if different
        if (currency !== selectedCurrency && onCurrencyChange) {
            onCurrencyChange(currency);
        }
        
        // Set operation and amount
        setOperation(operation);
        setForeignAmount(amount);
        
        // Clear previous results so they get recalculated
        setPlnAmount('');
        setQuote(null);
        setError(null);
        
        // Hide history panel
        setShowHistory(false);
    };

    // Clear all history
    const clearHistory = () => {
        setHistory([]);
        try {
            localStorage.removeItem('calculatorHistory');
        } catch (error) {
            console.error('Error clearing history from localStorage:', error);
        }
    };

    useEffect(() => {
        if (selectedCurrency) {
            loadCurrentRates();
            // Don't clear foreignAmount - keep the user's input
            setPlnAmount('');
            setQuote(null);
            setError(null);
        }
    }, [selectedCurrency]);

    // Effect to handle debounced calculations
    useEffect(() => {
        if (debouncedForeignAmount && currentRates) {
            calculatePLNAmount(debouncedForeignAmount);
        }
    }, [debouncedForeignAmount, currentRates, operation]);

    if (!selectedCurrency) {
        return (
            <div className="calculator">
                <div className="calculator-placeholder">
                    <h3>Exchange Calculator</h3>
                    <p>Select a currency to start exchanging</p>
                </div>
            </div>
        );
    }

    const rate = currentRates ? (operation === 'sell' ? currentRates.sell : currentRates.buy) : null;
    const operationTitle = operation === 'sell' ? 'Sell' : 'Buy';

    return (
        <div className="calculator">
            <div className="exchange-container">
                <h3 className="exchange-title">{operationTitle} {selectedCurrency}</h3>
                
                {/* Foreign Currency Card - Always the input */}
                <div className="currency-card currency-card--foreign">
                    <div className="transaction-label">
                        {operation === 'sell' ? 
                            <>Client wants to buy {selectedCurrency}</> : 
                            <>Client brings {selectedCurrency}</>
                        }
                    </div>
                    <div className="currency-header">
                        <div className="currency-flag-large">
                            {currencyData[selectedCurrency]?.flag}
                        </div>
                        <div className="currency-info">
                            <div className="currency-code-large">{selectedCurrency}</div>
                            <div className="currency-name-small">{currencyData[selectedCurrency]?.name}</div>
                        </div>
                        <div className="currency-amount">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                className="amount-input"
                                value={foreignAmount}
                                onChange={(e) => handleForeignAmountChange(e.target.value)}
                                placeholder="0"
                            />
                            <span className="currency-symbol">{currencyData[selectedCurrency]?.symbol}</span>
                        </div>
                    </div>
                </div>

                {/* Swap Arrow */}
                <div className="swap-container">
                    <button 
                        className={`swap-button ${operation === 'buy' ? 'rotated' : ''}`}
                        onClick={handleSwap}
                        disabled={loading}
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path 
                                d="M7 10L12 15L17 10" 
                                stroke="currentColor" 
                                strokeWidth="2" 
                                strokeLinecap="round" 
                                strokeLinejoin="round"
                            />
                        </svg>
                    </button>
                </div>

                {/* PLN Currency Card - Always the calculated result */}
                <div className="currency-card currency-card--pln">
                    <div className="transaction-label">
                        {operation === 'sell' ? 
                            <>Client pays PLN</> : 
                            <>We pay client PLN</>
                        }
                    </div>
                    <div className="currency-header">
                        <div className="currency-flag-large">
                            {currencyData['PLN']?.flag}
                        </div>
                        <div className="currency-info">
                            <div className="currency-code-large">PLN</div>
                            <div className="currency-name-small">{currencyData['PLN']?.name}</div>
                        </div>
                        <div className="currency-amount">
                            {loading ? (
                                <div className="loading-placeholder"></div>
                            ) : (
                                <div className="amount-display">
                                    {plnAmount ? 
                                        `${operation === 'sell' ? '+' : '-'}${parseFloat(plnAmount).toFixed(2)}` : 
                                        '0'
                                    }
                                </div>
                            )}
                            <span className="currency-symbol">{currencyData['PLN']?.symbol}</span>
                        </div>
                    </div>
                </div>

                {/* Rate Information */}
                {rate && (
                    <div className="rate-info-card">
                        <div className="rate-header">
                            <strong>Exchange Rate ({operation === 'sell' ? 'Selling' : 'Buying'} {selectedCurrency})</strong>
                        </div>
                        <div className="rate-line">
                            1 {selectedCurrency} = {parseFloat(rate).toFixed(4)} PLN
                        </div>
                        {quote && (
                            <div className="rate-timestamp">
                                Rate from {quote.effectiveDate}
                            </div>
                        )}
                        <div className="transaction-summary">
                            {operation === 'sell' ? (
                                <>We SELL {selectedCurrency}: Client brings PLN, we give {selectedCurrency}</>
                            ) : (
                                <>We BUY {selectedCurrency}: Client brings {selectedCurrency}, we give PLN</>
                            )}
                        </div>
                    </div>
                )}

                {/* Error Display */}
                {error && (
                    <div className="exchange-error">
                        {error}
                    </div>
                )}

                {/* History Section */}
                <div className="history-section">
                    <div className="history-header">
                        <button 
                            className="history-toggle"
                            onClick={() => setShowHistory(!showHistory)}
                        >
                            <span>📜</span>
                            <span>History ({history.length})</span>
                            <span className={`history-arrow ${showHistory ? 'open' : ''}`}>▼</span>
                        </button>
                        {history.length > 0 && (
                            <button 
                                className="history-clear"
                                onClick={clearHistory}
                                title="Clear all history"
                            >
                                🗑️
                            </button>
                        )}
                    </div>

                    {showHistory && (
                        <div className="history-panel">
                            {history.length === 0 ? (
                                <div className="history-empty">
                                    <p>No calculations yet</p>
                                    <small>Make a quote calculation to see it here</small>
                                </div>
                            ) : (
                                <div className="history-list">
                                    {history.map((entry) => (
                                        <HistoryItem
                                            key={entry.id}
                                            entry={entry}
                                            currencyData={currencyData}
                                            onLoad={() => loadFromHistory(entry)}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

const HistoryItem = ({ entry, currencyData, onLoad }) => {
    const { request, result, timestamp } = entry;
    const date = new Date(timestamp);
    
    const formatTime = (date) => {
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    };

    const operationLabel = request.operation === 'sell' ? 'Sell' : 'Buy';
    const operationColor = request.operation === 'sell' ? 'var(--error-500)' : 'var(--success-500)';

    return (
        <button className="history-item" onClick={onLoad}>
            <div className="history-item-header">
                <div className="history-currency">
                    <span className="history-flag">{currencyData[request.currency]?.flag}</span>
                    <span className="history-code">{request.currency}</span>
                    <span 
                        className="history-operation"
                        style={{ color: operationColor }}
                    >
                        {operationLabel}
                    </span>
                </div>
                <div className="history-time">{formatTime(date)}</div>
            </div>
            <div className="history-item-details">
                <div className="history-amount">
                    {request.amount} {currencyData[request.currency]?.symbol} → {parseFloat(result.total).toFixed(2)} zł
                </div>
                <div className="history-rate">
                    Rate: {parseFloat(result.unitRate).toFixed(4)} PLN
                </div>
            </div>
        </button>
    );
};

export default Calculator;
