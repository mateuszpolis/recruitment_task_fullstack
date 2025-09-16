import React from 'react';

const SidebarRates = ({ selectedCurrency, onCurrencySelect }) => {
    const currencies = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];

    return (
        <div className="sidebar-rates">
            <h3>Exchange Rates</h3>
            <div className="currency-list">
                {currencies.map(currency => (
                    <button
                        key={currency}
                        className={`currency-item ${selectedCurrency === currency ? 'active' : ''}`}
                        onClick={() => onCurrencySelect(currency)}
                    >
                        <span className="currency-code">{currency}</span>
                        <span className="currency-name">
                            {getCurrencyName(currency)}
                        </span>
                    </button>
                ))}
            </div>
        </div>
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
