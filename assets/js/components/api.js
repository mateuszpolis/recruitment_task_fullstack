import axios from 'axios';

// Create axios instance with relative base URL
const api = axios.create({
    baseURL: '', // Relative to current domain
    timeout: 10000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Request interceptor for debugging (optional)
api.interceptors.request.use(
    (config) => {
        console.log(`API Request: ${config.method?.toUpperCase()} ${config.url}`);
        return config;
    },
    (error) => Promise.reject(error)
);

// Response interceptor to normalize errors
api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Normalize error structure
        const normalizedError = {
            message: 'An error occurred',
            status: null,
            code: null,
            details: null
        };

        if (error.response) {
            // Server responded with error status
            const { status, data } = error.response;
            normalizedError.status = status;
            normalizedError.message = data?.error?.message || `HTTP ${status} Error`;
            normalizedError.code = data?.error?.code || null;
            normalizedError.details = data?.error?.details || null;
        } else if (error.request) {
            // Network error (no response received)
            normalizedError.message = 'Network error - please check your connection';
            normalizedError.code = 'NETWORK_ERROR';
        } else {
            // Request setup error
            normalizedError.message = error.message || 'Request configuration error';
            normalizedError.code = 'CONFIG_ERROR';
        }

        // Preserve original error for debugging
        normalizedError.originalError = error;
        
        return Promise.reject(normalizedError);
    }
);

/**
 * Get current exchange rates for specified currencies
 * @param {string|string[]} codes - Currency codes (e.g., 'EUR,USD' or ['EUR', 'USD'])
 * @returns {Promise<Object>} Response with current rates
 */
export const getCurrentRates = async (codes) => {
    const codesParam = Array.isArray(codes) ? codes.join(',') : codes;
    const params = codesParam ? { codes: codesParam } : {};
    
    const response = await api.get('/api/rates/current', { params });
    return response.data;
};

/**
 * Get historical exchange rates for a currency
 * @param {string} code - Currency code (e.g., 'EUR')
 * @param {string} date - Date in YYYY-MM-DD format
 * @param {number} [days=14] - Number of days to fetch
 * @returns {Promise<Object>} Response with historical rates
 */
export const getHistory = async (code, date, days = 14) => {
    if (!code || !date) {
        throw {
            message: 'Currency code and date are required',
            code: 'VALIDATION_ERROR',
            status: null,
            details: null
        };
    }

    const params = { date, days: days.toString() };
    const response = await api.get(`/api/rates/${encodeURIComponent(code)}/history`, { params });
    return response.data;
};

/**
 * Get a quote for currency exchange
 * @param {Object} quoteData - Quote request data
 * @param {string} quoteData.code - Currency code (e.g., 'EUR')
 * @param {string} quoteData.side - Either 'buy' or 'sell'
 * @param {string|number} quoteData.amount - Amount to exchange
 * @returns {Promise<Object>} Response with quote details
 */
export const postQuote = async ({ code, side, amount }) => {
    if (!code || !side || !amount) {
        throw {
            message: 'Currency code, side, and amount are required',
            code: 'VALIDATION_ERROR',
            status: null,
            details: null
        };
    }

    const requestBody = {
        code: code.toString(),
        side: side.toString(),
        amount: amount.toString()
    };

    const response = await api.post('/api/quote', requestBody);
    return response.data;
};

// Export the axios instance for advanced usage if needed
export { api };
