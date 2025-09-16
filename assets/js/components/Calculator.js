import React, { useState } from 'react';
import { postQuote, getCurrentRates } from './api';

const Calculator = ({ selectedCurrency }) => {
    const [amount, setAmount] = useState('');
    const [side, setSide] = useState('buy');
    const [quote, setQuote] = useState(null);
    const [currentRates, setCurrentRates] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const handleCalculate = async () => {
        if (!selectedCurrency || !amount) {
            setError('Please select a currency and enter an amount');
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const result = await postQuote({
                code: selectedCurrency,
                side: side,
                amount: amount
            });
            setQuote(result);
        } catch (err) {
            setError(err.message || 'Failed to calculate quote');
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

    React.useEffect(() => {
        if (selectedCurrency) {
            loadCurrentRates();
            setQuote(null);
            setError(null);
        }
    }, [selectedCurrency]);

    if (!selectedCurrency) {
        return (
            <div className="calculator">
                <div className="calculator-placeholder">
                    <h3>Currency Calculator</h3>
                    <p>Select a currency to start calculating</p>
                </div>
            </div>
        );
    }

    return (
        <div className="calculator">
            <div className="calculator-header">
                <h3>Calculate {selectedCurrency}</h3>
            </div>

            {currentRates && (
                <div className="current-rates-display">
                    <h4>Current Rates</h4>
                    <div className="rates-grid">
                        <div className="rate-item">
                            <span className="rate-label">Mid:</span>
                            <span className="rate-value">{parseFloat(currentRates.mid).toFixed(4)} PLN</span>
                        </div>
                        {currentRates.buy && (
                            <div className="rate-item">
                                <span className="rate-label">Buy:</span>
                                <span className="rate-value">{parseFloat(currentRates.buy).toFixed(4)} PLN</span>
                            </div>
                        )}
                        {currentRates.sell && (
                            <div className="rate-item">
                                <span className="rate-label">Sell:</span>
                                <span className="rate-value">{parseFloat(currentRates.sell).toFixed(4)} PLN</span>
                            </div>
                        )}
                    </div>
                </div>
            )}

            <div className="calculator-form">
                <div className="form-group">
                    <label htmlFor="amount">Amount ({selectedCurrency})</label>
                    <input
                        id="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        className="input"
                        value={amount}
                        onChange={(e) => setAmount(e.target.value)}
                        placeholder="Enter amount..."
                    />
                </div>

                <div className="form-group">
                    <label>Operation</label>
                    <div className="radio-group">
                        <label className="radio-option">
                            <input
                                type="radio"
                                value="buy"
                                checked={side === 'buy'}
                                onChange={(e) => setSide(e.target.value)}
                            />
                            <span>Buy {selectedCurrency}</span>
                        </label>
                        <label className="radio-option">
                            <input
                                type="radio"
                                value="sell"
                                checked={side === 'sell'}
                                onChange={(e) => setSide(e.target.value)}
                            />
                            <span>Sell {selectedCurrency}</span>
                        </label>
                    </div>
                </div>

                <button
                    className="button button--primary"
                    onClick={handleCalculate}
                    disabled={loading || !amount}
                >
                    {loading ? 'Calculating...' : 'Calculate'}
                </button>
            </div>

            {error && (
                <div className="calculator-error">
                    <p>{error}</p>
                </div>
            )}

            {quote && (
                <div className="calculator-result">
                    <h4>Quote Result</h4>
                    <div className="quote-details">
                        <div className="quote-row">
                            <span>Amount:</span>
                            <span>{quote.amount} {quote.code}</span>
                        </div>
                        <div className="quote-row">
                            <span>Operation:</span>
                            <span>{quote.side === 'buy' ? 'Buying' : 'Selling'} {quote.code}</span>
                        </div>
                        <div className="quote-row">
                            <span>Unit Rate:</span>
                            <span>{parseFloat(quote.unitRate).toFixed(4)} PLN</span>
                        </div>
                        <div className="quote-row total">
                            <span>Total:</span>
                            <span>{parseFloat(quote.total).toFixed(2)} PLN</span>
                        </div>
                        <div className="quote-meta">
                            <small>Effective Date: {quote.effectiveDate}</small>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Calculator;
