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

const Calculator = ({ selectedCurrency }) => {
    const [foreignAmount, setForeignAmount] = useState('');
    const [plnAmount, setPlnAmount] = useState('');
    const [operation, setOperation] = useState('sell'); // 'sell' = client wants to buy foreign currency, 'buy' = client wants to sell foreign currency
    const [quote, setQuote] = useState(null);
    const [currentRates, setCurrentRates] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

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
            const result = await postQuote({
                code: selectedCurrency,
                side: operation,
                amount: amount
            });
            setQuote(result);
            setPlnAmount(result.total);
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
            </div>
        </div>
    );
};

export default Calculator;
