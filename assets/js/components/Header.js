import React, { useState, useEffect } from 'react';
import { getCurrentRates } from './api';

const Header = () => {
    const [lastUpdated, setLastUpdated] = useState(null);
    const [effectiveDate, setEffectiveDate] = useState(null);

    useEffect(() => {
        const fetchRateInfo = async () => {
            try {
                // Fetch just one currency to get the date info
                const response = await getCurrentRates(['EUR']);
                setEffectiveDate(response.effectiveDate);
                setLastUpdated(new Date(response.fetchedAt));
            } catch (error) {
                console.error('Error fetching rate info:', error);
                // Fallback to current time if API fails
                setLastUpdated(new Date());
            }
        };

        fetchRateInfo();
        
        // Update every minute to show fresh "updated" time
        const interval = setInterval(() => {
            setLastUpdated(new Date());
        }, 60000);

        return () => clearInterval(interval);
    }, []);

    const formatTime = (date) => {
        if (!date) return '';
        return date.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: false 
        });
    };

    const formatDate = (dateString) => {
        if (!dateString) return '';
        return new Date(dateString).toLocaleDateString('en-CA'); // YYYY-MM-DD format
    };

    return (
        <header className="app-header">
            <div className="header-content">
                <div className="header-left">
                    <h1>Exchange Office Dashboard</h1>
                    <p className="header-description">
                        Real-time currency exchange rates powered by 
                        <a 
                            href="https://api.nbp.pl/" 
                            target="_blank" 
                            rel="noopener noreferrer"
                            className="nbp-link"
                        >
                            NBP API
                        </a>
                    </p>
                    <div className="base-currency-info">
                        <span className="base-currency-flag" role="img" aria-label="Poland flag">🇵🇱</span>
                        <span className="base-currency-text">
                            All rates quoted in <strong>PLN</strong> (Polish Złoty)
                        </span>
                    </div>
                </div>
                
                <div className="header-right">
                    <div className="rate-info">
                        {effectiveDate && (
                            <span className="rate-date">
                                Rates as of {formatDate(effectiveDate)}
                            </span>
                        )}
                        {lastUpdated && (
                            <span className="update-time">
                                (updated {formatTime(lastUpdated)})
                            </span>
                        )}
                    </div>
                </div>
            </div>
        </header>
    );
};

export default Header;
