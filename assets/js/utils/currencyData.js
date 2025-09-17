// Centralized currency data utility
export const getCurrencyData = (code) => {
    const currencyData = {
        'PLN': { 
            name: 'Polski Złoty', 
            flag: '🇵🇱', 
            symbol: 'zł' 
        },
        'EUR': { 
            name: 'Euro', 
            flag: '🇪🇺', 
            symbol: '€' 
        },
        'USD': { 
            name: 'Dolar amerykański', 
            flag: '🇺🇸', 
            symbol: '$' 
        },
        'CZK': { 
            name: 'Korona czeska', 
            flag: '🇨🇿', 
            symbol: 'Kč' 
        },
        'IDR': { 
            name: 'Rupia indonezyjska', 
            flag: '🇮🇩', 
            symbol: 'Rp' 
        },
        'BRL': { 
            name: 'Real brazylijski', 
            flag: '🇧🇷', 
            symbol: 'R$' 
        }
    };
    
    return currencyData[code] || { 
        name: code, 
        flag: '🏳️', 
        symbol: code 
    };
};

// Extended currency data for search functionality
export const getExtendedCurrencyData = (code) => {
    const currencyData = {
        'EUR': { 
            name: 'Euro', 
            country: 'Unia Europejska',
            flag: '🇪🇺',
            symbol: '€'
        },
        'USD': { 
            name: 'Dolar amerykański', 
            country: 'Stany Zjednoczone, USA, Ameryka',
            flag: '🇺🇸',
            symbol: '$'
        },
        'CZK': { 
            name: 'Korona czeska', 
            country: 'Czechy',
            flag: '🇨🇿',
            symbol: 'Kč'
        },
        'IDR': { 
            name: 'Rupia indonezyjska', 
            country: 'Indonezja',
            flag: '🇮🇩',
            symbol: 'Rp'
        },
        'BRL': { 
            name: 'Real brazylijski', 
            country: 'Brazylia',
            flag: '🇧🇷',
            symbol: 'R$'
        }
    };
    
    return currencyData[code] || { 
        name: code, 
        country: '', 
        flag: '🏳️', 
        symbol: code 
    };
};
