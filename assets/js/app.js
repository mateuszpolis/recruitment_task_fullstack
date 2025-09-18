/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

import React, { useState } from 'react';
import ReactDOM from 'react-dom';
import { BrowserRouter as Router } from 'react-router-dom';
import '../css/app.css';

// Import the three panel components
import SidebarRates from './components/SidebarRates';
import RateChart from './components/RateChart';
import Calculator from './components/Calculator';

const App = () => {
    const [selectedCurrency, setSelectedCurrency] = useState('EUR');

    return (
        <div className="app-container">
            <main className="app-main">
                <div className="panel panel-left">
                    <SidebarRates
                        selectedCurrency={selectedCurrency}
                        onCurrencySelect={setSelectedCurrency}
                    />
                </div>

                <div className="panel panel-middle">
                    <RateChart selectedCurrency={selectedCurrency} />
                </div>

                <div className="panel panel-right">
                    <Calculator
                        selectedCurrency={selectedCurrency}
                        onCurrencyChange={setSelectedCurrency}
                    />
                </div>
            </main>
        </div>
    );
};

ReactDOM.render(
    <Router>
        <App />
    </Router>,
    document.getElementById('root')
);
