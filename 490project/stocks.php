<?php
require_once __DIR__ . '/layout.php';
site_begin('Stock Browser & Search');

?>

<section class="panel">
    <h2>Search Stocks</h2>

    <form class="flex" id="stockForm">
        <label>
            Stock Symbol
            <input
                id="ticker"
                type="text"
                placeholder="Enter symbol (AAPL, TSLA, MSFT)"
                maxlength="15"
                pattern="[a-zA-Z0-9.\-]{1,15}"
                required
            >
        </label>
        <button type="submit" id="stockSearchButton">Search</button>
    </form>

    <p class="note">Or try one of these stocks:</p>

    <div class="flex">
        <button type="button" class="quick" data-symbol="AAPL">AAPL</button>
        <button type="button" class="quick" data-symbol="MSFT">MSFT</button>
        <button type="button" class="quick" data-symbol="NVDA">NVDA</button>
        <button type="button" class="quick" data-symbol="TSLA">TSLA</button>
    </div>
</section>

<section class="panel">
    <h2>Company Details</h2>
    <div id="company">Enter a stock symbol to view company information.</div>
</section>

<section class="panel">
    <h2>Stock Price</h2>
    <p id="quote">Search for a stock to view its price.</p>
    <p class="note">Prices may be delayed or from the previous trading day.</p>
</section>

<section class="panel">
    <h2>Virtual Trading</h2>
    <p>Buy or sell stocks using your virtual funds.</p>

    <div class="flex">
        <label>
            Number of Shares
            <input
                id="tradeQuantity"
                type="number"
                min="1"
                max="999999"
                step="1"
                value="1"
                required
            >
        </label>

        <button type="button" id="buyButton">
            Buy Stock
        </button>

        <button type="button" id="sellButton">
            Sell Stock
        </button>
    </div>
    <p id="tradeMessage"></p>
</section>

<section class="panel">
    <h2>Stock Price History</h2>
    <div id="chart">Search for a stock to view historical prices.</div>
    <p id="historyInfo" class="note"></p>
</section>

<section class="panel">
    <h2>Search by Company Name</h2>

    <form class="flex" id="searchForm">
        <label>
            Company Name
            <input
                id="searchText"
                type="text"
                placeholder="Example: Apple"
                required
            >
        </label>

        <button type="submit" id="companySearchButton">Search Companies</button>
    </form>

    <div id="searchResults"></div>
</section>

<script src="api.js"></script>
<script src="charts.js"></script>
<script>

let stockSearchRunning = false;

function validStockSymbol(symbol) {
    return /^[A-Z0-9.\-]{1,15}$/.test(symbol);
}

function updateStockUrl(symbol) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('ticker', symbol);
    window.history.replaceState({}, '', currentUrl);
}

function setSearchButtons(disabled) {
    $('stockSearchButton').disabled = disabled;
    $('companySearchButton').disabled = disabled;

    document.querySelectorAll('.quick').forEach(button => {
        button.disabled = disabled;
    });
}

async function showStock(symbol) {
    if (stockSearchRunning) return;

    const ticker = String(symbol).trim().toUpperCase();

    if (!validStockSymbol(ticker)) {
        $('company').textContent = 'Please enter a valid stock symbol.';
        return;
    }

    stockSearchRunning = true;
    setSearchButtons(true);
    $('ticker').value = ticker;
    updateStockUrl(ticker);

    $('company').textContent = 'Loading company information...';
    $('quote').textContent = 'Loading stock price...';
    $('chart').textContent = 'Loading price history...';
    $('historyInfo').textContent = '';

    try {
        // Send RabbitMQ requests one at a time.

        try {
            const companyReply = await rpc('fetch_stock', {
                ticker: ticker
            });

            const companyInfo = companyReply.data || companyReply;

            const fieldNames = {
                ticker: 'Stock Symbol',
                name: 'Company Name',
                market: 'Market',
                locale: 'Country',
                primary_exchange: 'Primary Exchange',
                type: 'Security Type',
                currency_name: 'Currency',
                cik: 'SEC CIK Number',
                composite_figi: 'Composite FIGI',
                share_class_figi: 'Share Class FIGI',
                list_date: 'Listing Date',
                market_cap: 'Market Capitalization',
                description: 'Company Description',
                homepage_url: 'Company Website',
                total_employees: 'Total Employees',
                sic_description: 'Industry',
                sic_code: 'Industry Code'
            };

            const companyDetails = Object.entries(companyInfo)
                .filter(([, value]) => {
                    return typeof value === 'string' ||
                           typeof value === 'number';
                })
                .map(([field, value]) => {
                    const displayName = fieldNames[field] ||
                        field.replace(/_/g, ' ')
                             .replace(/\b\w/g, letter => letter.toUpperCase());

                    let displayValue = value;

                    if (field === 'currency_name' || field === 'locale') {
                        displayValue = String(value).toUpperCase();
                    }

                    if (field === 'market') {
                        displayValue = String(value).replace(
                            /\b\w/g,
                            letter => letter.toUpperCase()
                        );
                    }

                    return {
                        field: displayName,
                        value: displayValue
                    };
                });

            renderTable(
                'company',
                [
                    ['Company Detail', 'field'],
                    ['Information', 'value']
                ],
                companyDetails
            );

        } catch (error) {
            showError('company', error);
        }

        try {
            const priceReply = await rpc('get_stock_price', {
                ticker: ticker
            });

            const stockPrice = priceReply.price;
            const priceType = priceReply.price_type || 'quote';
            const priceTime =
                priceReply.as_of ||
                priceReply.market_timestamp ||
                'Timestamp unavailable';

            $('quote').textContent =
                `${formatMoney(stockPrice)} — ${priceType} — ${priceTime}`;

        } catch (error) {
            showError('quote', error);
        }

        try {
            const historyReply = await rpc('fetch_stock_history', {
                ticker: ticker
            });

            const stockHistory = historyReply.history || [];

            if (!stockHistory.length) {
                $('chart').textContent = 'No historical prices available.';
                $('historyInfo').textContent = '';
            } else if (typeof lineChart === 'function') {
                lineChart(
                    $('chart'),
                    stockHistory,
                    'date',
                    'close',
                    'Stock Closing Price'
                );

                $('historyInfo').textContent =
                    `${stockHistory.length} historical price points`;
            } else {
                $('chart').textContent =
                    'Chart display is unavailable. Check charts.js.';
            }

        } catch (error) {
            showError('chart', error);
        }

    } finally {
        stockSearchRunning = false;
        setSearchButtons(false);
    }
}

