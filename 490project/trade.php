<?php
require_once __DIR__ . '/layout.php';
site_begin('Virtual Trading');
?>

<section class="panel">
    <h2>Virtual Stock Trading</h2>
    <p>Buy and sell stocks using your virtual funds.</p>

    <div class="flex">
        <label>
            Stock Symbol
            <input
                id="tradeTicker"
                type="text"
                placeholder="AAPL"
                required
            >
        </label>

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
    </div>
    <br>
    <div class="flex">
        <button type="button" id="buyButton">Buy Stock</button>
        <button type="button" id="sellButton">Sell Stock</button>
    </div>

    <p id="tradeMessage"></p>
</section>

<script src="api.js"></script>

<script>

async function executeTrade(side) {

    const ticker = $('tradeTicker').value.trim().toUpperCase();
    const quantity = $('tradeQuantity').value.trim();

    if (!/^[A-Z0-9.\-]{1,15}$/.test(ticker)) {
        $('tradeMessage').textContent = 'Please enter a valid stock symbol.';
        return;
    }

    if (!/^[1-9][0-9]{0,5}$/.test(quantity)) {
        $('tradeMessage').textContent = 'Please enter a valid number of shares.';
        return;
    }

    $('buyButton').disabled = true;
    $('sellButton').disabled = true;

    $('tradeMessage').textContent = 'Processing trade for '+ticker+ '...';

    try {

        const response = await rpc('execute_trade', {
            symbol: ticker,
            side: side,
            quantity: quantity
        });

        $('tradeMessage').textContent =
            response.message +
            ' | Remaining Balance: ' +
            formatMoney(response.balance);

    } catch (error) {

        $('tradeMessage').textContent =
            'Trade failed: ' + error.message;

    } finally {

        $('buyButton').disabled = false;
        $('sellButton').disabled = false;

    }
}


$('buyButton').addEventListener('click', function () {
    executeTrade('BUY');
});

$('sellButton').addEventListener('click', function () {
    executeTrade('SELL');
});


const params = new URLSearchParams(window.location.search);
const tickerFromStocks = params.get('ticker');

if (tickerFromStocks) {
    $('tradeTicker').value = tickerFromStocks.toUpperCase();
}

</script>

<?php site_end(); ?>


