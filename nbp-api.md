# NBP Public API Documentation

## General Information

- **Response formats:** JSON or XML  
- **Format selection:**
  - Query parameter: `?format=json` or `?format=xml`
  - HTTP header: `Accept: application/json` or `Accept: application/xml`
- **Default:** JSON if not specified  

### Historic Data Availability
- Currency exchange rates: since **2002-01-02**
- Gold prices: since **2013-01-02**
- A single enquiry cannot cover a period longer than **93 days**

### Time Frame Options
Results can be determined in one of the following ways:
- Current data (latest released at query time)
- Data published today
- Series of last *N* quotations
- Data for a specific publication date
- Series from a defined time range

---

## Currency Exchange Rates API

Currency data is available in two ways:
1. **Complete table** of exchange rates (or series of tables)
2. **Single currency** exchange rate (or series of rates)

### Query Parameters
- `{table}` – table type (`A`, `B`, or `C`)
- `{code}` – 3-letter currency code (ISO 4217)
- `{topCount}` – max size of returned series
- `{date}`, `{startDate}`, `{endDate}` – dates in `YYYY-MM-DD` format (ISO 8601)

---

### Queries for Complete Tables

- **Current table of type {table}**  
https://api.nbp.pl/api/exchangerates/tables/{table}/
- **Series of latest {topCount} tables of type {table}**  
https://api.nbp.pl/api/exchangerates/tables/{table}/last/{topCount}/
- **Table of type {table} published today**  
https://api.nbp.pl/api/exchangerates/tables/{table}/today/
- **Table of type {table} published on {date}**  
https://api.nbp.pl/api/exchangerates/tables/{table}/{date}/
- **Series of tables of type {table} from {startDate} to {endDate}**  
https://api.nbp.pl/api/exchangerates/tables/{table}/{startDate}/{endDate}/
---

### Queries for Particular Currency

- **Current exchange rate {code} from table {table}**  
https://api.nbp.pl/api/exchangerates/rates/{table}/{code}/
- **Series of latest {topCount} exchange rates of {code} from table {table}**  
https://api.nbp.pl/api/exchangerates/rates/{table}/{code}/last/{topCount}/
- **Exchange rate of {code} from table {table} published today**  
https://api.nbp.pl/api/exchangerates/rates/{table}/{code}/today/
- **Exchange rate of {code} from table {table} published on {date}**  
https://api.nbp.pl/api/exchangerates/rates/{table}/{code}/{date}/
- **Series of exchange rates of {code} from table {table} between {startDate} and {endDate}**  
https://api.nbp.pl/api/exchangerates/rates/{table}/{code}/{startDate}/{endDate}/
---

### Response Parameters (Exchange Rates)

- **Table** – table type  
- **No** – table number  
- **TradingDate** – trading date (table C only)  
- **EffectiveDate** – publication date  
- **Rates** – list of exchange rates:
- **Country** – country name
- **Symbol** – currency symbol (numeric, historic data)
- **Currency** – currency name
- **Code** – currency code
- **Bid** – buy rate (table C only)
- **Ask** – sell rate (table C only)
- **Mid** – average rate (tables A & B)

---

## Gold Prices API

### Query Parameters
- `{topCount}` – max size of returned series
- `{date}`, `{startDate}`, `{endDate}` – dates in `YYYY-MM-DD`

### Queries

- **Current gold price**  
https://api.nbp.pl/api/cenyzlota
- **Series of latest {topCount} quotations**  
https://api.nbp.pl/api/cenyzlota/last/{topCount}
- **Price published today**  
https://api.nbp.pl/api/cenyzlota/today
- **Price on {date}**  
https://api.nbp.pl/api/cenyzlota/{date}
- **Series of prices from {startDate} to {endDate}**  
https://api.nbp.pl/api/cenyzlota/{startDate}/{endDate}
### Response Parameters (Gold Prices)
- **Date** – publication date  
- **Code** – price of 1g of gold (1000 millesimal fineness) at NBP  

---

## Example Queries

### Currency Exchange Rates
- Current average CHF exchange rate  
https://api.nbp.pl/api/exchangerates/rates/a/chf/
- USD buy/sell rate published today  
https://api.nbp.pl/api/exchangerates/rates/c/usd/today/
- USD buy/sell rate on 2016-04-04 (JSON format)  
https://api.nbp.pl/api/exchangerates/rates/c/usd/2016-04-04/?format=json
- Current table A of average exchange rates  
https://api.nbp.pl/api/exchangerates/tables/a/
- Table A published today  
https://api.nbp.pl/api/exchangerates/tables/a/today/
- Last 10 GBP quotations (JSON format)  
https://api.nbp.pl/api/exchangerates/rates/a/gbp/last/10/?format=json
- Last 10 USD buy/sell quotations (XML format)  
https://api.nbp.pl/api/exchangerates/rates/c/usd/last/10/?format=xml
- GBP average exchange rates (2012-01-01 to 2012-01-31)  
https://api.nbp.pl/api/exchangerates/rates/a/gbp/2012-01-01/2012-01-31/
- GBP average exchange rate (2012-01-02)  
https://api.nbp.pl/api/exchangerates/rates/a/gbp/2012-01-02/
- Last 5 tables of type A  
https://api.nbp.pl/api/exchangerates/tables/a/last/5/
- Tables A from 2012-01-01 to 2012-01-31  
https://api.nbp.pl/api/exchangerates/tables/a/2012-01-01/2012-01-31/
- Table B on 2016-03-30  
https://api.nbp.pl/api/exchangerates/tables/b/2016-03-30/
---

### Gold Prices
- Current gold price  
https://api.nbp.pl/api/cenyzlota/
- Last 30 quotations (JSON format)  
https://api.nbp.pl/api/cenyzlota/last/30/?format=json
- Price published today  
https://api.nbp.pl/api/cenyzlota/today/
- Gold price on 2013-01-02  
https://api.nbp.pl/api/cenyzlota/2013-01-02/
- Gold prices from 2013-01-01 to 2013-01-31  
https://api.nbp.pl/api/cenyzlota/2013-01-01/2013-01-31/
---

## Error Messages
- **404 Not Found** – no data for the requested time interval  
- **400 Bad Request** – invalid request  
- **400 Bad Request – Limit exceeded** – request exceeded the data size limit  

---