$('stockForm').addEventListener('submit', function (event) {
    event.preventDefault();

    const enteredSymbol = $('ticker').value.trim().toUpperCase();

    if (!validStockSymbol(enteredSymbol)) {
        $('company').textContent = 'Please enter a valid stock symbol.';
        return;
    }

    showStock(enteredSymbol);
});

document.querySelectorAll('.quick').forEach(button => {
    button.addEventListener('click', function () {
        const selectedSymbol = button.dataset.symbol;
        $('ticker').value = selectedSymbol;
        showStock(selectedSymbol);
    });
});

$('searchForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    if (stockSearchRunning) return;

    const companyName = $('searchText').value.trim();

    if (!companyName) {
        $('searchResults').textContent = 'Enter a company name.';
        return;
    }

    stockSearchRunning = true;
    setSearchButtons(true);
    $('searchResults').textContent = 'Searching companies...';

    try {
        const searchReply = await rpc('search_stocks', {
            query: companyName
        });

        const matches = (searchReply.results || []).map(stock => ({
            ticker: stock.ticker || stock.symbol || '—',
            name: stock.name || stock.company_name || 'Unknown company'
        }));

        if (matches.length === 0) {
            $('searchResults').textContent = 'No companies found.';
        } else {
            renderTable(
                'searchResults',
                [
                    ['Ticker', 'ticker'],
                    ['Company', 'name']
                ],
                matches
            );
        }

    } catch (error) {
        showError('searchResults', error);
    } finally {
        stockSearchRunning = false;
        setSearchButtons(false);
    }
});



// Handle virtual stock trading
async function executeTrade(side) {

    const ticker = $('ticker').value.trim().toUpperCase();
    const quantity = $('tradeQuantity').value.trim();

    if (stockSearchRunning) {
        $('tradeMessage').textContent =
            'Please wait for the stock search to finish.';
        return;
    }

    if (!validStockSymbol(ticker)) {
        $('tradeMessage').textContent =
            'Please search for a valid stock first.';
        return;
    }

    if (!/^[1-9][0-9]{0,5}$/.test(quantity)) {
        $('tradeMessage').textContent =
            'Please enter a valid whole number of shares.';
        return;
    }

    $('buyButton').disabled = true;
    $('sellButton').disabled = true;

    $('tradeMessage').textContent = 'Processing trade...';

    try {
        const response = await rpc('execute_trade', {
            symbol: ticker,
            side: side,
            quantity: quantity
        });

        $('tradeMessage').textContent =
            response.message +
            ' | Remaining balance: ' +
            formatMoney(response.balance);

    } catch (error) {
        $('tradeMessage').textContent =
            'Trade failed: ' + error.message;

    } finally {
        $('buyButton').disabled = false;
        $('sellButton').disabled = false;
    }
}


// BUY button
$('buyButton').addEventListener('click', function () {
    executeTrade('BUY');
});


// SELL button
$('sellButton').addEventListener('click', function () {
    executeTrade('SELL');
});

// Only load a stock when a ticker is provided in the URL.

const pageParams = new URLSearchParams(window.location.search);
const tickerFromHome = pageParams.get('ticker');

if (tickerFromHome) {
    const startingSymbol = tickerFromHome.trim().toUpperCase();

    if (validStockSymbol(startingSymbol)) {
        $('ticker').value = startingSymbol;
        showStock(startingSymbol).catch(error => showError('company', error));
    } else {
        $('company').textContent = 'Invalid stock symbol in URL.';
    }
}

</script>

<?php site_end(); ?>

