<?php
require_once __DIR__ . '/layout.php';

site_begin('Stock Browser & Search');

?>

<section class="panel">
    <form class="flex" id="stockForm">
        <label>
            Symbol
            <input
                id="ticker"
                value="AAPL"
                required
                pattern="[a-zA-Z0-9.\-]{1,15}"
            >
        </label>

        <button>View stock</button>
    </form>

    <p>
        Try
        <button type="button" class="quick" data-symbol="MSFT">MSFT</button>
        <button type="button" class="quick" data-symbol="NVDA">NVDA</button>
        <button type="button" class="quick" data-symbol="AMZN">AMZN</button>
    </p>
</section>


<section class="panel">
    <h2>Company details</h2>
    <div id="company">Search for a stock.</div>
</section>


<section class="panel">
    <h2>Simulation quote</h2>
    <p id="quote">—</p>
    <p class="note">
        Data may be previous-day or delayed; check the timestamp before trading.
    </p>
</section>


<section class="panel">
    <h2>Stock price history</h2>
    <div id="chart"></div>
    <div id="historyInfo"></div>
</section>


<section class="panel">
    <h2>Search company names</h2>

    <form class="flex" id="searchForm">
        <label>
            Company name
            <input id="searchText" value="Apple">
        </label>

        <button>Search companies</button>
    </form>

    <div id="searchResults"></div>
</section>


<script src="api.js"></script>
<script src="charts.js"></script>

<script>

async function showStock(symbol) {
    $('company').textContent = 'Loading company details...';
    $('quote').textContent = 'Loading quote...';
    $('chart').textContent = 'Loading history...';

    const requests = await Promise.allSettled([
        rpc('fetch_stock', { ticker: symbol }),
        rpc('get_stock_price', { ticker: symbol }),
        rpc('fetch_stock_history', { ticker: symbol })
    ]);

    const [companyResult, quoteResult, historyResult] = requests;

    if (companyResult.status === 'fulfilled') {
        const company = companyResult.value.data || companyResult.value;

        const details = Object.entries(company)
            .filter(([, value]) =>
                ['string', 'number'].includes(typeof value)
            )
            .map(([name, value]) => ({ name, value }));

        renderTable(
            'company',
            [
                ['Field', 'name'],
                ['Value', 'value']
            ],
            details
        );
    } else {
        showError('company', companyResult.reason);
    }


    if (quoteResult.status === 'fulfilled') {
        const priceInfo = quoteResult.value;

        $('quote').textContent =
            `${formatMoney(priceInfo.price)} — ` +
            `${priceInfo.price_type || 'quote'} — ` +
            `${priceInfo.as_of || 'timestamp unavailable'}`;

    } else {
        showError('quote', quoteResult.reason);
    }


    if (historyResult.status === 'fulfilled') {
        const dailyPrices = historyResult.value.history || [];

        lineChart(
            $('chart'),
            dailyPrices,
            'date',
            'close',
            'Stock closing price'
        );

        $('historyInfo').textContent =
            `${dailyPrices.length} data points`;

    } else {
        showError('chart', historyResult.reason);
    }
}


$('stockForm').addEventListener('submit', event => {
    event.preventDefault();

    showStock($('ticker').value.toUpperCase())
        .catch(error => showError('company', error));
});


document.querySelectorAll('.quick').forEach(button => {
    button.addEventListener('click', () => {
        $('ticker').value = button.dataset.symbol;
        showStock(button.dataset.symbol);
    });
});


$('searchForm').addEventListener('submit', async event => {
    event.preventDefault();

    try {
        const searchReply = await rpc('search_stocks', {
            query: $('searchText').value
        });

        renderTable(
            'searchResults',
            [
                ['Ticker', 'symbol'],
                ['Company', 'name']
            ],
            searchReply.results || []
        );

    } catch (error) {
        showError('searchResults', error);
    }
});


showStock('AAPL')
    .catch(error => showError('company', error));

</script>

<?php site_end(); ?>

