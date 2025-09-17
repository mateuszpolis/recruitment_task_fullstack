import React, { useState, useEffect } from 'react';
import { getCurrentRates } from './api';
import { getExtendedCurrencyData } from '../utils/currencyData';

const SidebarRates = ({ selectedCurrency, onCurrencySelect }) => {
    const currencies = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];
    const [rates, setRates] = useState({});
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [lastRefresh, setLastRefresh] = useState(null);
    const [nextRefresh, setNextRefresh] = useState(null);
    const [refreshReason, setRefreshReason] = useState('');
    const [favorites, setFavorites] = useState([]);

    // Load favorites from localStorage on component mount
    useEffect(() => {
        const savedFavorites = localStorage.getItem('currencyFavorites');
        if (savedFavorites) {
            try {
                const parsedFavorites = JSON.parse(savedFavorites);
                if (Array.isArray(parsedFavorites)) {
                    setFavorites(parsedFavorites);
                }
            } catch (error) {
                console.error(
                    'Error parsing favorites from localStorage:',
                    error
                );
                localStorage.removeItem('currencyFavorites');
            }
        }
    }, []);

    useEffect(() => {
        const fetchRates = async (isRetry = false) => {
            try {
                if (!isRetry) setLoading(true);
                setError(null);
                const response = await getCurrentRates(currencies);

                // Convert array to object for easier lookup
                const ratesMap = {};
                response.rates.forEach((rate) => {
                    ratesMap[rate.code] = rate;
                });
                setRates(ratesMap);
                setLastRefresh(new Date());
            } catch (err) {
                setError(err.message || 'Failed to fetch rates');
                console.error('Error fetching rates:', err);

                // Retry once after 30 seconds if this wasn't already a retry
                if (!isRetry) {
                    setTimeout(() => fetchRates(true), 30 * 1000);
                }
            } finally {
                setLoading(false);
            }
        };

        fetchRates();

        // Smart refresh based on NBP publish schedule
        const setupSmartRefresh = () => {
            const now = new Date();
            const warsawTime = new Date(
                now.toLocaleString('en-US', { timeZone: 'Europe/Warsaw' })
            );
            const day = warsawTime.getDay(); // 0 = Sunday, 6 = Saturday
            const hours = warsawTime.getHours();
            const minutes = warsawTime.getMinutes();
            const currentMinutes = hours * 60 + minutes;

            // Weekend (Saturday/Sunday): check once every 4 hours
            if (day === 0 || day === 6) {
                const nextRefreshTime = new Date(
                    warsawTime.getTime() + 4 * 60 * 60 * 1000
                );
                setNextRefresh(nextRefreshTime);
                setRefreshReason('Weekend - NBP nie publikuje w weekendy');
                return setTimeout(fetchRates, 4 * 60 * 60 * 1000); // 4 hours
            }

            // Weekday logic based on NBP publish schedule
            const publish11_30 = 11 * 60 + 30; // 11:30 AM
            const publish12_30 = 12 * 60 + 30; // 12:30 PM

            if (currentMinutes < publish11_30) {
                // Before publish window: refresh at 11:30
                const minutesUntil11_30 = publish11_30 - currentMinutes;
                const nextRefreshTime = new Date(
                    warsawTime.getTime() + minutesUntil11_30 * 60 * 1000
                );
                setNextRefresh(nextRefreshTime);
                setRefreshReason(
                    'Przed publikacją - NBP publikuje około 11:30-12:30'
                );

                return setTimeout(
                    () => {
                        fetchRates();
                        setupSmartRefresh(); // Re-setup after fetch
                    },
                    minutesUntil11_30 * 60 * 1000
                );
            } else if (currentMinutes < publish12_30) {
                // During publish window: check every 5 minutes
                const nextRefreshTime = new Date(
                    warsawTime.getTime() + 5 * 60 * 1000
                );
                setNextRefresh(nextRefreshTime);
                setRefreshReason(
                    'Okno publikacji - sprawdzanie nowych kursów NBP'
                );
                return setTimeout(
                    () => {
                        fetchRates();
                        setupSmartRefresh(); // Re-setup after fetch
                    },
                    5 * 60 * 1000
                );
            } else {
                // After publish window: wait until next business day 11:30
                const nextBusinessDay = getNextBusinessDay(warsawTime);
                const next11_30 = new Date(nextBusinessDay);
                next11_30.setHours(11, 30, 0, 0);

                const msUntilNext = next11_30.getTime() - warsawTime.getTime();
                setNextRefresh(next11_30);
                setRefreshReason(
                    'Po publikacji - oczekiwanie na następny dzień roboczy'
                );
                return setTimeout(() => {
                    fetchRates();
                    setupSmartRefresh(); // Re-setup after fetch
                }, msUntilNext);
            }
        };

        const timeoutId = setupSmartRefresh();

        return () => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        };
    }, []);

    // Update relative time display every minute
    useEffect(() => {
        const interval = setInterval(() => {
            // Force re-render to update relative time display
            if (nextRefresh) {
                setNextRefresh(new Date(nextRefresh.getTime()));
            }
        }, 60000); // Update every minute

        return () => clearInterval(interval);
    }, [nextRefresh]);

    // Helper function to get next business day (skips weekends)
    const getNextBusinessDay = (date) => {
        const nextDay = new Date(date);
        nextDay.setDate(nextDay.getDate() + 1);

        // Skip weekends
        while (nextDay.getDay() === 0 || nextDay.getDay() === 6) {
            nextDay.setDate(nextDay.getDate() + 1);
        }

        return nextDay;
    };

    // Toggle favorite status of a currency
    const toggleFavorite = (currencyCode) => {
        const newFavorites = favorites.includes(currencyCode)
            ? favorites.filter((fav) => fav !== currencyCode)
            : [...favorites, currencyCode];

        setFavorites(newFavorites);

        // Save to localStorage
        try {
            localStorage.setItem(
                'currencyFavorites',
                JSON.stringify(newFavorites)
            );
        } catch (error) {
            console.error('Error saving favorites to localStorage:', error);
        }
    };

    const isFavorite = (currencyCode) => favorites.includes(currencyCode);

    const formatRate = (rate) => {
        if (!rate) return '—';
        return parseFloat(rate).toFixed(4);
    };

    // Use shared currency data utility
    const getCurrencyData = getExtendedCurrencyData;

    // Filter currencies based on search term
    const filteredCurrencies = currencies.filter((currency) => {
        if (!searchTerm.trim()) return true;

        const searchLower = searchTerm.toLowerCase();
        const data = getCurrencyData(currency);

        return (
            currency.toLowerCase().includes(searchLower) ||
            data.name.toLowerCase().includes(searchLower) ||
            data.country.toLowerCase().includes(searchLower)
        );
    });

    // Separate favorites and non-favorites
    const favoriteCurrencies = filteredCurrencies.filter((currency) =>
        isFavorite(currency)
    );
    const regularCurrencies = filteredCurrencies.filter(
        (currency) => !isFavorite(currency)
    );

    const formatTime = (date) => {
        if (!date) return 'Nigdy';
        return date.toLocaleTimeString('en-PL', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        });
    };

    const formatRelativeTime = (date) => {
        if (!date) return '';
        const now = new Date();
        const diff = date.getTime() - now.getTime();

        if (diff < 0) return 'przeterminowane';

        const minutes = Math.floor(diff / (1000 * 60));
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (days > 0) return `za ${days}d ${hours % 24}h`;
        if (hours > 0) return `za ${hours}h ${minutes % 60}m`;
        if (minutes > 0) return `za ${minutes}m`;
        return 'wkrótce';
    };

    return (
        <div className="sidebar-rates">
            <h3>💵 Kursy walut</h3>

            {/* Refresh Status */}
            <div className="refresh-status">
                <div className="status-row">
                    <span className="status-label">Ostatnia aktualizacja:</span>
                    <span className="status-value">
                        {formatTime(lastRefresh)}
                    </span>
                </div>
                <div className="status-row">
                    <span className="status-label">Następna:</span>
                    <span className="status-value">
                        {formatTime(nextRefresh)}
                        <span className="status-relative">
                            ({formatRelativeTime(nextRefresh)})
                        </span>
                    </span>
                </div>
                {refreshReason && (
                    <div className="status-reason">{refreshReason}</div>
                )}
            </div>

            <div className="search-container">
                <input
                    type="text"
                    className="search-input"
                    placeholder="Wyszukaj waluty..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                />
            </div>

            <div className="currency-list">
                {filteredCurrencies.length > 0 ? (
                    <>
                        {/* Favorites Section */}
                        {favoriteCurrencies.length > 0 && (
                            <div className="favorites-section">
                                <div className="section-header">
                                    <span className="section-title">
                                        ★ Ulubione
                                    </span>
                                    <span className="section-count">
                                        ({favoriteCurrencies.length})
                                    </span>
                                </div>
                                {favoriteCurrencies.map((currency) => (
                                    <CurrencyItem
                                        key={`fav-${currency}`}
                                        currency={currency}
                                        rate={rates[currency]}
                                        isSelected={
                                            selectedCurrency === currency
                                        }
                                        isLoading={loading}
                                        hasError={error}
                                        isFavorite={true}
                                        onSelect={onCurrencySelect}
                                        onToggleFavorite={toggleFavorite}
                                        formatRate={formatRate}
                                        getCurrencyData={getCurrencyData}
                                    />
                                ))}
                            </div>
                        )}

                        {/* Regular Currencies Section */}
                        {regularCurrencies.length > 0 && (
                            <div className="regular-section">
                                {favoriteCurrencies.length > 0 && (
                                    <div className="section-header">
                                        <span className="section-title">
                                            Wszystkie waluty
                                        </span>
                                        <span className="section-count">
                                            ({regularCurrencies.length})
                                        </span>
                                    </div>
                                )}
                                {regularCurrencies.map((currency) => (
                                    <CurrencyItem
                                        key={`reg-${currency}`}
                                        currency={currency}
                                        rate={rates[currency]}
                                        isSelected={
                                            selectedCurrency === currency
                                        }
                                        isLoading={loading}
                                        hasError={error}
                                        isFavorite={false}
                                        onSelect={onCurrencySelect}
                                        onToggleFavorite={toggleFavorite}
                                        formatRate={formatRate}
                                        getCurrencyData={getCurrencyData}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                ) : (
                    <div className="no-results">
                        <span>Nie znaleziono walut</span>
                        <small>
                            Spróbuj wyszukać po kodzie, nazwie lub kraju
                        </small>
                    </div>
                )}
            </div>
        </div>
    );
};

const CurrencyItem = ({
    currency,
    rate,
    isSelected,
    isLoading,
    hasError,
    isFavorite,
    onSelect,
    onToggleFavorite,
    formatRate,
    getCurrencyData,
}) => {
    const handleFavoriteClick = (e) => {
        e.stopPropagation(); // Prevent triggering the currency selection
        onToggleFavorite(currency);
    };

    const currencyData = getCurrencyData(currency);

    return (
        <button
            className={`currency-item ${isSelected ? 'active' : ''} ${isFavorite ? 'favorite' : ''}`}
            onClick={() => onSelect(currency)}
            disabled={isLoading}
        >
            <div className="currency-header">
                <div className="currency-flag">
                    <span
                        className="flag-emoji"
                        role="img"
                        aria-label={`${currency} flag`}
                    >
                        {currencyData.flag}
                    </span>
                </div>
                <div className="currency-info">
                    <span className="currency-code">{currency}</span>
                    <span className="currency-name">{currencyData.name}</span>
                </div>
                <button
                    className="favorite-button"
                    onClick={handleFavoriteClick}
                    title={
                        isFavorite ? 'Usuń z ulubionych' : 'Dodaj do ulubionych'
                    }
                    disabled={isLoading}
                >
                    {isFavorite ? (
                        <span className="favorite-icon favorite-icon--filled">
                            ★
                        </span>
                    ) : (
                        <span className="favorite-icon favorite-icon--empty">
                            ☆
                        </span>
                    )}
                </button>
            </div>

            {isLoading ? (
                <div className="rates-loading">Ładowanie...</div>
            ) : hasError ? (
                <div className="rates-error">Błąd</div>
            ) : rate ? (
                <div className="currency-rates">
                    <div className="rate-row">
                        <span className="rate-label">Średnia</span>
                        <span className="rate-value rate-mid">
                            {formatRate(rate.mid)}
                        </span>
                    </div>
                    <div className="rate-row">
                        <span className="rate-label">Kupno</span>
                        <span
                            className={`rate-value rate-buy ${!rate.buy ? 'rate-unavailable' : ''}`}
                        >
                            {rate.buy ? formatRate(rate.buy) : 'N/D'}
                        </span>
                    </div>
                    <div className="rate-row">
                        <span className="rate-label">Sprzedaż</span>
                        <span
                            className={`rate-value rate-sell ${!rate.sell ? 'rate-unavailable' : ''}`}
                        >
                            {rate.sell ? formatRate(rate.sell) : 'N/D'}
                        </span>
                    </div>
                </div>
            ) : (
                <div className="rates-error">Brak danych</div>
            )}
        </button>
    );
};

export default SidebarRates;
