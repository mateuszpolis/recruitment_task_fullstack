import React, { useState, useEffect } from 'react';
import { getCurrentRates } from './api';

const SidebarRates = ({ selectedCurrency, onCurrencySelect }) => {
    const currencies = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];
    const [rates, setRates] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        const fetchRates = async () => {
            try {
                setLoading(true);
                setError(null);
                const response = await getCurrentRates(currencies);
                
                // Convert array to object for easier lookup
                const ratesMap = {};
                response.rates.forEach(rate => {
                    ratesMap[rate.code] = rate;
                });
                setRates(ratesMap);
            } catch (err) {
                setError(err.message || 'Failed to fetch rates');
                console.error('Error fetching rates:', err);
            } finally {
                setLoading(false);
            }
        };

        fetchRates();
        
        // Refresh rates every 5 minutes
        const interval = setInterval(fetchRates, 5 * 60 * 1000);
        return () => clearInterval(interval);
    }, []);

    const formatRate = (rate) => {
        if (!rate) return '—';
        return parseFloat(rate).toFixed(4);
    };

    // Get searchable data for currencies
    const getSearchableData = (code) => {
        const currencyData = {
            'EUR': { name: 'Euro', country: 'European Union' },
            'USD': { name: 'US Dollar', country: 'United States of America' },
            'CZK': { name: 'Czech Koruna', country: 'Czech Republic' },
            'IDR': { name: 'Indonesian Rupiah', country: 'Indonesia' },
            'BRL': { name: 'Brazilian Real', country: 'Brazil' }
        };
        return currencyData[code] || { name: code, country: '' };
    };

    // Filter currencies based on search term
    const filteredCurrencies = currencies.filter(currency => {
        if (!searchTerm.trim()) return true;
        
        const searchLower = searchTerm.toLowerCase();
        const data = getSearchableData(currency);
        
        return (
            currency.toLowerCase().includes(searchLower) ||
            data.name.toLowerCase().includes(searchLower) ||
            data.country.toLowerCase().includes(searchLower)
        );
    });

    return (
        <div className="sidebar-rates">
            <h3>Exchange Rates</h3>
            
            <div className="search-container">
                <input
                    type="text"
                    className="search-input"
                    placeholder="Search currencies..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                />
            </div>
            
            <div className="currency-list">
                {filteredCurrencies.length > 0 ? (
                    filteredCurrencies.map(currency => {
                    const rate = rates[currency];
                    return (
                        <button
                            key={currency}
                            className={`currency-item ${selectedCurrency === currency ? 'active' : ''}`}
                            onClick={() => onCurrencySelect(currency)}
                            disabled={loading}
                        >
                            <div className="currency-header">
                                <div className="currency-flag">
                                    <CurrencyFlag code={currency} />
                                </div>
                                <div className="currency-info">
                                    <span className="currency-code">{currency}</span>
                                    <span className="currency-name">
                                        {getCurrencyName(currency)}
                                    </span>
                                </div>
                            </div>
                            
                            {loading ? (
                                <div className="rates-loading">Loading...</div>
                            ) : error ? (
                                <div className="rates-error">Error</div>
                            ) : rate ? (
                                <div className="currency-rates">
                                    <div className="rate-row">
                                        <span className="rate-label">Mid</span>
                                        <span className="rate-value rate-mid">{formatRate(rate.mid)}</span>
                                    </div>
                                    <div className="rate-row">
                                        <span className="rate-label">Buy</span>
                                        <span 
                                            className={`rate-value rate-buy ${!rate.buy ? 'rate-unavailable' : ''}`}
                                        >
                                            {rate.buy ? formatRate(rate.buy) : 'N/A'}
                                        </span>
                                    </div>
                                    <div className="rate-row">
                                        <span className="rate-label">Sell</span>
                                        <span 
                                            className={`rate-value rate-sell ${!rate.sell ? 'rate-unavailable' : ''}`}
                                        >
                                            {rate.sell ? formatRate(rate.sell) : 'N/A'}
                                        </span>
                                    </div>
                                </div>
                            ) : (
                                <div className="rates-error">No data</div>
                            )}
                        </button>
                    );
                    })
                ) : (
                    <div className="no-results">
                        <span>No currencies found</span>
                        <small>Try searching by currency code, name, or country</small>
                    </div>
                )}
            </div>
        </div>
    );
};

const CurrencyFlag = ({ code }) => {
    // Simple flag component using Unicode flag emojis
    const flags = {
        'EUR': '🇪🇺', // EU flag
        'USD': '🇺🇸', // US flag
        'CZK': '🇨🇿', // Czech Republic flag
        'IDR': '🇮🇩', // Indonesia flag
        'BRL': '🇧🇷'  // Brazil flag
    };
    
    return (
        <span className="flag-emoji" role="img" aria-label={`${code} flag`}>
            {flags[code] || '🏳️'}
        </span>
    );
};

const getCurrencyName = (code) => {
    const names = {
        'EUR': 'Euro',
        'USD': 'US Dollar',
        'CZK': 'Czech Koruna',
        'IDR': 'Indonesian Rupiah',
        'BRL': 'Brazilian Real'
    };
    return names[code] || code;
};

export default SidebarRates;
